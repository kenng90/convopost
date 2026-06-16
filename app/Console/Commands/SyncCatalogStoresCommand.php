<?php

namespace App\Console\Commands;

use App\Models\ListCatalog;
use App\Services\Catalog\ApiCatalogImportService;
use App\Services\Catalog\CatalogInventoryService;
use App\Services\Catalog\CatalogStoreSyncService;
use Illuminate\Console\Command;

class SyncCatalogStoresCommand extends Command
{
    protected $signature = 'catalog:sync-stores {--company=}';

    protected $description = 'Pull catalog updates from linked Shopify/WooCommerce stores and API sources';

    public function handle(
        CatalogStoreSyncService $syncService,
        ApiCatalogImportService $apiImportService,
        CatalogInventoryService $inventoryService,
    ): int {
        $released = $inventoryService->releaseExpiredReservations();
        $this->info("Released {$released} expired inventory reservations.");

        $query = ListCatalog::withoutGlobalScopes()->whereNull('parent_id');

        if ($companyId = $this->option('company')) {
            $query->where('company_id', $companyId);
        }

        $synced = 0;

        $query->chunkById(50, function ($catalogs) use ($syncService, $apiImportService, &$synced) {
            foreach ($catalogs as $catalog) {
                $storeType = $catalog->metadata['store_source'] ?? null;

                try {
                    if (in_array($storeType, ['shopify', 'woocommerce'], true)) {
                        $syncService->pullFromStore($catalog, $storeType);
                        $synced++;
                    } elseif ($catalog->source === 'api' && is_array($catalog->api_config) && ! empty($catalog->api_config['url'])) {
                        $apiImportService->importIntoCatalog($catalog);
                        $synced++;
                    }
                } catch (\Throwable $e) {
                    $this->error("Catalog {$catalog->id}: {$e->getMessage()}");
                }
            }
        });

        $this->info("Synced {$synced} catalog(s).");

        return self::SUCCESS;
    }
}
