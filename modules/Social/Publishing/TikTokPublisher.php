<?php

namespace Modules\Social\Publishing;

use Illuminate\Support\Facades\Http;
use Modules\Social\Contracts\SocialPublisherInterface;
use Modules\Social\Enums\SocialProvider;
use Modules\Social\Models\SocialAccount;
use Modules\Social\Models\SocialPostVersion;
use Modules\Social\Support\PublishResult;

class TikTokPublisher implements SocialPublisherInterface
{
    public function provider(): SocialProvider
    {
        return SocialProvider::TikTok;
    }

    public function publish(SocialAccount $account, SocialPostVersion $version, array $mediaUrls = []): PublishResult
    {
        $token = $account->getAccessToken();

        if (! $token) {
            return PublishResult::fail('TikTok access token is missing.');
        }

        if ($mediaUrls === []) {
            return PublishResult::fail('TikTok posts require at least one video or image media URL.');
        }

        $mediaUrl = $mediaUrls[0];
        $isVideo = $this->looksLikeVideo($mediaUrl);
        $caption = (string) ($version->content ?? '');

        if ($isVideo) {
            return $this->publishVideo($token, $caption, $mediaUrl);
        }

        return $this->publishPhoto($token, $caption, $mediaUrls);
    }

    public function refreshToken(SocialAccount $account): bool
    {
        $refresh = $account->getRefreshToken();

        if (! $refresh) {
            return false;
        }

        $response = Http::asForm()->post($this->tokenUrl(), [
            'client_key' => $this->clientKey(),
            'client_secret' => $this->clientSecret(),
            'grant_type' => 'refresh_token',
            'refresh_token' => $refresh,
        ]);

        if (! $response->successful()) {
            return false;
        }

        $accessToken = (string) ($response->json('access_token') ?? '');
        $newRefresh = $response->json('refresh_token');
        $expiresIn = (int) ($response->json('expires_in') ?? 86400);

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

    protected function publishVideo(string $token, string $caption, string $videoUrl): PublishResult
    {
        $init = Http::withToken($token)
            ->acceptJson()
            ->post($this->apiBase().'/v2/post/publish/video/init/', [
                'post_info' => [
                    'title' => mb_substr($caption, 0, 2200),
                    'privacy_level' => 'PUBLIC_TO_EVERYONE',
                    'disable_duet' => false,
                    'disable_comment' => false,
                    'disable_stitch' => false,
                ],
                'source_info' => [
                    'source' => 'PULL_FROM_URL',
                    'video_url' => $videoUrl,
                ],
            ]);

        if (! $init->successful()) {
            return PublishResult::fail(
                (string) (data_get($init->json(), 'error.message') ?? $init->body())
            );
        }

        $publishId = (string) data_get($init->json(), 'data.publish_id', '');

        if ($publishId === '') {
            return PublishResult::fail('TikTok did not return a publish_id for the video.');
        }

        return PublishResult::ok($publishId, ['response' => $init->json()]);
    }

    /**
     * @param  list<string>  $photoUrls
     */
    protected function publishPhoto(string $token, string $caption, array $photoUrls): PublishResult
    {
        $init = Http::withToken($token)
            ->acceptJson()
            ->post($this->apiBase().'/v2/post/publish/content/init/', [
                'post_info' => [
                    'title' => mb_substr($caption, 0, 2200),
                    'privacy_level' => 'PUBLIC_TO_EVERYONE',
                    'disable_comment' => false,
                    'auto_add_music' => true,
                ],
                'source_info' => [
                    'source' => 'PULL_FROM_URL',
                    'photo_cover_index' => 0,
                    'photo_images' => array_values(array_slice($photoUrls, 0, 35)),
                ],
                'post_mode' => 'DIRECT_POST',
                'media_type' => 'PHOTO',
            ]);

        if (! $init->successful()) {
            return PublishResult::fail(
                (string) (data_get($init->json(), 'error.message') ?? $init->body())
            );
        }

        $publishId = (string) data_get($init->json(), 'data.publish_id', '');

        if ($publishId === '') {
            return PublishResult::fail('TikTok did not return a publish_id for the photo post.');
        }

        return PublishResult::ok($publishId, ['response' => $init->json()]);
    }

    protected function looksLikeVideo(string $url): bool
    {
        $path = strtolower(parse_url($url, PHP_URL_PATH) ?? '');

        return str_ends_with($path, '.mp4')
            || str_ends_with($path, '.mov')
            || str_ends_with($path, '.webm')
            || str_contains($path, '/video');
    }

    protected function clientKey(): string
    {
        return trim((string) config('social.providers.tiktok.oauth.client_key', ''));
    }

    protected function clientSecret(): string
    {
        return trim((string) config('social.providers.tiktok.oauth.client_secret', ''));
    }

    protected function tokenUrl(): string
    {
        return (string) config('social.providers.tiktok.oauth.token_url', 'https://open.tiktokapis.com/v2/oauth/token/');
    }

    protected function apiBase(): string
    {
        return rtrim((string) config('social.providers.tiktok.oauth.api_base', 'https://open.tiktokapis.com'), '/');
    }
}
