<?php

namespace Tests\Unit;

use App\Services\Security\WebhookSignature;
use Tests\TestCase;

class WebhookSignatureTest extends TestCase
{
    public function test_paystack_signature_accepts_valid_hmac(): void
    {
        $payload = '{"event":"charge.success"}';
        $secret = 'test-secret';
        $signature = hash_hmac('sha512', $payload, $secret);

        $this->assertTrue((new WebhookSignature)->paystackIsValid($payload, $signature, $secret));
    }

    public function test_paystack_signature_rejects_invalid_hmac(): void
    {
        $this->assertFalse((new WebhookSignature)->paystackIsValid('{"event":"charge.success"}', 'deadbeef', 'test-secret'));
        $this->assertFalse((new WebhookSignature)->paystackIsValid('{"event":"charge.success"}', null, 'test-secret'));
        $this->assertFalse((new WebhookSignature)->paystackIsValid('{"event":"charge.success"}', 'abc', ''));
    }

    public function test_meta_hub_signature_accepts_valid_hmac(): void
    {
        $payload = '{"object":"whatsapp_business_account"}';
        $secret = 'meta-secret';
        $header = 'sha256='.hash_hmac('sha256', $payload, $secret);

        $this->assertTrue((new WebhookSignature)->metaHubIsValid($payload, $header, $secret));
    }

    public function test_meta_hub_signature_rejects_invalid_hmac(): void
    {
        $this->assertFalse((new WebhookSignature)->metaHubIsValid('payload', 'sha256=nope', 'meta-secret'));
    }

    public function test_stripe_timestamp_freshness(): void
    {
        $verifier = new WebhookSignature;

        $this->assertTrue($verifier->stripeTimestampIsFresh(time()));
        $this->assertFalse($verifier->stripeTimestampIsFresh(time() - 301));
    }
}
