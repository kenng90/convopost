<?php

namespace Modules\Social\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\Social\Enums\SocialProvider;
use Modules\Social\Models\SocialPostAccount;
use Modules\Social\Models\SocialPostAnalyticsSnapshot;

class SocialAnalyticsSyncService
{
    /**
     * @return array{synced: int, failed: int, skipped: int}
     */
    public function syncDue(?int $companyId = null, int $limit = 100): array
    {
        $stats = ['synced' => 0, 'failed' => 0, 'skipped' => 0];

        $query = SocialPostAccount::query()
            ->with(['account', 'post'])
            ->where('status', 'published')
            ->whereNotNull('provider_post_id')
            ->where('provider_post_id', '!=', '')
            ->orderByDesc('published_at')
            ->limit($limit);

        if ($companyId) {
            $query->whereHas('post', fn ($q) => $q->where('company_id', $companyId));
        }

        foreach ($query->get() as $pivot) {
            try {
                if ($this->syncPostAccount($pivot)) {
                    $stats['synced']++;
                } else {
                    $stats['skipped']++;
                }
            } catch (\Throwable $e) {
                report($e);
                $stats['failed']++;
                Log::warning('Social analytics sync failed', [
                    'social_post_account_id' => $pivot->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $stats;
    }

    public function syncPostAccount(SocialPostAccount $pivot): bool
    {
        $account = $pivot->account;
        $post = $pivot->post;

        if (! $account || ! $post || ! $pivot->provider_post_id) {
            return false;
        }

        $provider = SocialProvider::tryFromString($account->provider);

        if ($provider === null) {
            return false;
        }

        $metrics = match ($provider) {
            SocialProvider::Facebook => $this->fetchFacebookMetrics($account->getAccessToken(), $pivot->provider_post_id),
            SocialProvider::Instagram => $this->fetchInstagramMetrics($account->getAccessToken(), $pivot->provider_post_id),
            SocialProvider::LinkedIn => $this->fetchLinkedInMetrics($account->getAccessToken(), $pivot->provider_post_id),
            default => null,
        };

        if ($metrics === null) {
            return false;
        }

        SocialPostAnalyticsSnapshot::query()->create([
            'company_id' => $post->company_id,
            'social_post_id' => $post->id,
            'social_post_account_id' => $pivot->id,
            'provider' => $provider->value,
            'provider_post_id' => $pivot->provider_post_id,
            'impressions' => $metrics['impressions'],
            'reach' => $metrics['reach'],
            'likes' => $metrics['likes'],
            'comments' => $metrics['comments'],
            'shares' => $metrics['shares'],
            'clicks' => $metrics['clicks'],
            'engagement' => $metrics['engagement'],
            'raw' => $metrics['raw'],
            'synced_at' => now(),
        ]);

        return true;
    }

    /**
     * @return array{impressions: int, reach: int, likes: int, comments: int, shares: int, clicks: int, engagement: int, raw: array}|null
     */
    protected function fetchFacebookMetrics(?string $token, string $providerPostId): ?array
    {
        if (! $token) {
            return null;
        }

        $graph = rtrim((string) config('social.providers.facebook.oauth.graph_version', 'v21.0'), '/');
        $response = Http::timeout(30)->get(
            "https://graph.facebook.com/{$graph}/{$providerPostId}/insights",
            [
                'metric' => 'post_impressions,post_impressions_unique,post_engaged_users,post_clicks',
                'access_token' => $token,
            ]
        );

        if (! $response->successful()) {
            Log::info('Facebook insights unavailable', [
                'post_id' => $providerPostId,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return null;
        }

        $data = $response->json('data') ?? [];
        $byName = collect($data)->keyBy('name');

        $impressions = (int) data_get($byName->get('post_impressions'), 'values.0.value', 0);
        $reach = (int) data_get($byName->get('post_impressions_unique'), 'values.0.value', 0);
        $engagement = (int) data_get($byName->get('post_engaged_users'), 'values.0.value', 0);
        $clicks = (int) data_get($byName->get('post_clicks'), 'values.0.value', 0);

        return [
            'impressions' => $impressions,
            'reach' => $reach,
            'likes' => 0,
            'comments' => 0,
            'shares' => 0,
            'clicks' => $clicks,
            'engagement' => $engagement,
            'raw' => $response->json() ?? [],
        ];
    }

    /**
     * @return array{impressions: int, reach: int, likes: int, comments: int, shares: int, clicks: int, engagement: int, raw: array}|null
     */
    protected function fetchInstagramMetrics(?string $token, string $providerPostId): ?array
    {
        if (! $token) {
            return null;
        }

        $graph = rtrim((string) config('social.providers.instagram.oauth.graph_version', 'v21.0'), '/');
        $response = Http::timeout(30)->get(
            "https://graph.facebook.com/{$graph}/{$providerPostId}/insights",
            [
                'metric' => 'impressions,reach,engagement,saved',
                'access_token' => $token,
            ]
        );

        if (! $response->successful()) {
            Log::info('Instagram insights unavailable', [
                'media_id' => $providerPostId,
                'status' => $response->status(),
            ]);

            return null;
        }

        $data = $response->json('data') ?? [];
        $byName = collect($data)->keyBy('name');

        $impressions = (int) data_get($byName->get('impressions'), 'values.0.value', 0);
        $reach = (int) data_get($byName->get('reach'), 'values.0.value', 0);
        $engagement = (int) data_get($byName->get('engagement'), 'values.0.value', 0);

        return [
            'impressions' => $impressions,
            'reach' => $reach,
            'likes' => 0,
            'comments' => 0,
            'shares' => 0,
            'clicks' => 0,
            'engagement' => $engagement,
            'raw' => $response->json() ?? [],
        ];
    }

    /**
     * LinkedIn organic analytics require partner APIs; record a placeholder zero snapshot
     * so the pipeline stays consistent until fuller LinkedIn insights are enabled.
     *
     * @return array{impressions: int, reach: int, likes: int, comments: int, shares: int, clicks: int, engagement: int, raw: array}|null
     */
    protected function fetchLinkedInMetrics(?string $token, string $providerPostId): ?array
    {
        if (! $token || $providerPostId === '') {
            return null;
        }

        return [
            'impressions' => 0,
            'reach' => 0,
            'likes' => 0,
            'comments' => 0,
            'shares' => 0,
            'clicks' => 0,
            'engagement' => 0,
            'raw' => ['note' => 'linkedin_insights_pending', 'provider_post_id' => $providerPostId],
        ];
    }
}
