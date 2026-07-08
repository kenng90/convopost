<?php

namespace App\Services\Telephony;

class PhoneNormalizer
{
    public function __construct(
        private readonly string $defaultCountryCode = '254',
    ) {
    }

    public static function make(?string $defaultCountryCode = null): self
    {
        return new self($defaultCountryCode ?? config('hostpinnacle.default_country_code', '254'));
    }

    /**
     * HostPinnacle expects international format without a leading plus (e.g. 254712345678).
     */
    public function forHostPinnacle(?string $phone): ?string
    {
        $digits = $this->digitsOnly($phone);
        if ($digits === '') {
            return null;
        }

        if (str_starts_with($digits, '0')) {
            $digits = $this->defaultCountryCode.substr($digits, 1);
        }

        if (strlen($digits) <= 10 && ! str_starts_with($digits, $this->defaultCountryCode)) {
            $digits = $this->defaultCountryCode.$digits;
        }

        return $digits;
    }

    /**
     * Twilio/Telnyx expect E.164 with a leading plus.
     */
    public function forE164(?string $phone): ?string
    {
        $hostPinnacle = $this->forHostPinnacle($phone);
        if ($hostPinnacle === null) {
            return null;
        }

        return '+'.$hostPinnacle;
    }

    private function digitsOnly(?string $phone): string
    {
        return preg_replace('/\D+/', '', trim((string) $phone)) ?? '';
    }
}
