<?php

namespace Modules\Flowmaker\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Services\Payments\PaystackCommerceService;
use App\Services\Security\WebhookSignature;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Modules\Flowmaker\Jobs\ResumeFlowFromPayment;
use Modules\Invoice\Models\InvoicePayment;

class PaystackController extends Controller
{
    public function callback(Request $request, PaystackCommerceService $paystack)
    {
        $reference = (string) $request->query('reference', $request->input('reference', ''));
        Log::info('Paystack flow callback', ['reference' => $reference]);

        if ($reference === '') {
            return response('Missing reference', 400);
        }

        $payment = InvoicePayment::query()->where('gateway_reference', $reference)->first();
        if (! $payment) {
            return response('Payment not found', 404);
        }

        $invoice = $payment->invoice;
        $company = $invoice?->company;
        if (! $company) {
            return response('Company not found', 404);
        }

        $verified = $paystack->verify($company, $reference);
        $status = ($verified['success'] ?? false) && (($verified['data']['status'] ?? '') === 'success')
            ? 'success'
            : 'failed';

        if ($status === 'success') {
            $payment->markAsSuccess($reference);
            $payment->update(['paid_via' => 'paystack']);
        } else {
            $payment->update([
                'status' => 'failed',
                'completed_at' => now(),
                'result_description' => $verified['message'] ?? 'Verification failed',
            ]);
        }

        $notes = is_array($invoice->notes) ? $invoice->notes : [];
        $flowId = (int) ($notes['flow_id'] ?? 0);
        $contactId = (int) ($notes['contact_id'] ?? 0);

        if ($flowId > 0 && $contactId > 0) {
            ResumeFlowFromPayment::dispatch($flowId, $contactId, $status)->onQueue('flows');
        }

        $redirect = route('catalog.invoice.pay', $invoice->public_uuid ?? $invoice->id);

        return redirect()->away($redirect);
    }

    public function webhook(Request $request, PaystackCommerceService $paystack)
    {
        $payload = $request->all();
        $reference = (string) data_get($payload, 'data.reference', '');
        $payment = $reference !== ''
            ? InvoicePayment::query()
                ->where('gateway_reference', $reference)
                ->orWhere('mpesa_checkout_request_id', $reference)
                ->first()
            : null;

        $secret = $payment?->invoice?->company
            ? $paystack->secretKey($payment->invoice->company)
            : '';

        if (! app(WebhookSignature::class)->paystackIsValid($request->getContent(), $request->header('x-paystack-signature'), $secret)) {
            Log::warning('Paystack commerce webhook rejected: invalid signature');

            return response()->json(['status' => false], 401);
        }

        $result = $paystack->handleWebhook($payload);
        $payment = $result['payment'] ?? null;

        if ($payment instanceof InvoicePayment) {
            $invoice = $payment->invoice;
            $notes = is_array($invoice?->notes) ? $invoice->notes : [];
            $flowId = (int) ($notes['flow_id'] ?? 0);
            $contactId = (int) ($notes['contact_id'] ?? 0);
            $status = $payment->status === 'success' ? 'success' : 'failed';

            if ($flowId > 0 && $contactId > 0) {
                ResumeFlowFromPayment::dispatch($flowId, $contactId, $status)->onQueue('flows');
            }
        }

        return response()->json(['status' => 'ok']);
    }
}
