<?php

namespace Tests\Unit;

use Modules\Wpbox\Support\PhoneNormalizer;
use Tests\TestCase;

class PhoneNormalizerTest extends TestCase
{
    public function test_normalizes_kenyan_local_number_with_leading_zero(): void
    {
        $normalizer = new PhoneNormalizer;

        $this->assertSame('254716212345', $normalizer->normalize('0716212345', '254'));
    }

    public function test_normalizes_international_number_without_plus(): void
    {
        $normalizer = new PhoneNormalizer;

        $this->assertSame('254716212345', $normalizer->normalize('254716212345', '254'));
    }

    public function test_normalizes_nine_digit_kenyan_mobile(): void
    {
        $normalizer = new PhoneNormalizer;

        $this->assertSame('254716212345', $normalizer->normalize('716212345', '254'));
    }

    public function test_lookup_candidates_include_legacy_local_formats(): void
    {
        $normalizer = new PhoneNormalizer;

        $candidates = $normalizer->lookupCandidates('254716212345', '254');

        $this->assertContains('254716212345', $candidates);
        $this->assertContains('0716212345', $candidates);
        $this->assertContains('716212345', $candidates);
    }
}
