<?php

namespace Modules\Social\Publishing;

use Illuminate\Support\Facades\Http;
use Modules\Social\Contracts\SocialPublisherInterface;
use Modules\Social\Enums\SocialProvider;
use Modules\Social\Models\SocialAccount;
use Modules\Social\Models\SocialPostVersion;
use Modules\Social\Support\PublishResult;
use Modules\Social\Support\XThreadParts;

class XPublisher implements SocialPublisherInterface
{
    public function provider(): SocialProvider
    {
        return SocialProvider::X;
    }

    public function publish(SocialAccount $account, SocialPostVersion $version, array $mediaUrls = []): PublishResult
    {
        $token = $account->getAccessToken();

        if (! $token) {
            return PublishResult::fail('X access token is missing.');
        }

        $parts = XThreadParts::fromVersion($version);

        if ($parts === [] && $mediaUrls === []) {
            return PublishResult::fail('X posts require text or media.');
        }

        // Media-only first tweet when there is no text.
        if ($parts === []) {
            $parts = [''];
        }

        $tweetIds = [];
        $previousId = null;

        foreach ($parts as $index => $text) {
            $payload = [];

            $trimmed = mb_substr(trim($text), 0, 280);
            if ($trimmed !== '') {
                $payload['text'] = $trimmed;
            }

            if ($index === 0 && $mediaUrls !== []) {
                $mediaIds = [];
                foreach (array_slice($mediaUrls, 0, 4) as $mediaUrl) {
                    $mediaId = $this->uploadMedia($token, $mediaUrl);
                    if ($mediaId === null) {
                        return PublishResult::fail('Failed to upload media to X: '.$mediaUrl);
                    }
                    $mediaIds[] = $mediaId;
                }

                if ($mediaIds !== []) {
                    $payload['media'] = ['media_ids' => $mediaIds];
                }
            }

            if ($previousId !== null) {
                $payload['reply'] = [
                    'in_reply_to_tweet_id' => $previousId,
                ];
            }

            if ($payload === [] || (! isset($payload['text']) && ! isset($payload['media']))) {
                return PublishResult::fail('X thread part requires text or media.');
            }

            $response = Http::withToken($token)
                ->acceptJson()
                ->post($this->apiBase().'/2/tweets', $payload);

            if (! $response->successful()) {
                $error = (string) (data_get($response->json(), 'detail')
                    ?? data_get($response->json(), 'title')
                    ?? data_get($response->json(), 'errors.0.message')
                    ?? $response->body());

                if ($tweetIds !== []) {
                    return PublishResult::fail(
                        'X thread partially published ('.count($tweetIds).' tweet(s)), then failed: '.$error,
                        ['thread_ids' => $tweetIds, 'response' => $response->json()]
                    );
                }

                return PublishResult::fail($error);
            }

            $tweetId = (string) data_get($response->json(), 'data.id', '');

            if ($tweetId === '') {
                return PublishResult::fail('X did not return a tweet id.');
            }

            $tweetIds[] = $tweetId;
            $previousId = $tweetId;
        }

        return PublishResult::ok($tweetIds[0], [
            'thread_ids' => $tweetIds,
            'thread_count' => count($tweetIds),
        ]);
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
        $expiresIn = (int) ($response->json('expires_in') ?? 7200);

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

    protected function uploadMedia(string $token, string $mediaUrl): ?string
    {
        $download = Http::timeout(60)->get($mediaUrl);

        if (! $download->successful()) {
            return null;
        }

        $bytes = $download->body();
        $filename = basename(parse_url($mediaUrl, PHP_URL_PATH) ?: 'media.bin');
        $mime = $this->mimeFor($mediaUrl);

        $upload = Http::withToken($token)
            ->attach('media', $bytes, $filename, ['Content-Type' => $mime])
            ->post($this->uploadBase().'/1.1/media/upload.json');

        if (! $upload->successful()) {
            return null;
        }

        $mediaId = (string) ($upload->json('media_id_string') ?? $upload->json('media_id') ?? '');

        return $mediaId !== '' ? $mediaId : null;
    }

    protected function mimeFor(string $url): string
    {
        $path = strtolower(parse_url($url, PHP_URL_PATH) ?? '');

        return match (true) {
            str_ends_with($path, '.png') => 'image/png',
            str_ends_with($path, '.gif') => 'image/gif',
            str_ends_with($path, '.webp') => 'image/webp',
            str_ends_with($path, '.mp4') => 'video/mp4',
            str_ends_with($path, '.mov') => 'video/quicktime',
            default => 'image/jpeg',
        };
    }

    protected function clientId(): string
    {
        return trim((string) config('social.providers.x.oauth.client_id', ''));
    }

    protected function clientSecret(): string
    {
        return trim((string) config('social.providers.x.oauth.client_secret', ''));
    }

    protected function tokenUrl(): string
    {
        return (string) config('social.providers.x.oauth.token_url', 'https://api.twitter.com/2/oauth2/token');
    }

    protected function apiBase(): string
    {
        return rtrim((string) config('social.providers.x.oauth.api_base', 'https://api.twitter.com'), '/');
    }

    protected function uploadBase(): string
    {
        return rtrim((string) config('social.providers.x.oauth.upload_base', 'https://upload.twitter.com'), '/');
    }
}
