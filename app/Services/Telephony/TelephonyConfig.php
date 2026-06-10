<?php

namespace App\Services\Telephony;

use App\Models\Company;

class TelephonyConfig
{
    public function __construct(
        public Company $company,
        public string $provider,
    ) {
    }

    public static function forCompany(Company $company): self
    {
        $provider = TelephonyProvider::normalize($company->getConfig('telephony_provider', TelephonyProvider::TELNYX));

        return new self($company, $provider);
    }

    public function isTelnyx(): bool
    {
        return $this->provider === TelephonyProvider::TELNYX;
    }

    public function isTwilio(): bool
    {
        return $this->provider === TelephonyProvider::TWILIO;
    }

    public function stringConfig(string $key, string $default = ''): string
    {
        $value = $this->company->getConfig($key, $default);

        return trim(is_string($value) ? $value : (string) ($value ?? ''));
    }

    public function smsReady(): bool
    {
        if ($this->isTelnyx()) {
            return $this->stringConfig('TELNYX_API_KEY') !== ''
                && $this->stringConfig('TELNYX_FROM_NUMBER') !== '';
        }

        return $this->stringConfig('TWILIO_ACCOUNT_SID') !== ''
            && $this->stringConfig('TWILIO_AUTH_TOKEN') !== ''
            && $this->stringConfig('TWILIO_FROM_NUMBER') !== '';
    }

    public function voiceReady(): bool
    {
        if ($this->isTelnyx()) {
            return $this->stringConfig('TELNYX_API_KEY') !== ''
                && $this->stringConfig('TELNYX_CONNECTION_ID') !== '';
        }

        return $this->stringConfig('TWILIO_ACCOUNT_SID') !== ''
            && $this->stringConfig('TWILIO_AUTH_TOKEN') !== '';
    }
}
