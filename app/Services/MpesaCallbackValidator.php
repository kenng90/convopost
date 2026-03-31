<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class MpesaCallbackValidator
{
    /**
     * Validate M-Pesa STK Push callback
     * 
     * Safaricom doesn't provide signature in STK Push callbacks,
     * but we can validate structure and expected fields
     */
    public static function validateStkPushCallback(array $body): array
    {
        $errors = [];

        // Check required fields for STK Push callback
        $requiredFields = ['CheckoutRequestID', 'ResultCode', 'ResultDesc'];
        
        foreach ($requiredFields as $field) {
            if (!isset($body[$field])) {
                $errors[] = "Missing required field: {$field}";
            }
        }

        // Validate CheckoutRequestID format (should be non-empty string)
        if (isset($body['CheckoutRequestID']) && empty($body['CheckoutRequestID'])) {
            $errors[] = 'CheckoutRequestID cannot be empty';
        }

        // Validate ResultCode is numeric
        if (isset($body['ResultCode']) && !is_numeric($body['ResultCode'])) {
            $errors[] = 'ResultCode must be numeric';
        }

        // For successful callbacks, check callback metadata
        if (isset($body['ResultCode']) && $body['ResultCode'] == 0) {
            if (!isset($body['CallbackMetadata']) || !is_array($body['CallbackMetadata'])) {
                $errors[] = 'CallbackMetadata missing for successful callback';
            }

            if (isset($body['CallbackMetadata']['Item']) && is_array($body['CallbackMetadata']['Item'])) {
                $hasReceipt = false;
                $hasAmount = false;

                foreach ($body['CallbackMetadata']['Item'] as $item) {
                    if (($item['Name'] ?? '') === 'MpesaReceiptNumber') {
                        $hasReceipt = true;
                    }
                    if (($item['Name'] ?? '') === 'Amount') {
                        $hasAmount = true;
                    }
                }

                if (!$hasReceipt) {
                    $errors[] = 'Missing MpesaReceiptNumber in callback';
                }
                if (!$hasAmount) {
                    $errors[] = 'Missing Amount in callback';
                }
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    /**
     * Validate callback is not a replay/duplicate
     * Check if we've already processed this CheckoutRequestID
     */
    public static function isNotDuplicate(string $checkoutRequestId): bool
    {
        $cacheKey = "mpesa_callback_{$checkoutRequestId}";
        
        // Check if we already processed this callback (within last 24 hours)
        if (cache()->has($cacheKey)) {
            Log::warning('Duplicate M-Pesa callback detected', [
                'checkout_request_id' => $checkoutRequestId,
            ]);
            return false;
        }

        // Mark this callback as processed for 24 hours
        cache()->put($cacheKey, true, now()->addHours(24));
        return true;
    }

    /**
     * Validate callback came from expected IP ranges (if needed)
     * Safaricom's IP ranges can be configured
     */
    public static function validateIpRange(string $ip, array $allowedIps = []): bool
    {
        // If no IPs configured, skip validation
        if (empty($allowedIps)) {
            return true;
        }

        $ipAllowed = false;
        foreach ($allowedIps as $allowedIp) {
            if (filter_var($ip, FILTER_VALIDATE_IP, ['flags' => FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE])) {
                if ($ip === $allowedIp || fnmatch($allowedIp, $ip)) {
                    $ipAllowed = true;
                    break;
                }
            }
        }

        if (!$ipAllowed) {
            Log::warning('M-Pesa callback from unauthorized IP', [
                'ip' => $ip,
                'allowed_ips' => $allowedIps,
            ]);
        }

        return $ipAllowed;
    }

    /**
     * Log callback for audit trail
     */
    public static function logCallback(array $body, string $status = 'received', ?array $errors = null): void
    {
        Log::info('M-Pesa callback received', [
            'checkout_request_id' => $body['CheckoutRequestID'] ?? 'unknown',
            'result_code' => $body['ResultCode'] ?? 'unknown',
            'status' => $status,
            'errors' => $errors,
        ]);
    }
}
