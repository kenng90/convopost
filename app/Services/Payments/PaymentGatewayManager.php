<?php

namespace App\Services\Payments;

use App\Contracts\PaymentGateway;
use App\Models\Company;
use App\Services\MpesaService;
use Illuminate\Support\Facades\Log;
use Modules\Invoice\Models\Invoice;
use Modules\Invoice\Models\InvoicePayment;

class PaymentGatewayManager
{
    public function __construct(
        protected PaystackCommerceService $paystack,
    ) {
    }

    /**
     * @return list<PaymentGateway>
     */
    public function gateways(): array
    {
        return [
            $this->paystack,
            new class implements PaymentGateway
            {
                public function key(): string
                {
                    return 'mpesa';
                }

                public function label(): string
                {
                    return 'M-Pesa STK Push';
                }

                public function isConfigured(Company $company): bool
                {
                    return (new MpesaService($company))->isConfigured();
                }

                public function configErrors(Company $company): array
                {
                    return (new MpesaService($company))->getConfigErrors();
                }

                public function initiate(Company $company, Invoice $invoice, array $options = []): array
                {
                    $mpesa = new MpesaService($company);
                    if (! $mpesa->isConfigured()) {
                        return ['success' => false, 'message' => implode('; ', $mpesa->getConfigErrors())];
                    }

                    $phone = $options['phone'] ?? $invoice->customer_phone;
                    $callback = $options['callback_url'] ?? url('/api/invoice/payment/callback');
                    $amount = isset($options['amount']) ? (float) $options['amount'] : (float) $invoice->amount;

                    $result = $mpesa->initiateStk(
                        (string) $phone,
                        $amount,
                        (string) ($options['account_reference'] ?? $invoice->invoice_number),
                        (string) ($options['description'] ?? 'Invoice payment'),
                        $callback
                    );

                    if (! ($result['success'] ?? false)) {
                        return [
                            'success' => false,
                            'message' => $result['message'] ?? 'STK push failed',
                        ];
                    }

                    $payment = InvoicePayment::create([
                        'invoice_id' => $invoice->id,
                        'payment_method' => 'mpesa',
                        'paid_via' => 'mpesa',
                        'amount' => $amount,
                        'status' => 'pending',
                        'mpesa_checkout_request_id' => $result['checkout_request_id'] ?? null,
                        'mpesa_merchant_request_id' => $result['merchant_request_id'] ?? null,
                        'initiated_at' => now(),
                        'response_data' => $result,
                    ]);

                    return [
                        'success' => true,
                        'payment' => $payment,
                        'reference' => $payment->mpesa_checkout_request_id,
                        'message' => 'STK push sent',
                    ];
                }

                public function handleWebhook(array $payload): array
                {
                    return ['success' => false, 'message' => 'Use M-Pesa callback endpoints'];
                }
            },
        ];
    }

    public function get(string $key): ?PaymentGateway
    {
        foreach ($this->gateways() as $gateway) {
            if ($gateway->key() === $key) {
                return $gateway;
            }
        }

        return null;
    }

    /**
     * @return list<array{key: string, label: string, configured: bool}>
     */
    public function availableForCompany(Company $company): array
    {
        return array_values(array_map(fn (PaymentGateway $gateway) => [
            'key' => $gateway->key(),
            'label' => $gateway->label(),
            'configured' => $gateway->isConfigured($company),
        ], $this->gateways()));
    }

    public function preferredForCompany(Company $company, ?string $requested = null): ?PaymentGateway
    {
        if ($requested) {
            $gateway = $this->get($requested);
            if ($gateway && $gateway->isConfigured($company)) {
                return $gateway;
            }
        }

        $preferred = trim((string) $company->getConfig('preferred_payment_gateway', 'paystack'));
        $gateway = $this->get($preferred !== '' ? $preferred : 'paystack');
        if ($gateway && $gateway->isConfigured($company)) {
            return $gateway;
        }

        foreach ($this->gateways() as $candidate) {
            if ($candidate->isConfigured($company)) {
                return $candidate;
            }
        }

        Log::info('No payment gateway configured', ['company_id' => $company->id]);

        return null;
    }
}
