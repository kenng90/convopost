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
     * Exchange a Login Kit / Business Account authorization code for tokens.
     *
     * @return array{ok: bool, data: array<string, mixed>, message: string, code: mixed}
     */
    public function exchangeAuthorizationCode(string $code, string $redirectUri): array
    {
        $response = Http::asJson()->post($this->url('/tt_user/oauth2/token/'), [
            'client_id' => (string) config('services.tiktok.app_id', ''),
            'client_secret' => (string) config('services.tiktok.app_secret', ''),
            'grant_type' => 'authorization_code',
            'auth_code' => $code,
            'redirect_uri' => $redirectUri,
        ]);

        return $this->decode($response);
    }

    /**
     * Send a Comment-to-Message private reply for a high-intent comment.
     *
     * @return array{ok: bool, data: array<string, mixed>, message: string, code: mixed}
     */
    public function sendDirectReply(
        string $accessToken,
        string $businessId,
        string $commentId,
        string $text,
    ): array {
        $response = Http::withHeaders($this->headers($accessToken))
            ->asJson()
            ->post($this->url('/business/message/send/'), [
                'business_id' => $businessId,
                'message_type' => 'TEXT',
                'text' => ['body' => $text],
                'direct_reply' => [
                    'reply_type' => 'COMMENT_TO_MESSAGE',
                    'comment_reply' => ['comment_id' => $commentId],
                ],
            ]);

        return $this->decode($response);
    }

    /**
     * Post a public reply under a video comment.
     *
     * @return array{ok: bool, data: array<string, mixed>, message: string, code: mixed}
     */
    public function replyToPublicComment(
        string $accessToken,
        string $businessId,
        string $videoId,
        string $commentId,
        string $text,
    ): array {
        $response = Http::withHeaders($this->headers($accessToken))
            ->asJson()
            ->post($this->url('/business/comment/reply/create/'), [
                'business_id' => $businessId,
                'video_id' => $videoId,
                'comment_id' => $commentId,
                'text' => $text,
            ]);

        return $this->decode($response);
    }

    /**
     * Enable or disable Comment-to-Message for a Business Account.
     *
     * @return array{ok: bool, data: array<string, mixed>, message: string, code: mixed}
     */
    public function updateCommentToMessage(string $accessToken, string $businessId, string $status = 'ENABLE'): array
    {
        $response = Http::withHeaders($this->headers($accessToken))
            ->asJson()
            ->post($this->url('/business/message/direct_reply/update/'), [
                'business_id' => $businessId,
                'direct_reply_type' => 'COMMENT_TO_MESSAGE',
                'operation_status' => $status,
            ]);

        return $this->decode($response);
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
