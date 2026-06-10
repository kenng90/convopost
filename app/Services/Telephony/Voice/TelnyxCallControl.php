<?php

namespace App\Services\Telephony\Voice;

use App\Services\Telephony\TelephonyConfig;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelnyxCallControl
{
    public function __construct(
        protected TelephonyConfig $config,
    ) {
    }

    public static function forCompany(\App\Models\Company $company): self
    {
        return new self(\App\Services\Telephony\TelephonyConfig::forCompany($company));
    }

    public function answer(string $callControlId): bool
    {
        return $this->action($callControlId, 'answer');
    }

    public function speak(string $callControlId, string $text, string $voice = 'female'): bool
    {
        return $this->action($callControlId, 'speak', [
            'payload' => $text,
            'voice' => $voice,
            'language' => 'en-US',
        ]);
    }

    /**
     * @param  array<string>  $speech
     */
    public function gatherUsingSpeak(
        string $callControlId,
        string $prompt,
        array $speech = ['en-US'],
    ): bool {
        return $this->action($callControlId, 'gather_using_speak', [
            'payload' => $prompt,
            'voice' => 'female',
            'language' => 'en-US',
            'valid_inputs' => ['speech'],
            'speech' => [
                'language' => $speech[0] ?? 'en-US',
            ],
        ]);
    }

    public function hangup(string $callControlId): bool
    {
        return $this->action($callControlId, 'hangup');
    }

    /**
     * @param  array<string, mixed>  $body
     */
    protected function action(string $callControlId, string $command, array $body = []): bool
    {
        $apiKey = $this->config->stringConfig('TELNYX_API_KEY');
        if ($apiKey === '') {
            return false;
        }

        $response = Http::withToken($apiKey)
            ->acceptJson()
            ->post("https://api.telnyx.com/v2/calls/{$callControlId}/actions/{$command}", $body);

        if ($response->failed()) {
            Log::warning('telnyx.call_control_failed', [
                'command' => $command,
                'call_control_id' => $callControlId,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return false;
        }

        return true;
    }
}
