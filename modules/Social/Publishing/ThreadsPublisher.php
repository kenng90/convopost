<?php

namespace Modules\Social\Publishing;

use Illuminate\Support\Facades\Http;
use Modules\Social\Contracts\SocialPublisherInterface;
use Modules\Social\Enums\SocialProvider;
use Modules\Social\Models\SocialAccount;
use Modules\Social\Models\SocialPostVersion;
use Modules\Social\Support\PublishResult;

class ThreadsPublisher implements SocialPublisherInterface
{
    public function provider(): SocialProvider
    {
        return SocialProvider::Threads;
    }

    public function publish(SocialAccount $account, SocialPostVersion $version, array $mediaUrls = []): PublishResult
    {
        $token = $account->getAccessToken();
        $userId = (string) data_get($account->meta, 'threads_user_id', $account->external_id);

        if (! $token || $userId === '') {
            return PublishResult::fail('Threads account credentials are incomplete.');
        }

        $text = (string) ($version->content ?? '');
        $payload = [
            'access_token' => $token,
            'text' => mb_substr($text, 0, 500),
        ];

        if ($mediaUrls !== []) {
            $mediaUrl = $mediaUrls[0];
            if ($this->looksLikeVideo($mediaUrl)) {
                $payload['media_type'] = 'VIDEO';
                $payload['video_url'] = $mediaUrl;
            } else {
                $payload['media_type'] = 'IMAGE';
                $payload['image_url'] = $mediaUrl;
            }
        } else {
            $payload['media_type'] = 'TEXT';
        }

        $base = $this->apiBase().'/'.$this->graphVersion().'/'.$userId;

        $container = Http::asForm()->post($base.'/threads', $payload);

        if (! $container->successful() || ! $container->json('id')) {
            return PublishResult::fail(
                (string) (data_get($container->json(), 'error.message') ?? $container->body())
            );
        }

        $creationId = (string) $container->json('id');

        $publish = Http::asForm()->post($base.'/threads_publish', [
            'creation_id' => $creationId,
            'access_token' => $token,
        ]);

        if (! $publish->successful() || ! $publish->json('id')) {
            return PublishResult::fail(
                (string) (data_get($publish->json(), 'error.message') ?? $publish->body())
            );
        }

        return PublishResult::ok((string) $publish->json('id'), [
            'creation_id' => $creationId,
            'response' => $publish->json(),
        ]);
    }

    public function refreshToken(SocialAccount $account): bool
    {
        $token = $account->getAccessToken();

        if (! $token) {
            return false;
        }

        $response = Http::get($this->apiBase().'/refresh_access_token', [
            'grant_type' => 'th_refresh_token',
            'access_token' => $token,
        ]);

        if (! $response->successful()) {
            return false;
        }

        $accessToken = (string) ($response->json('access_token') ?? '');
        $expiresIn = (int) ($response->json('expires_in') ?? 0);

        if ($accessToken === '') {
            return false;
        }

        $account->setAccessToken($accessToken);

        if ($expiresIn > 0) {
            $account->token_expires_at = now()->addSeconds($expiresIn);
        }

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

    protected function apiBase(): string
    {
        return rtrim((string) config('social.providers.threads.oauth.api_base', 'https://graph.threads.net'), '/');
    }

    protected function graphVersion(): string
    {
        return trim((string) config('social.providers.threads.oauth.graph_version', 'v1.0'), '/');
    }
}
