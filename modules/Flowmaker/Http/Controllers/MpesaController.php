<?php

namespace Modules\Flowmaker\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Services\MpesaCallbackValidator;
use App\Services\MpesaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Modules\Flowmaker\Jobs\ResumeFlowFromMpesa;
use Modules\Flowmaker\Models\Contact;
use Modules\Flowmaker\Models\ContactState;
use Modules\Flowmaker\Models\Flow;
use Modules\Invoice\Models\InvoicePayment;

class MpesaController extends Controller
{
    /**
     * Handle the STK Push callback from Safaricom for flow-initiated payments.
     */
    public function stkCallback(Request $request)
    {
        Log::info('MPesa STK Callback received', ['payload' => $request->all()]);

        try {
            $body = $request->input('Body.stkCallback') ?? $request->input('Body');

            if (! $body) {
                Log::error('MPesa STK Callback: invalid payload structure', ['raw' => $request->all()]);

                return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
            }

            MpesaCallbackValidator::logCallback($body, 'received');

            $validation = MpesaCallbackValidator::validateStkPushCallback($body);
            if (! $validation['valid']) {
                Log::error('MPesa STK Callback: invalid structure', ['errors' => $validation['errors']]);

                return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
            }

            $checkoutRequestId = $body['CheckoutRequestID'] ?? null;
            $resultCode = $body['ResultCode'] ?? null;
            $resultDesc = $body['ResultDesc'] ?? '';

            if (! $checkoutRequestId) {
                Log::error('MPesa STK Callback: missing CheckoutRequestID');

                return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
            }

            if (! MpesaCallbackValidator::isNotDuplicate($checkoutRequestId)) {
                Log::warning('MPesa STK Callback: duplicate callback ignored', [
                    'checkoutRequestId' => $checkoutRequestId,
                ]);

                return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
            }

            $payment = InvoicePayment::findByCheckoutRequestId($checkoutRequestId);
            $checkoutState = ContactState::where('state', 'mpesa_checkout_request_id')
                ->where('value', $checkoutRequestId)
                ->first();

            if (! $payment && ! $checkoutState) {
                Log::warning('MPesa STK Callback: no matching payment or contact state', [
                    'checkoutRequestId' => $checkoutRequestId,
                ]);

                return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
            }

            if ($payment) {
                $this->updatePaymentFromCallback($payment, $body);
            }

            if (! $checkoutState) {
                MpesaCallbackValidator::logCallback($body, 'processed');

                return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
            }

            $contactId = $checkoutState->contact_id;
            $flowId = $checkoutState->flow_id;

            $contact = Contact::find($contactId);
            $flow = Flow::withoutGlobalScopes()->find($flowId);

            if (! $contact || ! $flow) {
                Log::error('MPesa STK Callback: contact or flow not found', [
                    'contactId' => $contactId,
                    'flowId' => $flowId,
                ]);

                return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
            }

            $mpesaService = new MpesaService(Company::find($contact->company_id));
            $callbackData = $mpesaService->parseCallback($body);

            $contact->setContactState($flowId, 'mpesa_result_code', (string) $resultCode);
            $contact->setContactState($flowId, 'mpesa_result_desc', $resultDesc);
            $contact->setContactState($flowId, 'mpesa_status', $resultCode == 0 ? 'success' : 'failed');

            if ($callbackData['receipt_number']) {
                $contact->setContactState($flowId, 'mpesa_receipt', $callbackData['receipt_number']);
            }

            if ($callbackData['amount_paid']) {
                $contact->setContactState($flowId, 'mpesa_amount_paid', (string) $callbackData['amount_paid']);
            }

            Log::info('MPesa STK Callback: state updated, resuming flow', [
                'resultCode' => $resultCode,
                'receiptNumber' => $callbackData['receipt_number'],
                'paymentId' => $payment?->id,
            ]);

            $invoice = $payment?->invoice;
            $deferFailure = $resultCode != 0
                && $invoice
                && app(\App\Services\Collections\CollectionEngine::class)->shouldDeferFlowFailure($invoice);

            if (! $deferFailure) {
                ResumeFlowFromMpesa::dispatch($flow->id, $contact->id)->onQueue('flows');
            }

            MpesaCallbackValidator::logCallback($body, 'processed');
        } catch (\Exception $e) {
            Log::error('MPesa STK Callback: exception', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }

        return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
    }

    private function updatePaymentFromCallback(InvoicePayment $payment, array $body): void
    {
        $payment->loadMissing('invoice');
        $company = Company::find($payment->invoice->company_id);

        if (! $company) {
            Log::error('MPesa STK Callback: company not found for payment', ['paymentId' => $payment->id]);

            return;
        }

        $mpesaService = new MpesaService($company);
        $callbackData = $mpesaService->parseCallback($body);

        $payment->storeResponseData($body);

        if ($callbackData['success']) {
            $payment->markAsSuccess($callbackData['receipt_number']);
            Log::info('Flow MPesa payment successful', [
                'payment_id' => $payment->id,
                'invoice_id' => $payment->invoice_id,
                'receipt' => $callbackData['receipt_number'],
            ]);
        } else {
            $payment->markAsFailed($callbackData['result_description']);
            Log::warning('Flow MPesa payment failed', [
                'payment_id' => $payment->id,
                'invoice_id' => $payment->invoice_id,
                'result_code' => $callbackData['result_code'],
                'result_description' => $callbackData['result_description'],
            ]);
        }
    }
}
