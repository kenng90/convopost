<?php

namespace Modules\Social\Publishing;

use Illuminate\Support\Facades\Http;
use Modules\Social\Contracts\SocialPublisherInterface;
use Modules\Social\Enums\SocialProvider;
use Modules\Social\Models\SocialAccount;
use Modules\Social\Models\SocialPostVersion;
use Modules\Social\Support\PublishResult;

class InstagramPublisher implements SocialPublisherInterface
{
    use PostsFirstComment;

    public function provider(): SocialProvider
    {
        return SocialProvider::Instagram;
    }

    public function publish(SocialAccount $account, SocialPostVersion $version, array $mediaUrls = []): PublishResult
    {
        $token = $account->getAccessToken();
        $igUserId = (string) data_get($account->meta, 'instagram_account_id', $account->external_id);

        if (! $token || $igUserId === '') {
            return PublishResult::fail('Instagram account credentials are incomplete.');
        }

        if ($mediaUrls === []) {
            return PublishResult::fail('Instagram publishing requires at least one image or video URL.');
        }

        $graph = rtrim((string) config('social.providers.instagram.oauth.graph_version', 'v21.0'), '/');
        $caption = (string) ($version->content ?? '');
        $mediaUrl = $mediaUrls[0];
        $isVideo = $this->looksLikeVideo($mediaUrl);

        $containerPayload = [
            'caption' => $caption,
            'access_token' => $token,
        ];

        if ($isVideo) {
            $containerPayload['media_type'] = 'REELS';
            $containerPayload['video_url'] = $mediaUrl;
        } else {
            $containerPayload['image_url'] = $mediaUrl;
        }

        $container = Http::asForm()->post(
            "https://graph.facebook.com/{$graph}/{$igUserId}/media",
            $containerPayload
        );

        if (! $container->successful() || ! $container->json('id')) {
            return PublishResult::fail(
                (string) ($container->json('error.message') ?? $container->body())
            );
        }

        $creationId = (string) $container->json('id');

        $publish = Http::asForm()->post(
            "https://graph.facebook.com/{$graph}/{$igUserId}/media_publish",
            [
                'creation_id' => $creationId,
                'access_token' => $token,
            ]
        );

        if (! $publish->successful() || ! $publish->json('id')) {
            return PublishResult::fail(
                (string) ($publish->json('error.message') ?? $publish->body())
            );
        }

        $providerPostId = (string) $publish->json('id');

        $meta = [
            'creation_id' => $creationId,
            'response' => $publish->json(),
        ];
        $meta = array_merge(
            $meta,
            $this->postFirstComment(
                $account,
                $version,
                $providerPostId,
                "https://graph.facebook.com/{$graph}"
            )
        );

        return PublishResult::ok($providerPostId, $meta);
    }

    public function refreshToken(SocialAccount $account): bool
    {
        return false;
    }

    protected function looksLikeVideo(string $url): bool
    {
        $path = strtolower(parse_url($url, PHP_URL_PATH) ?: $url);

        return str_ends_with($path, '.mp4')
            || str_ends_with($path, '.mov')
            || str_ends_with($path, '.webm');
    }
}
