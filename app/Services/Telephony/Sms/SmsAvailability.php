<?php

namespace App\Services\Telephony\Sms;

use App\Models\Company;
use App\Services\HostPinnacle\HostPinnacleCredentials;
use App\Support\ConvoConnectBrand;

class SmsAvailability
{
    public function resolveProvider(Company $company): string
    {
        if (config('hostpinnacle.enabled', false) && $this->hostPinnacleProvisioned($company)) {
            return SmsProvider::HOSTPINNACLE;
        }

        if ($this->twilioConfigured($company)) {
            return SmsProvider::TWILIO;
        }

        if ($this->telnyxConfigured($company)) {
            return SmsProvider::TELNYX;
        }

        return SmsProvider::UNCONFIGURED;
    }

    public function isReady(Company $company): bool
    {
        return SmsConfig::forCompany($company)->smsReady();
    }

    public function statusMessage(Company $company): string
    {
        $provider = $this->resolveProvider($company);

        if ($provider === SmsProvider::HOSTPINNACLE) {
            $brand = ConvoConnectBrand::name();

            if ($this->senderIdApproved($company)) {
                return __(':brand SMS is active.', ['brand' => $brand]);
            }

            $status = trim((string) $company->getConfig('HOSTPINNACLE_SENDER_STATUS', 'pending_approval'));

            return match ($status) {
                'request_failed' => __(':brand SMS setup failed. Please contact support.', ['brand' => $brand]),
                'pending_approval' => __(':brand SMS is being set up. Your Sender ID is pending approval (usually 24–48 hours).', ['brand' => $brand]),
                default => __(':brand SMS is not ready yet. Please contact support.', ['brand' => $brand]),
            };
        }

        if ($provider === SmsProvider::TWILIO) {
            return __('SMS is sent via your connected Twilio account.');
        }

        if ($provider === SmsProvider::TELNYX) {
            return __('SMS is sent via your connected Telnyx account.');
        }

        if (config('hostpinnacle.enabled', false)) {
            return __(':brand SMS is being provisioned. You can also connect your own Twilio account in App Settings.', [
                'brand' => ConvoConnectBrand::name(),
            ]);
        }

        return __('SMS is not configured. Connect your Twilio account in App Settings → SMS.');
    }

    public function hostPinnacleProvisioned(Company $company): bool
    {
        return HostPinnacleCredentials::forCompany($company) !== null;
    }

    public function twilioConfigured(Company $company): bool
    {
        return trim((string) $company->getConfig('TWILIO_ACCOUNT_SID', '')) !== ''
            && trim((string) $company->getConfig('TWILIO_AUTH_TOKEN', '')) !== ''
            && trim((string) $company->getConfig('TWILIO_FROM_NUMBER', '')) !== '';
    }

    public function telnyxConfigured(Company $company): bool
    {
        return trim((string) $company->getConfig('TELNYX_API_KEY', '')) !== ''
            && trim((string) $company->getConfig('TELNYX_FROM_NUMBER', '')) !== '';
    }

    public function senderIdApproved(Company $company): bool
    {
        if (! config('hostpinnacle.require_approved_sender_id', true)) {
            return $this->hostPinnacleProvisioned($company);
        }

        $status = strtolower(trim((string) $company->getConfig('HOSTPINNACLE_SENDER_STATUS', '')));

        return in_array($status, ['approved', 'active', 'success'], true);
    }
}
