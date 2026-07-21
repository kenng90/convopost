<?php

namespace App\Services\Payments;

use App\Contracts\PaymentGateway;
use App\Models\Company;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Modules\Invoice\Models\Invoice;
use Modules\Invoice\Models\InvoicePayment;

class PaystackCommerceService implements PaymentGateway
{
    public function key(): string
    {
        return 'paystack';
    }

    public function label(): string
    {
        return 'Paystack (Card / Mobile Money)';
    }

    public function isConfigured(Company $company): bool
    {
        return $this->configErrors($company) === [];
    }

    public function configErrors(Company $company): array
    {
        $errors = [];

        if (! trim((string) $company->getConfig('paystack_commerce_secret_key', ''))) {
            $errors[] = 'Paystack secret key is missing';
        }

        if (! trim((string) $company->getConfig('paystack_commerce_public_key', ''))) {
            $errors[] = 'Paystack public key is missing';
        }

        return $errors;
    }

    public function publicKey(Company $company): string
    {
        return trim((string) $company->getConfig('paystack_commerce_public_key', ''));
    }

    public function secretKey(Company $company): string
    {
        return trim((string) $company->getConfig('paystack_commerce_secret_key', ''));
    }

    public function initiate(Company $company, Invoice $invoice, array $options = []): array
    {
        if (! $this->isConfigured($company)) {
            return [
                'success' => false,
                'message' => implode('; ', $this->configErrors($company)),
            ];
        }

        $email = $this->resolveCustomerEmail($invoice, $options);

        $amount = isset($options['amount']) ? (float) $options['amount'] : (float) $invoice->amount;
        $amountMinor = (int) round($amount * 100);
        if ($amountMinor < 100) {
            return ['success' => false, 'message' => 'Amount too small for Paystack'];
        }

        $reference = 'INV-'.$invoice->id.'-'.Str::upper(Str::random(10));
        $callbackUrl = $options['callback_url']
            ?? url('/api/invoice/paystack/callback');

        $response = Http::withToken($this->secretKey($company))
            ->acceptJson()
            ->post('https://api.paystack.co/transaction/initialize', [
                'email' => $email,
                'amount' => $amountMinor,
                'currency' => strtoupper((string) ($invoice->currency ?: 'KES')),
                'reference' => $reference,
                'callback_url' => $callbackUrl,
                'metadata' => [
                    'invoice_id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'company_id' => $company->id,
                    'customer_phone' => $invoice->customer_phone,
                ],
            ]);

        if (! $response->successful() || ! ($response->json('status') === true)) {
            Log::warning('Paystack initialize failed', [
                'invoice_id' => $invoice->id,
                'body' => $response->json(),
            ]);

            return [
                'success' => false,
                'message' => $response->json('message') ?? 'Unable to start Paystack checkout',
            ];
        }

        $data = $response->json('data') ?? [];

        $payment = InvoicePayment::create([
            'invoice_id' => $invoice->id,
            'payment_method' => 'paystack',
            'paid_via' => 'paystack',
            'amount' => $amount,
            'status' => 'pending',
            'gateway_reference' => $data['reference'] ?? $reference,
            'gateway_access_code' => $data['access_code'] ?? null,
            'authorization_url' => $data['authorization_url'] ?? null,
            'response_data' => $data,
            'initiated_at' => now(),
        ]);

        return [
            'success' => true,
            'payment' => $payment,
            'authorization_url' => $payment->authorization_url,
            'reference' => $payment->gateway_reference,
            'access_code' => $payment->gateway_access_code,
            'public_key' => $this->publicKey($company),
        ];
    }

    public function verify(Company $company, string $reference): array
    {
        $response = Http::withToken($this->secretKey($company))
            ->acceptJson()
            ->get('https://api.paystack.co/transaction/verify/'.urlencode($reference));

        if (! $response->successful() || ! ($response->json('status') === true)) {
            return ['success' => false, 'message' => $response->json('message') ?? 'Verification failed'];
        }

        return [
            'success' => true,
            'data' => $response->json('data') ?? [],
        ];
    }

    public function handleWebhook(array $payload): array
    {
        $event = (string) ($payload['event'] ?? '');
        $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];
        $reference = (string) ($data['reference'] ?? '');

        if ($reference === '') {
            return ['success' => false, 'message' => 'Missing reference'];
        }

        $payment = InvoicePayment::query()
            ->where('gateway_reference', $reference)
            ->orWhere('mpesa_checkout_request_id', $reference)
            ->first();

        if (! $payment) {
            return ['success' => false, 'message' => 'Payment not found'];
        }

        if ($event === 'charge.success' || ($data['status'] ?? '') === 'success') {
            $payment->markAsSuccess($data['receipt_number'] ?? $reference);
            $payment->update([
                'paid_via' => 'paystack',
                'result_description' => 'Paystack charge success',
                'response_data' => array_merge((array) $payment->response_data, $data),
            ]);

            return ['success' => true, 'payment' => $payment->fresh()];
        }

        if (in_array($event, ['charge.failed', 'paymentrequest.failed'], true)
            || in_array(($data['status'] ?? ''), ['failed', 'abandoned'], true)) {
            $payment->update([
                'status' => 'failed',
                'result_description' => (string) ($data['gateway_response'] ?? 'Payment failed'),
                'completed_at' => now(),
                'response_data' => array_merge((array) $payment->response_data, $data),
            ]);

            return ['success' => true, 'payment' => $payment->fresh()];
        }

        return ['success' => true, 'payment' => $payment, 'message' => 'Ignored event '.$event];
    }

    /**
     * Paystack requires a valid email. Catalog/checkout invoices often have none,
     * so build a placeholder Paystack will accept (never use .local / .test).
     */
    private function resolveCustomerEmail(Invoice $invoice, array $options = []): string
    {
        $candidates = [
            $options['email'] ?? null,
            $invoice->customer_email,
        ];

        foreach ($candidates as $candidate) {
            $email = is_string($candidate) ? trim($candidate) : '';
            if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return $email;
            }
        }

        return $this->fallbackEmail($invoice);
    }

    private function fallbackEmail(Invoice $invoice): string
    {
        $phone = preg_replace('/\D+/', '', (string) $invoice->customer_phone) ?: 'customer';

        // Stable public domain — Paystack rejects .local/.test and may reject ephemeral hosts.
        return $phone.'@pay.convoconnect.com';
    }
}
