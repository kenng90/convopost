<?php

namespace App\Services\Catalog;

use App\Models\Company;
use App\Models\ListCatalog;
use App\Scopes\CompanyScope;
use Illuminate\Support\Facades\Log;
use Modules\Reminders\Models\Source;
use Modules\Reminders\Services\AvailabilityService;

class CatalogAvailabilitySyncService
{
    public function __construct(
        protected CatalogListingSlotBookingService $slotBookingService,
        protected AvailabilityService $availabilityService,
        protected CatalogItemRepository $catalogItemRepository,
    ) {
    }

    public function syncCatalog(ListCatalog $catalog, int $daysAhead = 14): int
    {
        $updated = 0;
        $company = Company::find($catalog->company_id);
        if (! $company) {
            return 0;
        }

        $items = $this->catalogItemRepository->getItemsArray($catalog);
        $changed = false;

        foreach ($items as &$item) {
            $source = $this->slotBookingService->resolveBookableSource($company, $item);
            if (! $source instanceof Source) {
                continue;
            }

            $from = now()->startOfDay();
            $to = now()->addDays(max(1, $daysAhead))->endOfDay();
            $dates = $this->availabilityService->availableDates($source, $from, $to);
            $availability = count($dates) > 0 ? 'Available' : 'Fully Booked';

            $current = (string) ($item['availability']
                ?? ($item['metadata']['availability'] ?? ''));

            if ($current === $availability) {
                continue;
            }

            $item['availability'] = $availability;
            $metadata = is_array($item['metadata'] ?? null) ? $item['metadata'] : [];
            $metadata['availability'] = $availability;
            $metadata['availability_synced_at'] = now()->toIso8601String();
            $metadata['next_available_dates'] = array_slice(array_map(
                fn ($date) => is_object($date) && method_exists($date, 'format')
                    ? $date->format('Y-m-d')
                    : (string) $date,
                $dates
            ), 0, 5);
            $item['metadata'] = $metadata;
            $changed = true;
            $updated++;
        }
        unset($item);

        if ($changed) {
            $this->catalogItemRepository->replaceAllFromArray($catalog, $items, true);
        }

        return $updated;
    }

    public function syncCompany(Company $company, int $daysAhead = 14): int
    {
        $total = 0;
        $catalogs = ListCatalog::withoutGlobalScope(CompanyScope::class)
            ->where('company_id', $company->id)
            ->whereIn('catalog_mode', ['service', 'listing'])
            ->get();

        foreach ($catalogs as $catalog) {
            try {
                $total += $this->syncCatalog($catalog, $daysAhead);
            } catch (\Throwable $e) {
                Log::warning('Catalog availability sync failed', [
                    'catalog_id' => $catalog->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $total;
    }

    public function syncItemBySource(Source $source): int
    {
        $company = Company::find($source->company_id);
        if (! $company) {
            return 0;
        }

        return $this->syncCompany($company);
    }
}
