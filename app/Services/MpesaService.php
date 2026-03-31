<?php

namespace App\Services;

use App\Models\Company;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MpesaService
{
    protected $company;
    protected $environment;
    protected $baseUrl;
    protected $consumerKey;
    protected $consumerSecret;
    protected $passkey;
    protected $shortCode;

    /**
     * Initialize the M-Pesa service with company credentials
     */
    public function __construct(Company $company)
    {
        $this->company = $company;
        $this->environment = $company->getConfig('mpesa_environment', 'sandbox');
        $this->consumerKey = $company->getConfig('mpesa_consumer_key', '');
        $this->consumerSecret = $company->getConfig('mpesa_consumer_secret', '');
        $this->passkey = $company->getConfig('mpesa_passkey', '');
        $this->shortCode = $company->getConfig('mpesa_short_code', '');

        $this->baseUrl = $this->environment === 'production'
            ? 'https://api.safaricom.co.ke'
            : 'https://sandbox.safaricom.co.ke';
    }

    /**
     * Validate M-Pesa configuration
     */
    public function isConfigured(): bool
    {
        return !empty($this->consumerKey)
            && !empty($this->consumerSecret)
            && !empty($this->passkey)
            && !empty($this->shortCode);
    }

    /**
     * Get configuration errors
     */
    public function getConfigErrors(): array
    {
        $errors = [];
        if (empty($this->consumerKey)) {
            $errors[] = 'M-Pesa Consumer Key is not configured';
        }
        if (empty($this->consumerSecret)) {
            $errors[] = 'M-Pesa Consumer Secret is not configured';
        }
        if (empty($this->passkey)) {
            $errors[] = 'M-Pesa Passkey is not configured';
        }
        if (empty($this->shortCode)) {
            $errors[] = 'M-Pesa Short Code is not configured';
        }
        return $errors;
    }

    /**
     * Generate OAuth access token from Safaricom
     */
    public function getAccessToken(): ?string
    {
        try {
            $response = Http::timeout(30)
                ->withBasicAuth($this->consumerKey, $this->consumerSecret)
                ->get("{$this->baseUrl}/oauth/v1/generate", ['grant_type' => 'client_credentials']);

            if (!$response->successful()) {
                Log::error('M-Pesa: Failed to get access token', [
                    'status' => $response->status(),
                    'response' => $response->body(),
                    'environment' => $this->environment,
                ]);
                return null;
            }

            return $response->json('access_token');
        } catch (\Exception $e) {
            Log::error('M-Pesa: Exception getting access token', [
                'error' => $e->getMessage(),
                'environment' => $this->environment,
            ]);
            return null;
        }
    }

    /**
     * Format phone number to Safaricom's required format (254XXXXXXXXX)
     */
    public function formatPhoneNumber(string $phone): string
    {
        // Remove any + prefix
        $phone = ltrim($phone, '+');

        // If starts with 0, replace with 254
        if (str_starts_with($phone, '0')) {
            $phone = '254' . substr($phone, 1);
        }

        // If starts with 7 or 1 (local format without country code)
        if (strlen($phone) === 9 && (str_starts_with($phone, '7') || str_starts_with($phone, '1'))) {
            $phone = '254' . $phone;
        }

        return $phone;
    }

    /**
     * Initiate STK Push payment request
     *
     * @param string $phone Customer phone number
     * @param float $amount Amount to charge
     * @param string $accountReference Account reference (max 12 chars)
     * @param string $transactionDesc Transaction description (max 13 chars)
     * @param string $callbackUrl Callback URL for payment status
     * 
     * @return array ['success' => bool, 'checkout_request_id' => string, 'merchant_request_id' => string, 'error' => string]
     */
    public function initiateStk(
        string $phone,
        float $amount,
        string $accountReference = 'Payment',
        string $transactionDesc = 'Payment',
        ?string $callbackUrl = null
    ): array
    {
        if (!$this->isConfigured()) {
            Log::error('M-Pesa: Service not configured', [
                'company_id' => $this->company->id,
                'errors' => $this->getConfigErrors(),
            ]);
            return [
                'success' => false,
                'error' => 'M-Pesa is not properly configured',
            ];
        }

        // Format phone number
        $phone = $this->formatPhoneNumber($phone);

        // Get access token
        $accessToken = $this->getAccessToken();
        if (!$accessToken) {
            return [
                'success' => false,
                'error' => 'Failed to authenticate with M-Pesa',
            ];
        }

        // Set default callback URL if not provided
        if (!$callbackUrl) {
            $callbackUrl = config('app.url') . '/api/mpesa/callback';
        }

        try {
            // Build STK Push payload
            $timestamp = now()->format('YmdHis');
            $password = base64_encode($this->shortCode . $this->passkey . $timestamp);

            $payload = [
                'BusinessShortCode' => $this->shortCode,
                'Password' => $password,
                'Timestamp' => $timestamp,
                'TransactionType' => 'CustomerPayBillOnline',
                'Amount' => (int) $amount,
                'PartyA' => $phone,
                'PartyB' => $this->shortCode,
                'PhoneNumber' => $phone,
                'CallBackURL' => $callbackUrl,
                'AccountReference' => substr($accountReference, 0, 12),
                'TransactionDesc' => substr($transactionDesc, 0, 13),
            ];

            Log::info('M-Pesa: Initiating STK Push', [
                'phone' => $phone,
                'amount' => $amount,
                'account_reference' => $accountReference,
                'environment' => $this->environment,
            ]);

            // Send STK Push request
            $response = Http::timeout(30)
                ->withToken($accessToken)
                ->post("{$this->baseUrl}/mpesa/stkpush/v1/processrequest", $payload);

            $data = $response->json();

            Log::info('M-Pesa: STK Push response', [
                'response_code' => $data['ResponseCode'] ?? null,
                'checkout_request_id' => $data['CheckoutRequestID'] ?? null,
            ]);

            // Check for errors in response
            if (!$response->successful() || isset($data['errorCode'])) {
                $error = $data['errorMessage'] ?? $data['ResultDesc'] ?? 'STK push failed';
                Log::error('M-Pesa: STK Push failed', [
                    'error' => $error,
                    'response' => $data,
                ]);
                return [
                    'success' => false,
                    'error' => $error,
                ];
            }

            $checkoutRequestId = $data['CheckoutRequestID'] ?? null;
            if (!$checkoutRequestId) {
                Log::error('M-Pesa: No CheckoutRequestID in response', ['response' => $data]);
                return [
                    'success' => false,
                    'error' => 'Invalid response from M-Pesa',
                ];
            }

            return [
                'success' => true,
                'checkout_request_id' => $checkoutRequestId,
                'merchant_request_id' => $data['MerchantRequestID'] ?? '',
                'response_code' => $data['ResponseCode'] ?? null,
                'response_description' => $data['ResponseDescription'] ?? null,
            ];
        } catch (\Exception $e) {
            Log::error('M-Pesa: Exception during STK Push', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Parse callback response from Safaricom
     */
    public function parseCallback(array $body): array
    {
        $checkoutRequestId = $body['CheckoutRequestID'] ?? null;
        $resultCode = $body['ResultCode'] ?? null;
        $resultDesc = $body['ResultDesc'] ?? '';
        $receiptNumber = null;
        $amount = null;

        // Extract receipt and amount from CallbackMetadata if present
        if (isset($body['CallbackMetadata']['Item']) && is_array($body['CallbackMetadata']['Item'])) {
            foreach ($body['CallbackMetadata']['Item'] as $item) {
                if (($item['Name'] ?? '') === 'MpesaReceiptNumber') {
                    $receiptNumber = $item['Value'] ?? null;
                } elseif (($item['Name'] ?? '') === 'Amount') {
                    $amount = $item['Value'] ?? null;
                }
            }
        }

        return [
            'checkout_request_id' => $checkoutRequestId,
            'result_code' => (string) $resultCode,
            'result_description' => $resultDesc,
            'receipt_number' => $receiptNumber,
            'amount_paid' => $amount,
            'success' => (string) $resultCode === '0',
        ];
    }

    /**
     * Get company M-Pesa configuration
     */
    public function getConfig(): array
    {
        return [
            'environment' => $this->environment,
            'short_code' => $this->shortCode,
            'is_configured' => $this->isConfigured(),
            'errors' => $this->getConfigErrors(),
        ];
    }
}
