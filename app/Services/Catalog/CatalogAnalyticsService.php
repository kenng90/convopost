<?php

namespace App\Services\Catalog;

use App\Models\CatalogAnalyticsEvent;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class CatalogAnalyticsService
{
    public function record(int $companyId, int $catalogId, string $eventType, array $metadata = []): void
    {
        CatalogAnalyticsEvent::create([
            'company_id' => $companyId,
            'catalog_id' => $catalogId,
            'event_type' => $eventType,
            'metadata' => $metadata ?: null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function summary(int $catalogId, int $days = 30): array
    {
        $since = Carbon::now()->subDays($days);

        $counts = CatalogAnalyticsEvent::query()
            ->where('catalog_id', $catalogId)
            ->where('created_at', '>=', $since)
            ->select('event_type', DB::raw('count(*) as total'))
            ->groupBy('event_type')
            ->pluck('total', 'event_type')
            ->all();

        $dailyViews = CatalogAnalyticsEvent::query()
            ->where('catalog_id', $catalogId)
            ->where('event_type', CatalogAnalyticsEvent::TYPE_VIEW)
            ->where('created_at', '>=', $since)
            ->select(DB::raw('DATE(created_at) as day'), DB::raw('count(*) as total'))
            ->groupBy('day')
            ->orderBy('day')
            ->pluck('total', 'day')
            ->all();

        return [
            'period_days' => $days,
            'views' => (int) ($counts[CatalogAnalyticsEvent::TYPE_VIEW] ?? 0),
            'cart_adds' => (int) ($counts[CatalogAnalyticsEvent::TYPE_CART_ADD] ?? 0),
            'whatsapp_checkouts' => (int) ($counts[CatalogAnalyticsEvent::TYPE_CHECKOUT_WHATSAPP] ?? 0),
            'invoice_checkouts' => (int) ($counts[CatalogAnalyticsEvent::TYPE_CHECKOUT_INVOICE] ?? 0),
            'daily_views' => $dailyViews,
        ];
    }
}
