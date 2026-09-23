<?php

namespace Modules\Social\Publishing;

use Illuminate\Support\Facades\Http;
use Modules\Social\Contracts\SocialPublisherInterface;
use Modules\Social\Enums\SocialProvider;
use Modules\Social\Models\SocialAccount;
use Modules\Social\Models\SocialPostVersion;
use Modules\Social\Support\PublishResult;

class PinterestPublisher implements SocialPublisherInterface
{
    public function provider(): SocialProvider
    {
        return SocialProvider::Pinterest;
    }

    public function publish(SocialAccount $account, SocialPostVersion $version, array $mediaUrls = []): PublishResult
    {
        $token = $account->getAccessToken();
        $boardId = (string) data_get($account->meta, 'board_id', $account->external_id);

        if (! $token || $boardId === '') {
            return PublishResult::fail('Pinterest account credentials are incomplete.');
        }

        if ($mediaUrls === []) {
            return PublishResult::fail('Pinterest pins require at least one image media URL.');
        }

        $mediaUrl = $mediaUrls[0];

        if ($this->looksLikeVideo($mediaUrl)) {
            return PublishResult::fail('Pinterest video pins require a prior video upload id; use an image URL for now.');
        }

        $caption = trim((string) ($version->content ?? ''));
        $title = mb_substr($caption !== '' ? $caption : 'Pin', 0, 100);
        $description = mb_substr($caption, 0, 800);

        $payload = [
            'board_id' => $boardId,
            'title' => $title,
            'description' => $description,
            'media_source' => [
                'source_type' => 'image_url',
                'url' => $mediaUrl,
            ],
        ];

        $response = Http::withToken($token)
            ->acceptJson()
            ->post($this->apiBase().'/v5/pins', $payload);

        if (! $response->successful()) {
            return PublishResult::fail(
                (string) (data_get($response->json(), 'message')
                    ?? data_get($response->json(), 'error.message')
                    ?? $response->body())
            );
        }

        $pinId = (string) data_get($response->json(), 'id', '');

        if ($pinId === '') {
            return PublishResult::fail('Pinterest did not return a pin id.');
        }

        return PublishResult::ok($pinId, ['response' => $response->json()]);
    }

    public function refreshToken(SocialAccount $account): bool
    {
        $refresh = $account->getRefreshToken();

        if (! $refresh) {
            return false;
        }

        $response = Http::withBasicAuth($this->clientId(), $this->clientSecret())
            ->asForm()
            ->post($this->tokenUrl(), [
                'grant_type' => 'refresh_token',
                'refresh_token' => $refresh,
            ]);

        if (! $response->successful()) {
            return false;
        }

        $accessToken = (string) ($response->json('access_token') ?? '');
        $newRefresh = $response->json('refresh_token');
        $expiresIn = (int) ($response->json('expires_in') ?? 2592000);

        if ($accessToken === '') {
            return false;
        }

        $account->setAccessToken($accessToken);

        if (is_string($newRefresh) && $newRefresh !== '') {
            $account->setRefreshToken($newRefresh);
        }

        $account->token_expires_at = now()->addSeconds($expiresIn);
        $account->save();

        return true;
    }

    protected function looksLikeVideo(string $url): bool
    {
        $path = strtolower(parse_url($url, PHP_URL_PATH) ?? '');

        return str_ends_with($path, '.mp4')
            || str_ends_with($path, '.mov')
            || str_ends_with($path, '.webm')
            || str_contains($path, '/video');
    }

    protected function clientId(): string
    {
        return trim((string) config('social.providers.pinterest.oauth.client_id', ''));
    }

    protected function clientSecret(): string
    {
        return trim((string) config('social.providers.pinterest.oauth.client_secret', ''));
    }

    protected function tokenUrl(): string
    {
        return (string) config('social.providers.pinterest.oauth.token_url', 'https://api.pinterest.com/v5/oauth/token');
    }

    protected function apiBase(): string
    {
        return rtrim((string) config('social.providers.pinterest.oauth.api_base', 'https://api.pinterest.com'), '/');
    }
}
