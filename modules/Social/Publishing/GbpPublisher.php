<?php

namespace Modules\Social\Publishing;

use Illuminate\Support\Facades\Http;
use Modules\Social\Contracts\SocialPublisherInterface;
use Modules\Social\Enums\SocialProvider;
use Modules\Social\Models\SocialAccount;
use Modules\Social\Models\SocialPostVersion;
use Modules\Social\Support\PublishResult;

class GbpPublisher implements SocialPublisherInterface
{
    public function provider(): SocialProvider
    {
        return SocialProvider::Gbp;
    }

    public function publish(SocialAccount $account, SocialPostVersion $version, array $mediaUrls = []): PublishResult
    {
        $token = $account->getAccessToken();
        $locationName = (string) data_get($account->meta, 'location_name', '');

        if ($locationName === '' && is_string($account->external_id) && str_contains($account->external_id, 'accounts_')) {
            $locationName = str_replace('_', '/', $account->external_id);
        }

        if (! $token || $locationName === '') {
            return PublishResult::fail('Google Business Profile location credentials are incomplete.');
        }

        $summary = trim((string) ($version->content ?? ''));

        if ($summary === '') {
            return PublishResult::fail('Google Business Profile posts require caption text.');
        }

        $payload = [
            'languageCode' => 'en-US',
            'summary' => mb_substr($summary, 0, 1500),
            'topicType' => 'STANDARD',
        ];

        if ($mediaUrls !== []) {
            $mediaUrl = $mediaUrls[0];
            $payload['media'] = [[
                'mediaFormat' => $this->looksLikeVideo($mediaUrl) ? 'VIDEO' : 'PHOTO',
                'sourceUrl' => $mediaUrl,
            ]];
        }

        $response = Http::withToken($token)
            ->acceptJson()
            ->post($this->localPostsBase().'/v4/'.$locationName.'/localPosts', $payload);

        if (! $response->successful()) {
            return PublishResult::fail(
                (string) (data_get($response->json(), 'error.message') ?? $response->body())
            );
        }

        $postName = (string) data_get($response->json(), 'name', '');
        $postId = $postName !== '' ? $postName : (string) data_get($response->json(), 'searchUrl', 'gbp-post');

        return PublishResult::ok($postId, ['response' => $response->json()]);
    }

    public function refreshToken(SocialAccount $account): bool
    {
        $refresh = $account->getRefreshToken();

        if (! $refresh) {
            return false;
        }

        $response = Http::asForm()->post($this->tokenUrl(), [
            'client_id' => $this->clientId(),
            'client_secret' => $this->clientSecret(),
            'grant_type' => 'refresh_token',
            'refresh_token' => $refresh,
        ]);

        if (! $response->successful()) {
            return false;
        }

        $accessToken = (string) ($response->json('access_token') ?? '');
        $expiresIn = (int) ($response->json('expires_in') ?? 3600);

        if ($accessToken === '') {
            return false;
        }

        $account->setAccessToken($accessToken);
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
        return trim((string) config('social.providers.gbp.oauth.client_id', ''));
    }

    protected function clientSecret(): string
    {
        return trim((string) config('social.providers.gbp.oauth.client_secret', ''));
    }

    protected function tokenUrl(): string
    {
        return (string) config('social.providers.gbp.oauth.token_url', 'https://oauth2.googleapis.com/token');
    }

    protected function localPostsBase(): string
    {
        return rtrim((string) config('social.providers.gbp.oauth.local_posts_base', 'https://mybusiness.googleapis.com'), '/');
    }
}
