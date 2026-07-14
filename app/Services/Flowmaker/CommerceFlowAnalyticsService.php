<?php

namespace App\Services\Flowmaker;

use Illuminate\Support\Facades\DB;
use Modules\Flowmaker\Models\FlowRunLog;

class CommerceFlowAnalyticsService
{
    /** @var list<string> */
    public const FUNNEL_EVENTS = [
        'catalog_link_sent',
        'catalog_product_selected',
        'catalog_checkout_started',
        'catalog_checkout_completed',
        'catalog_search_started',
        'catalog_search_matched',
        'catalog_search_no_match',
        'payment_initiated',
        'payment_succeeded',
        'payment_failed',
        'order_status_updated',
    ];

    /**
     * @return array<string, mixed>
     */
    public function summaryForFlow(int $flowId, int $days = 30): array
    {
        $since = now()->subDays($days);

        $counts = FlowRunLog::query()
            ->where('flow_id', $flowId)
            ->where('created_at', '>=', $since)
            ->whereIn('event', self::FUNNEL_EVENTS)
            ->select('event', DB::raw('count(*) as total'))
            ->groupBy('event')
            ->pluck('total', 'event')
            ->all();

        $started = (int) ($counts['catalog_link_sent'] ?? 0) + (int) ($counts['catalog_search_started'] ?? 0);
        $paid = (int) ($counts['payment_succeeded'] ?? 0);
        $checkout = (int) ($counts['catalog_checkout_completed'] ?? 0);

        return [
            'days' => $days,
            'funnel' => $counts,
            'conversion_rate' => $started > 0 ? round(($paid / $started) * 100, 1) : null,
            'totals' => [
                'started' => $started,
                'checkout_completed' => $checkout,
                'payment_succeeded' => $paid,
                'payment_failed' => (int) ($counts['payment_failed'] ?? 0),
                'product_selected' => (int) ($counts['catalog_product_selected'] ?? 0),
            ],
        ];
    }
}
