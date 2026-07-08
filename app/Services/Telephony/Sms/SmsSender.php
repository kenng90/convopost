<?php

namespace App\Services\Telephony\Sms;

use App\Models\Company;
use App\Services\HostPinnacle\HostPinnacleClient;
use App\Services\HostPinnacle\HostPinnacleCreditSync;
use App\Services\Telephony\PhoneNormalizer;

class SmsSender
{
    public function __construct(
        private readonly HostPinnacleClient $hostPinnacleClient,
        private readonly HostPinnacleCreditSync $hostPinnacleCreditSync,
        private readonly PhoneNormalizer $normalizer,
    ) {
    }

    public function send(Company $company, string $to, string $body): SmsSendResult
    {
        $config = SmsConfig::forCompany($company);

        if (! $config->smsReady()) {
            return SmsSendResult::fail($this->missingConfigurationMessage($config));
        }

        return match ($config->provider) {
            SmsProvider::HOSTPINNACLE => (new HostPinnacleSmsSender(
                $config,
                $this->hostPinnacleClient,
                $this->hostPinnacleCreditSync,
                $this->normalizer,
            ))->send($to, $body),
            SmsProvider::TWILIO => (new TwilioSmsSender($config->telephonyConfig()))->send($to, $body),
            SmsProvider::TELNYX => (new TelnyxSmsSender($config->telephonyConfig()))->send($to, $body),
            default => SmsSendResult::fail(app(SmsAvailability::class)->statusMessage($company)),
        };
    }

    private function missingConfigurationMessage(SmsConfig $config): string
    {
        return app(SmsAvailability::class)->statusMessage($config->company);
    }
}
