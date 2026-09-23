<?php

namespace Modules\Social\Publishing;

use Illuminate\Support\Facades\Http;
use Modules\Social\Contracts\SocialPublisherInterface;
use Modules\Social\Enums\SocialProvider;
use Modules\Social\Models\SocialAccount;
use Modules\Social\Models\SocialPostVersion;
use Modules\Social\Support\PublishResult;

class YouTubePublisher implements SocialPublisherInterface
{
    public function provider(): SocialProvider
    {
        return SocialProvider::YouTube;
    }

    public function publish(SocialAccount $account, SocialPostVersion $version, array $mediaUrls = []): PublishResult
    {
        $token = $account->getAccessToken();

        if (! $token) {
            return PublishResult::fail('YouTube access token is missing.');
        }

        if ($mediaUrls === []) {
            return PublishResult::fail('YouTube Shorts require a video media URL.');
        }

        $videoUrl = $mediaUrls[0];

        if (! $this->looksLikeVideo($videoUrl)) {
            return PublishResult::fail('YouTube Shorts publishing requires a video file (mp4/mov/webm).');
        }

        $videoBytes = $this->downloadMedia($videoUrl);

        if ($videoBytes === null || $videoBytes === '') {
            return PublishResult::fail('Could not download video media for YouTube upload.');
        }

        $caption = trim((string) ($version->content ?? ''));
        $title = mb_substr($caption !== '' ? $caption : 'Short', 0, 100);
        $description = $this->shortsDescription($caption);

        $init = Http::withToken($token)
            ->withHeaders([
                'Content-Type' => 'application/json; charset=UTF-8',
                'X-Upload-Content-Type' => $this->contentTypeFor($videoUrl),
                'X-Upload-Content-Length' => (string) strlen($videoBytes),
            ])
            ->post($this->apiBase().'/upload/youtube/v3/videos?uploadType=resumable&part=snippet,status', [
                'snippet' => [
                    'title' => $title,
                    'description' => $description,
                    'categoryId' => '22',
                ],
                'status' => [
                    'privacyStatus' => 'public',
                    'selfDeclaredMadeForKids' => false,
                ],
            ]);

        if (! $init->successful()) {
            return PublishResult::fail(
                (string) (data_get($init->json(), 'error.message') ?? $init->body())
            );
        }

        $uploadUrl = $init->header('Location');

        if (! is_string($uploadUrl) || $uploadUrl === '') {
            return PublishResult::fail('YouTube did not return a resumable upload URL.');
        }

        $upload = Http::withToken($token)
            ->withBody($videoBytes, $this->contentTypeFor($videoUrl))
            ->put($uploadUrl);

        if (! $upload->successful()) {
            return PublishResult::fail(
                (string) (data_get($upload->json(), 'error.message') ?? $upload->body())
            );
        }

        $videoId = (string) data_get($upload->json(), 'id', '');

        if ($videoId === '') {
            return PublishResult::fail('YouTube did not return a video id after upload.');
        }

        return PublishResult::ok($videoId, ['response' => $upload->json()]);
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

    protected function shortsDescription(string $caption): string
    {
        $caption = trim($caption);

        if ($caption === '') {
            return '#Shorts';
        }

        if (stripos($caption, '#shorts') !== false) {
            return mb_substr($caption, 0, 5000);
        }

        return mb_substr($caption."\n\n#Shorts", 0, 5000);
    }

    protected function downloadMedia(string $url): ?string
    {
        $response = Http::timeout(120)->get($url);

        if (! $response->successful()) {
            return null;
        }

        return $response->body();
    }

    protected function looksLikeVideo(string $url): bool
    {
        $path = strtolower(parse_url($url, PHP_URL_PATH) ?? '');

        return str_ends_with($path, '.mp4')
            || str_ends_with($path, '.mov')
            || str_ends_with($path, '.webm')
            || str_contains($path, '/video');
    }

    protected function contentTypeFor(string $url): string
    {
        $path = strtolower(parse_url($url, PHP_URL_PATH) ?? '');

        return match (true) {
            str_ends_with($path, '.mov') => 'video/quicktime',
            str_ends_with($path, '.webm') => 'video/webm',
            default => 'video/mp4',
        };
    }

    protected function clientId(): string
    {
        return trim((string) config('social.providers.youtube.oauth.client_id', ''));
    }

    protected function clientSecret(): string
    {
        return trim((string) config('social.providers.youtube.oauth.client_secret', ''));
    }

    protected function tokenUrl(): string
    {
        return (string) config('social.providers.youtube.oauth.token_url', 'https://oauth2.googleapis.com/token');
    }

    protected function apiBase(): string
    {
        return rtrim((string) config('social.providers.youtube.oauth.api_base', 'https://www.googleapis.com'), '/');
    }
}
