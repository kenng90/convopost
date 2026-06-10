<?php

namespace App\Services\Telephony\Sms;

use App\Services\Telephony\TelephonyConfig;
use Illuminate\Support\Facades\Http;

class TwilioSmsSender
{
    public function __construct(
        protected TelephonyConfig $config,
    ) {
    }

    public function send(string $to, string $body): SmsSendResult
    {
        $sid = $this->config->stringConfig('TWILIO_ACCOUNT_SID');
        $token = $this->config->stringConfig('TWILIO_AUTH_TOKEN');
        $from = $this->config->stringConfig('TWILIO_FROM_NUMBER');

        $response = Http::withBasicAuth($sid, $token)
            ->asForm()
            ->post("https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json", [
                'To' => $to,
                'From' => $from,
                'Body' => $body,
            ]);

        if ($response->failed()) {
            return SmsSendResult::fail('Twilio HTTP error: '.$response->body());
        }

        $data = $response->json();
        if (($data['status'] ?? '') === 'queued') {
            return SmsSendResult::ok($data['sid'] ?? null);
        }

        return SmsSendResult::fail('Twilio error: '.($data['message'] ?? $response->body()));
    }
}
