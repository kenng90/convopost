<?php

namespace App\Services\Telephony\Sms;

use App\Models\Company;
use App\Services\Telephony\TelephonyConfig;
use App\Services\Telephony\TelephonyProvider;

class SmsSender
{
    public function send(Company $company, string $to, string $body): SmsSendResult
    {
        $config = TelephonyConfig::forCompany($company);

        if (! $config->smsReady()) {
            return SmsSendResult::fail(
                $config->isTelnyx()
                    ? 'Telnyx SMS settings missing (API key and from number in App Settings).'
                    : 'Twilio SMS settings missing (Account SID, Auth Token, and from number in App Settings).'
            );
        }

        return match ($config->provider) {
            TelephonyProvider::TWILIO => (new TwilioSmsSender($config))->send($to, $body),
            default => (new TelnyxSmsSender($config))->send($to, $body),
        };
    }
}
