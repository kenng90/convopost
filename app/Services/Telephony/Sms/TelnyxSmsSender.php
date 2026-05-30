<?php

namespace App\Services\Telephony\Sms;

use App\Services\Telephony\TelephonyConfig;
use Illuminate\Support\Facades\Http;

class TelnyxSmsSender
{
    public function __construct(
        protected TelephonyConfig $config,
    ) {
    }

    public function send(string $to, string $body): SmsSendResult
    {
        $apiKey = $this->config->stringConfig('TELNYX_API_KEY');
        $from = $this->config->stringConfig('TELNYX_FROM_NUMBER');
        $profileId = $this->config->stringConfig('TELNYX_MESSAGING_PROFILE_ID');

        $payload = [
            'from' => $from,
            'to' => $to,
            'text' => $body,
        ];
        if ($profileId !== '') {
            $payload['messaging_profile_id'] = $profileId;
        }

        $response = Http::withToken($apiKey)
            ->acceptJson()
            ->post('https://api.telnyx.com/v2/messages', $payload);

        if ($response->failed()) {
            $errors = $response->json('errors.0.detail') ?? $response->body();

            return SmsSendResult::fail('Telnyx error: '.$errors);
        }

        $id = $response->json('data.id');

        return SmsSendResult::ok($id);
    }
}
