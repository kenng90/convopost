<?php

namespace App\Services\Telephony\Sms;

use App\Models\Company;
use App\Services\Telephony\TelephonyConfig;

class SmsConfig
{
    public function __construct(
        public Company $company,
        public string $provider,
    ) {
    }

    public static function forCompany(Company $company): self
    {
        $provider = app(SmsAvailability::class)->resolveProvider($company);

        return new self($company, $provider);
    }

    public function isHostPinnacle(): bool
    {
        return $this->provider === SmsProvider::HOSTPINNACLE;
    }

    public function isTwilio(): bool
    {
        return $this->provider === SmsProvider::TWILIO;
    }

    public function isTelnyx(): bool
    {
        return $this->provider === SmsProvider::TELNYX;
    }

    public function isUnconfigured(): bool
    {
        return $this->provider === SmsProvider::UNCONFIGURED;
    }

    public function stringConfig(string $key, string $default = ''): string
    {
        $value = $this->company->getConfig($key, $default);

        return trim(is_string($value) ? $value : (string) ($value ?? ''));
    }

    public function smsReady(): bool
    {
        if ($this->isHostPinnacle()) {
            if (! config('hostpinnacle.enabled', false)) {
                return false;
            }

            $availability = app(SmsAvailability::class);

            return $availability->hostPinnacleProvisioned($this->company)
                && $availability->senderIdApproved($this->company);
        }

        if ($this->isTelnyx()) {
            return app(SmsAvailability::class)->telnyxConfigured($this->company);
        }

        if ($this->isTwilio()) {
            return app(SmsAvailability::class)->twilioConfigured($this->company);
        }

        return false;
    }

    public function telephonyConfig(): TelephonyConfig
    {
        return TelephonyConfig::forCompany($this->company);
    }
}
