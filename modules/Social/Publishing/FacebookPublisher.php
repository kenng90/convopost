<?php

namespace Modules\Social\Publishing;

use Illuminate\Support\Facades\Http;
use Modules\Social\Contracts\SocialPublisherInterface;
use Modules\Social\Enums\SocialProvider;
use Modules\Social\Models\SocialAccount;
use Modules\Social\Models\SocialPostVersion;
use Modules\Social\Support\PublishResult;

class FacebookPublisher implements SocialPublisherInterface
{
    public function provider(): SocialProvider
    {
        return SocialProvider::Facebook;
    }

    public function publish(SocialAccount $account, SocialPostVersion $version, array $mediaUrls = []): PublishResult
    {
        $token = $account->getAccessToken();
        $pageId = (string) data_get($account->meta, 'page_id', $account->external_id);

        if (! $token || $pageId === '') {
            return PublishResult::fail('Facebook Page credentials are incomplete.');
        }

        $graph = rtrim((string) config('social.providers.facebook.oauth.graph_version', 'v21.0'), '/');
        $message = (string) ($version->content ?? '');

        if ($mediaUrls !== []) {
            $photoUrl = $mediaUrls[0];
            $response = Http::asForm()->post(
                "https://graph.facebook.com/{$graph}/{$pageId}/photos",
                [
                    'url' => $photoUrl,
                    'caption' => $message,
                    'published' => true,
                    'access_token' => $token,
                ]
            );
        } else {
            $response = Http::asForm()->post(
                "https://graph.facebook.com/{$graph}/{$pageId}/feed",
                [
                    'message' => $message,
                    'access_token' => $token,
                ]
            );
        }

        if (! $response->successful()) {
            return PublishResult::fail(
                (string) ($response->json('error.message') ?? $response->body())
            );
        }

        $providerPostId = (string) ($response->json('post_id') ?? $response->json('id') ?? '');

        if ($providerPostId === '') {
            return PublishResult::fail('Facebook did not return a post id.');
        }

        return PublishResult::ok($providerPostId, ['response' => $response->json()]);
    }

    public function refreshToken(SocialAccount $account): bool
    {
        return false;
    }
}
