<?php

namespace Modules\Social\Publishing;

use Illuminate\Support\Facades\Http;
use Modules\Social\Contracts\SocialPublisherInterface;
use Modules\Social\Enums\SocialProvider;
use Modules\Social\Models\SocialAccount;
use Modules\Social\Models\SocialPostVersion;
use Modules\Social\Support\PublishResult;

class LinkedInPublisher implements SocialPublisherInterface
{
    public function provider(): SocialProvider
    {
        return SocialProvider::LinkedIn;
    }

    public function publish(SocialAccount $account, SocialPostVersion $version, array $mediaUrls = []): PublishResult
    {
        $token = $account->getAccessToken();

        if (! $token) {
            return PublishResult::fail('LinkedIn access token is missing.');
        }

        $authorUrn = (string) data_get(
            $account->meta,
            'organization_urn',
            data_get($account->meta, 'member_urn', '')
        );

        if ($authorUrn === '') {
            $type = (string) data_get($account->meta, 'type', 'organization');
            $authorUrn = $type === 'member'
                ? 'urn:li:person:'.$account->external_id
                : 'urn:li:organization:'.$account->external_id;
        }

        $payload = [
            'author' => $authorUrn,
            'lifecycleState' => 'PUBLISHED',
            'specificContent' => [
                'com.linkedin.ugc.ShareContent' => [
                    'shareCommentary' => [
                        'text' => (string) ($version->content ?? ''),
                    ],
                    'shareMediaCategory' => $mediaUrls === [] ? 'NONE' : 'ARTICLE',
                ],
            ],
            'visibility' => [
                'com.linkedin.ugc.MemberNetworkVisibility' => 'PUBLIC',
            ],
        ];

        if ($mediaUrls !== []) {
            $payload['specificContent']['com.linkedin.ugc.ShareContent']['media'] = [[
                'status' => 'READY',
                'originalUrl' => $mediaUrls[0],
            ]];
        }

        $response = Http::withToken($token)
            ->withHeaders([
                'X-Restli-Protocol-Version' => '2.0.0',
                'Content-Type' => 'application/json',
            ])
            ->post('https://api.linkedin.com/v2/ugcPosts', $payload);

        if (! $response->successful()) {
            return PublishResult::fail(
                (string) ($response->json('message') ?? $response->body())
            );
        }

        $providerPostId = (string) ($response->json('id') ?? $response->header('x-restli-id') ?? '');

        if ($providerPostId === '') {
            return PublishResult::fail('LinkedIn did not return a post id.');
        }

        return PublishResult::ok($providerPostId, ['response' => $response->json()]);
    }

    public function refreshToken(SocialAccount $account): bool
    {
        $refresh = $account->getRefreshToken();
        $clientId = (string) config('social.providers.linkedin.oauth.client_id', '');
        $clientSecret = (string) config('social.providers.linkedin.oauth.client_secret', '');

        if (! $refresh || $clientId === '' || $clientSecret === '') {
            return false;
        }

        $response = Http::asForm()->post('https://www.linkedin.com/oauth/v2/accessToken', [
            'grant_type' => 'refresh_token',
            'refresh_token' => $refresh,
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
        ]);

        if (! $response->successful() || ! $response->json('access_token')) {
            return false;
        }

        $account->setAccessToken((string) $response->json('access_token'));

        if ($response->json('refresh_token')) {
            $account->setRefreshToken((string) $response->json('refresh_token'));
        }

        if ($response->json('expires_in')) {
            $account->token_expires_at = now()->addSeconds((int) $response->json('expires_in'));
        }

        $account->save();

        return true;
    }
}
