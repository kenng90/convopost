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
