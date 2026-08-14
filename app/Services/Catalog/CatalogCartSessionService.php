<?php

namespace App\Services\Catalog;

use App\Models\CatalogCartSession;
use App\Models\ListCatalog;
use App\Services\Campaign\CampaignTriggerService;
use App\Services\Outcomes\OutcomeJourneyEnroller;
use Illuminate\Support\Carbon;

class CatalogCartSessionService
{
    /**
     * @param  list<array<string, mixed>>  $items
     */
    public function sync(
        ListCatalog $catalog,
        string $visitorKey,
        array $items,
        ?string $customerPhone = null,
        ?string $customerName = null,
        ?int $contactId = null,
    ): CatalogCartSession {
        $session = CatalogCartSession::withoutGlobalScopes()->updateOrCreate(
            [
                'catalog_id' => $catalog->id,
                'visitor_key' => $visitorKey,
            ],
            [
                'company_id' => $catalog->company_id,
                'contact_id' => $contactId,
                'items' => $items,
                'customer_phone' => $customerPhone,
                'customer_name' => $customerName,
                'last_activity_at' => now(),
                'abandoned_at' => null,
                'converted_at' => $items === [] ? null : null,
            ]
        );

        if ($items === []) {
            $session->update(['converted_at' => null, 'abandoned_at' => null]);
        }

        return $session;
    }

    /**
     * @param  list<array<string, mixed>>  $items
     */
    public function markAbandoned(
        ListCatalog $catalog,
        string $visitorKey,
        array $items,
        ?string $customerPhone = null,
        ?string $customerName = null,
    ): ?CatalogCartSession {
        if ($items === [] || ! $customerPhone) {
            return null;
        }

        $session = $this->sync($catalog, $visitorKey, $items, $customerPhone, $customerName);

        if ($session->converted_at) {
            return $session;
        }

        $session->update(['abandoned_at' => now()]);

        if ($catalog->company) {
            app(CampaignTriggerService::class)->fire(
                $catalog->company,
                CampaignTriggerService::EVENT_CART_ABANDONED,
                [
                    'phone' => $customerPhone,
                    'customer_phone' => $customerPhone,
                    'customer_name' => $customerName,
                    'catalog_id' => $catalog->id,
                    'catalog_name' => $catalog->name,
                    'item_count' => $session->itemCount(),
                ]
            );

            app(OutcomeJourneyEnroller::class)->enrollCartAbandoned(
                $catalog->company,
                $customerPhone,
                $customerName
            );
        }

        return $session->fresh();
    }

    public function markConverted(ListCatalog $catalog, string $visitorKey): void
    {
        $session = CatalogCartSession::withoutGlobalScopes()
            ->where('catalog_id', $catalog->id)
            ->where('visitor_key', $visitorKey)
            ->first();

        CatalogCartSession::withoutGlobalScopes()
            ->where('catalog_id', $catalog->id)
            ->where('visitor_key', $visitorKey)
            ->update([
                'converted_at' => now(),
                'abandoned_at' => null,
                'items' => [],
            ]);

        if ($session && $catalog->company && $session->customer_phone) {
            $contact = \Modules\Wpbox\Models\Contact::firstOrCreate(
                ['company_id' => $catalog->company_id, 'phone' => $session->customer_phone],
                ['name' => $session->customer_name ?: $session->customer_phone, 'subscribed' => 1]
            );
            app(OutcomeJourneyEnroller::class)->markCartRecovered($catalog->company, $contact);
        }
    }

    public function isStale(?Carbon $lastActivity, int $minutes = 30): bool
    {
        if (! $lastActivity) {
            return false;
        }

        return $lastActivity->lte(now()->subMinutes($minutes));
    }
}
