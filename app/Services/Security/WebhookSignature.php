<?php

namespace App\Services\Security;

class WebhookSignature
{
    public function paystackIsValid(string $payload, ?string $signature, string $secret): bool
    {
        if ($secret === '' || $signature === null || $signature === '') {
            return false;
        }

        return hash_equals(hash_hmac('sha512', $payload, $secret), $signature);
    }

    public function metaHubIsValid(string $payload, ?string $signatureHeader, string $secret): bool
    {
        if ($secret === '' || $signatureHeader === null || $signatureHeader === '') {
            return false;
        }

        $provided = str_starts_with($signatureHeader, 'sha256=')
            ? substr($signatureHeader, 7)
            : $signatureHeader;

        return hash_equals(hash_hmac('sha256', $payload, $secret), $provided);
    }

    public function stripeTimestampIsFresh(int $timestamp, int $toleranceSeconds = 300): bool
    {
        return abs(time() - $timestamp) <= $toleranceSeconds;
    }
}
