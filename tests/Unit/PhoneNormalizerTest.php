<?php

namespace Tests\Unit;

use App\Services\Telephony\PhoneNormalizer;
use PHPUnit\Framework\TestCase;

class PhoneNormalizerTest extends TestCase
{
    public function test_normalizes_kenyan_local_number_for_host_pinnacle(): void
    {
        $normalizer = new PhoneNormalizer('254');

        $this->assertSame('254712345678', $normalizer->forHostPinnacle('0712345678'));
    }

    public function test_normalizes_e164_number_for_host_pinnacle(): void
    {
        $normalizer = new PhoneNormalizer('254');

        $this->assertSame('254712345678', $normalizer->forHostPinnacle('+254712345678'));
    }

    public function test_normalizes_to_e164_for_twilio(): void
    {
        $normalizer = new PhoneNormalizer('254');

        $this->assertSame('+254712345678', $normalizer->forE164('0712345678'));
    }
}
