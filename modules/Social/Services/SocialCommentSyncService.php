<?php

namespace Modules\Social\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\Social\Enums\SocialProvider;
use Modules\Social\Models\SocialComment;
use Modules\Social\Models\SocialPostAccount;

class SocialCommentSyncService
{
    /**
     * Pull comments for published Facebook / Instagram posts (read-only; no WhatsApp inbox).
     *
     * @return array{synced: int, failed: int, skipped: int, upserted: int}
     */
    public function syncDue(?int $companyId = null, int $limit = 50): array
    {
        $stats = ['synced' => 0, 'failed' => 0, 'skipped' => 0, 'upserted' => 0];

        $query = SocialPostAccount::query()
            ->with(['account', 'post'])
            ->where('status', 'published')
            ->whereNotNull('provider_post_id')
            ->where('provider_post_id', '!=', '')
            ->whereHas('account', fn ($q) => $q->whereIn('provider', ['facebook', 'instagram']))
            ->orderByDesc('published_at')
            ->limit($limit);

        if ($companyId) {
            $query->whereHas('post', fn ($q) => $q->where('company_id', $companyId));
        }

        foreach ($query->get() as $pivot) {
            try {
                $count = $this->syncPostAccount($pivot);

                if ($count === null) {
                    $stats['skipped']++;
                } else {
                    $stats['synced']++;
                    $stats['upserted'] += $count;
                }
            } catch (\Throwable $e) {
                report($e);
                $stats['failed']++;
                Log::warning('Social comment sync failed', [
                    'social_post_account_id' => $pivot->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $stats;
    }

    /**
     * @return int|null Number of comments upserted, or null when skipped
     */
    public function syncPostAccount(SocialPostAccount $pivot): ?int
    {
        $account = $pivot->account;
        $post = $pivot->post;

        if (! $account || ! $post || ! $pivot->provider_post_id) {
            return null;
        }

        $provider = SocialProvider::tryFromString($account->provider);

        if (! in_array($provider, [SocialProvider::Facebook, SocialProvider::Instagram], true)) {
            return null;
        }

        $token = $account->getAccessToken();

        if (! $token) {
            return null;
        }

        $comments = match ($provider) {
            SocialProvider::Facebook => $this->fetchFacebookComments($token, $pivot->provider_post_id),
            SocialProvider::Instagram => $this->fetchInstagramComments($token, $pivot->provider_post_id),
            default => null,
        };

        if ($comments === null) {
            return null;
        }

        $upserted = 0;

        foreach ($comments as $row) {
            SocialComment::withoutGlobalScopes()->updateOrCreate(
                [
                    'company_id' => $post->company_id,
                    'provider' => $provider->value,
                    'provider_comment_id' => $row['provider_comment_id'],
                ],
                [
                    'social_post_id' => $post->id,
                    'social_post_account_id' => $pivot->id,
                    'social_account_id' => $account->id,
                    'provider_post_id' => $pivot->provider_post_id,
                    'author_name' => $row['author_name'],
                    'author_username' => $row['author_username'],
                    'author_external_id' => $row['author_external_id'],
                    'body' => $row['body'],
                    'commented_at' => $row['commented_at'],
                    'raw' => $row['raw'],
                ]
            );
            $upserted++;
        }

        return $upserted;
    }

    /**
     * @return list<array{
     *     provider_comment_id: string,
     *     author_name: ?string,
     *     author_username: ?string,
     *     author_external_id: ?string,
     *     body: ?string,
     *     commented_at: ?\Illuminate\Support\Carbon,
     *     raw: array
     * }>|null
     */
    protected function fetchFacebookComments(string $token, string $providerPostId): ?array
    {
        $graph = rtrim((string) config('social.providers.facebook.oauth.graph_version', 'v21.0'), '/');

        $response = Http::get("https://graph.facebook.com/{$graph}/{$providerPostId}/comments", [
            'fields' => 'id,from{id,name},message,created_time',
            'limit' => 50,
            'access_token' => $token,
        ]);

        if (! $response->successful()) {
            Log::warning('Facebook comment fetch failed', [
                'provider_post_id' => $providerPostId,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return null;
        }

        $rows = [];

        foreach ($response->json('data', []) as $item) {
            $id = (string) ($item['id'] ?? '');

            if ($id === '') {
                continue;
            }

            $rows[] = [
                'provider_comment_id' => $id,
                'author_name' => data_get($item, 'from.name'),
                'author_username' => null,
                'author_external_id' => data_get($item, 'from.id') !== null
                    ? (string) data_get($item, 'from.id')
                    : null,
                'body' => isset($item['message']) ? (string) $item['message'] : null,
                'commented_at' => $this->parseProviderTime($item['created_time'] ?? null),
                'raw' => is_array($item) ? $item : [],
            ];
        }

        return $rows;
    }

    /**
     * @return list<array{
     *     provider_comment_id: string,
     *     author_name: ?string,
     *     author_username: ?string,
     *     author_external_id: ?string,
     *     body: ?string,
     *     commented_at: ?\Illuminate\Support\Carbon,
     *     raw: array
     * }>|null
     */
    protected function fetchInstagramComments(string $token, string $providerPostId): ?array
    {
        $graph = rtrim((string) config('social.providers.instagram.oauth.graph_version', 'v21.0'), '/');

        $response = Http::get("https://graph.facebook.com/{$graph}/{$providerPostId}/comments", [
            'fields' => 'id,text,username,timestamp,from',
            'limit' => 50,
            'access_token' => $token,
        ]);

        if (! $response->successful()) {
            Log::warning('Instagram comment fetch failed', [
                'provider_post_id' => $providerPostId,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return null;
        }

        $rows = [];

        foreach ($response->json('data', []) as $item) {
            $id = (string) ($item['id'] ?? '');

            if ($id === '') {
                continue;
            }

            $username = $item['username'] ?? data_get($item, 'from.username');

            $rows[] = [
                'provider_comment_id' => $id,
                'author_name' => data_get($item, 'from.name') ?: ($username ? (string) $username : null),
                'author_username' => $username !== null ? (string) $username : null,
                'author_external_id' => data_get($item, 'from.id') !== null
                    ? (string) data_get($item, 'from.id')
                    : null,
                'body' => isset($item['text']) ? (string) $item['text'] : null,
                'commented_at' => $this->parseProviderTime($item['timestamp'] ?? null),
                'raw' => is_array($item) ? $item : [],
            ];
        }

        return $rows;
    }

    protected function parseProviderTime(mixed $value): ?Carbon
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }
}
