<?php

namespace Modules\Tiktok\Messaging;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class TiktokClient
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array{ok: bool, data: array<string, mixed>, message: string, code: mixed}
     */
    public function sendMessage(
        string $accessToken,
        string $businessId,
        string $conversationId,
        string $messageType,
        array $payload,
    ): array {
        $body = array_merge([
            'business_id' => $businessId,
            'message_type' => $messageType,
            'recipient_type' => 'CONVERSATION',
            'recipient' => $conversationId,
        ], $payload);

        $response = Http::withHeaders($this->headers($accessToken))
            ->asJson()
            ->post($this->url('/business/message/send/'), $body);

        return $this->decode($response);
    }

    /**
     * @return array{ok: bool, data: array<string, mixed>, message: string, code: mixed}
     */
    public function getCapabilities(string $accessToken, string $businessId, ?string $conversationId = null): array
    {
        $body = [
            'business_id' => $businessId,
        ];

        if ($conversationId) {
            $body['recipient_type'] = 'CONVERSATION';
            $body['recipient'] = $conversationId;
        }

        $response = Http::withHeaders($this->headers($accessToken))
            ->asJson()
            ->post($this->url('/business/message/capabilities/get/'), $body);

        return $this->decode($response);
    }

    /**
     * Refresh a Business Account (or Marketing API) access token.
     *
     * @return array{ok: bool, data: array<string, mixed>, message: string, code: mixed}
     */
    public function refreshAccessToken(string $refreshToken): array
    {
        $appId = (string) config('services.tiktok.app_id', '');
        $secret = (string) config('services.tiktok.app_secret', '');

        $businessAccount = Http::asJson()->post($this->url('/tt_user/oauth2/refresh_token/'), [
            'client_id' => $appId,
            'client_secret' => $secret,
            'grant_type' => 'refresh_token',
            'refresh_token' => $refreshToken,
        ]);

        $decoded = $this->decode($businessAccount);
        if ($decoded['ok']) {
            return $decoded;
        }

        $marketing = Http::asJson()->post($this->url('/oauth2/refresh_token/'), [
            'app_id' => $appId,
            'secret' => $secret,
            'refresh_token' => $refreshToken,
        ]);

        return $this->decode($marketing);
    }

    /**
     * Register or update the app-level Webhooks API callback for a Business Messaging event.
     *
     * @return array{ok: bool, data: array<string, mixed>, message: string, code: mixed}
     */
    public function updateWebhook(string $eventType, string $callbackUrl): array
    {
        $response = Http::asJson()->post($this->url('/business/webhook/update/'), [
            'app_id' => (string) config('services.tiktok.app_id', ''),
            'secret' => (string) config('services.tiktok.app_secret', ''),
            'event_type' => $eventType,
            'callback_url' => $callbackUrl,
        ]);

        return $this->decode($response);
    }

    /**
     * @return array<string, string>
     */
    private function headers(string $accessToken): array
    {
        return [
            'Access-Token' => $accessToken,
            'Content-Type' => 'application/json',
        ];
    }

    private function url(string $path): string
    {
        return rtrim((string) config('services.tiktok.base_url'), '/').$path;
    }

    /**
     * @return array{ok: bool, data: array<string, mixed>, message: string, code: mixed}
     */
    private function decode(Response $response): array
    {
        $json = $response->json();
        if (! is_array($json)) {
            $json = [];
        }

        $data = $json['data'] ?? [];

        return [
            'ok' => $response->successful() && (int) ($json['code'] ?? -1) === 0,
            'data' => is_array($data) ? $data : [],
            'message' => (string) ($json['message'] ?? $response->body()),
            'code' => $json['code'] ?? $response->status(),
        ];
    }
}
