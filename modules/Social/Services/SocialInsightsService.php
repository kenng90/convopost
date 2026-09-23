<?php

namespace Modules\Social\Services;

use App\Models\Company;
use Illuminate\Support\Collection;
use Modules\Invoice\Models\Invoice;
use Modules\Social\Models\SocialOfferLink;
use Modules\Social\Models\SocialPost;
use Modules\Social\Models\SocialPostAnalyticsSnapshot;

class SocialInsightsService
{
    /**
     * @return array{
     *     totals: array{impressions: int, reach: int, engagement: int, clicks: int, orders: int, revenue: float},
     *     posts: Collection<int, array<string, mixed>>
     * }
     */
    public function forCompany(Company $company, int $limit = 50): array
    {
        $posts = SocialPost::query()
            ->withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->with(['defaultVersion'])
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        $postIds = $posts->pluck('id')->all();

        $latestSnapshots = SocialPostAnalyticsSnapshot::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->whereIn('social_post_id', $postIds)
            ->orderByDesc('synced_at')
            ->orderByDesc('id')
            ->get()
            ->groupBy('social_post_id')
            ->map(function (Collection $rows) {
                return $rows->groupBy('provider')->map->first()->values();
            });

        $clickCounts = SocialOfferLink::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->whereIn('social_post_id', $postIds)
            ->get(['social_post_id', 'click_count'])
            ->keyBy('social_post_id');

        $invoices = Invoice::query()
            ->where('company_id', $company->id)
            ->whereIn('social_post_id', $postIds)
            ->get(['social_post_id', 'amount', 'status']);

        $ordersByPost = $invoices->groupBy('social_post_id');

        $rows = $posts->map(function (SocialPost $post) use ($latestSnapshots, $clickCounts, $ordersByPost) {
            $snapshots = $latestSnapshots->get($post->id, collect());
            $impressions = (int) $snapshots->sum('impressions');
            $reach = (int) $snapshots->sum('reach');
            $engagement = (int) $snapshots->sum('engagement');
            $analyticsClicks = (int) $snapshots->sum('clicks');
            $offerClicks = (int) ($clickCounts->get($post->id)?->click_count ?? 0);

            $postInvoices = $ordersByPost->get($post->id, collect());
            $orders = $postInvoices->count();
            $revenue = (float) $postInvoices->sum(fn ($invoice) => (float) $invoice->amount);

            return [
                'post' => $post,
                'impressions' => $impressions,
                'reach' => $reach,
                'engagement' => $engagement,
                'clicks' => max($analyticsClicks, $offerClicks),
                'offer_clicks' => $offerClicks,
                'orders' => $orders,
                'revenue' => $revenue,
                'providers' => $snapshots->pluck('provider')->unique()->values()->all(),
            ];
        });

        return [
            'totals' => [
                'impressions' => (int) $rows->sum('impressions'),
                'reach' => (int) $rows->sum('reach'),
                'engagement' => (int) $rows->sum('engagement'),
                'clicks' => (int) $rows->sum('clicks'),
                'orders' => (int) $rows->sum('orders'),
                'revenue' => (float) $rows->sum('revenue'),
            ],
            'posts' => $rows,
        ];
    }
}
