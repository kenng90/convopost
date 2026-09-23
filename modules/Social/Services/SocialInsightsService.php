<?php

namespace Modules\Social\Services;

use App\Models\Company;
use Illuminate\Support\Collection;
use Modules\Invoice\Models\Invoice;
use Modules\Social\Models\SocialAccount;
use Modules\Social\Models\SocialComment;
use Modules\Social\Models\SocialOfferLink;
use Modules\Social\Models\SocialPost;
use Modules\Social\Models\SocialPostAnalyticsSnapshot;

class SocialInsightsService
{
    /**
     * @return array{
     *     content: array{published_posts: int, connected_accounts: int, impressions: int, reach: int, engagement: int, comments: int, clicks: int},
     *     commerce: array{offer_clicks: int, orders: int, paid_orders: int, revenue: float, conversion_rate: float, average_order_value: float},
     *     totals: array{impressions: int, reach: int, engagement: int, clicks: int, orders: int, revenue: float},
     *     posts: Collection<int, array<string, mixed>>,
     *     posts_that_sold: Collection<int, array<string, mixed>>
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

        $commentCounts = SocialComment::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->whereIn('social_post_id', $postIds)
            ->selectRaw('social_post_id, COUNT(*) as aggregate')
            ->groupBy('social_post_id')
            ->pluck('aggregate', 'social_post_id');

        $rows = $posts->map(function (SocialPost $post) use ($latestSnapshots, $clickCounts, $ordersByPost, $commentCounts) {
            $snapshots = $latestSnapshots->get($post->id, collect());
            $impressions = (int) $snapshots->sum('impressions');
            $reach = (int) $snapshots->sum('reach');
            $engagement = (int) $snapshots->sum('engagement');
            $analyticsClicks = (int) $snapshots->sum('clicks');
            $offerClicks = (int) ($clickCounts->get($post->id)?->click_count ?? 0);
            $comments = (int) ($commentCounts->get($post->id) ?? 0);

            $postInvoices = $ordersByPost->get($post->id, collect());
            $paidInvoices = $postInvoices->where('status', 'paid');
            $orders = $postInvoices->count();
            $paidOrders = $paidInvoices->count();
            $revenue = (float) $paidInvoices->sum(fn ($invoice) => (float) $invoice->amount);

            return [
                'post' => $post,
                'impressions' => $impressions,
                'reach' => $reach,
                'engagement' => $engagement,
                'comments' => $comments,
                'clicks' => max($analyticsClicks, $offerClicks),
                'offer_clicks' => $offerClicks,
                'orders' => $orders,
                'paid_orders' => $paidOrders,
                'revenue' => $revenue,
                'providers' => $snapshots->pluck('provider')->unique()->values()->all(),
            ];
        });

        $publishedPosts = SocialPost::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('status', 'published')
            ->count();

        $connectedAccounts = SocialAccount::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->whereNull('deleted_at')
            ->where('status', 'active')
            ->count();

        $offerClicks = (int) $rows->sum('offer_clicks');
        $paidOrders = (int) $rows->sum('paid_orders');
        $revenue = (float) $rows->sum('revenue');
        $conversionRate = $offerClicks > 0
            ? round(($paidOrders / $offerClicks) * 100, 2)
            : 0.0;
        $averageOrderValue = $paidOrders > 0
            ? round($revenue / $paidOrders, 2)
            : 0.0;

        $content = [
            'published_posts' => $publishedPosts,
            'connected_accounts' => $connectedAccounts,
            'impressions' => (int) $rows->sum('impressions'),
            'reach' => (int) $rows->sum('reach'),
            'engagement' => (int) $rows->sum('engagement'),
            'comments' => (int) $rows->sum('comments'),
            'clicks' => (int) $rows->sum('clicks'),
        ];

        $commerce = [
            'offer_clicks' => $offerClicks,
            'orders' => (int) $rows->sum('orders'),
            'paid_orders' => $paidOrders,
            'revenue' => $revenue,
            'conversion_rate' => $conversionRate,
            'average_order_value' => $averageOrderValue,
        ];

        $postsThatSold = $rows
            ->filter(fn (array $row) => $row['paid_orders'] > 0)
            ->sortByDesc('revenue')
            ->values();

        return [
            'content' => $content,
            'commerce' => $commerce,
            // Backward-compatible totals used by older callers / tests.
            'totals' => [
                'impressions' => $content['impressions'],
                'reach' => $content['reach'],
                'engagement' => $content['engagement'],
                'clicks' => $content['clicks'],
                'orders' => $commerce['paid_orders'],
                'revenue' => $commerce['revenue'],
            ],
            'posts' => $rows,
            'posts_that_sold' => $postsThatSold,
        ];
    }
}
