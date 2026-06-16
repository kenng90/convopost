<?php

namespace App\Services\Catalog;

use App\Models\CatalogItem;
use App\Models\CatalogSyncState;
use App\Models\Company;
use App\Models\ListCatalog;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CatalogStoreSyncService
{
    public function __construct(
        protected StoreCatalogImportService $storeImportService,
        protected CatalogItemRepository $itemRepository,
        protected CatalogReimportService $reimportService,
    ) {
    }

    public function pullFromStore(ListCatalog $catalog, string $storeType): array
    {
        $company = Company::findOrFail($catalog->company_id);
        $items = $storeType === 'shopify'
            ? $this->storeImportService->fetchShopifyItems($company)
            : $this->storeImportService->fetchWooCommerceItems($company);

        $merge = $this->reimportService->mergeByItemId(
            $this->itemRepository->getItemsArray($catalog),
            $items,
            removeMissing: true
        );

        $this->itemRepository->replaceAllFromArray($catalog, $merge['items']);
        $this->linkStoreProducts($catalog, $storeType, $items);

        $state = CatalogSyncState::updateOrCreate(
            ['catalog_id' => $catalog->id, 'store_type' => $storeType],
            [
                'company_id' => $catalog->company_id,
                'status' => 'idle',
                'last_pulled_at' => now(),
                'last_error' => null,
            ]
        );

        return [
            'stats' => $merge,
            'sync_state' => $state,
            'item_count' => count($merge['items']),
        ];
    }

    public function pushInventoryForItem(CatalogItem $item): void
    {
        $links = $item->storeLinks()->get();
        $company = Company::find($item->company_id);
        if (! $company) {
            return;
        }

        foreach ($links as $link) {
            try {
                if ($link->store_type === 'shopify') {
                    $this->pushShopifyInventory($company, $link, $item);
                } elseif ($link->store_type === 'woocommerce') {
                    $this->pushWooCommerceInventory($company, $link, $item);
                }
            } catch (\Throwable $e) {
                Log::warning('Catalog inventory push failed', [
                    'item_id' => $item->id,
                    'store' => $link->store_type,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    public function registerWebhooks(Company $company, string $storeType): array
    {
        $token = $company->getConfig('plain_token', '');
        if ($token === '') {
            return ['success' => false, 'message' => 'Company API token missing'];
        }

        $callbackUrl = route('catalog.webhooks.'.$storeType, ['token' => $token]);

        if ($storeType === 'shopify') {
            return $this->registerShopifyWebhooks($company, $callbackUrl);
        }

        return $this->registerWooCommerceWebhooks($company, $callbackUrl);
    }

    /**
     * @param  list<array<string, mixed>>  $storeItems
     */
    public function linkStoreProducts(ListCatalog $catalog, string $storeType, array $storeItems): void
    {
        foreach ($storeItems as $row) {
            $item = $this->itemRepository->findByCatalogItemId($catalog, (string) $row['id']);
            if (! $item) {
                continue;
            }

            $item->storeLinks()->updateOrCreate(
                [
                    'store_type' => $storeType,
                    'external_product_id' => (string) ($row['metadata']['external_product_id'] ?? $row['id']),
                    'company_id' => $catalog->company_id,
                ],
                [
                    'external_variant_id' => $row['metadata']['external_variant_id'] ?? null,
                    'external_inventory_item_id' => $row['metadata']['external_inventory_item_id'] ?? null,
                    'metadata' => $row['metadata'] ?? null,
                ]
            );
        }
    }

    private function pushShopifyInventory(Company $company, $link, CatalogItem $item): void
    {
        $inventoryItemId = $link->external_inventory_item_id;
        $locationId = $company->getConfig('shopify_location_id');
        $token = $company->getConfig('shopify_access_token');
        $store = $company->getConfig('shopify_store_name');
        $version = $company->getConfig('shopify_api_version', '2024-10');

        if (! $inventoryItemId || ! $locationId || ! $token || ! $store) {
            return;
        }

        $available = $item->quantity_available ?? 0;

        Http::withHeaders(['X-Shopify-Access-Token' => $token])
            ->post("https://{$store}.myshopify.com/admin/api/{$version}/inventory_levels/set.json", [
                'location_id' => $locationId,
                'inventory_item_id' => $inventoryItemId,
                'available' => $available,
            ]);
    }

    private function pushWooCommerceInventory(Company $company, $link, CatalogItem $item): void
    {
        $storeUrl = rtrim((string) $company->getConfig('woocommerce_store_url'), '/');
        $key = $company->getConfig('woocommerce_consumer_key');
        $secret = $company->getConfig('woocommerce_consumer_secret');
        $productId = $link->external_product_id;

        if (! $storeUrl || ! $key || ! $secret || ! $productId) {
            return;
        }

        $url = "{$storeUrl}/wp-json/wc/v3/products/{$productId}?consumer_key={$key}&consumer_secret={$secret}";

        Http::put($url, [
            'stock_quantity' => $item->quantity_available,
            'manage_stock' => $item->quantity_available !== null,
            'stock_status' => $item->stock_status === CatalogItem::STOCK_OUT ? 'outofstock' : 'instock',
        ]);
    }

    private function registerShopifyWebhooks(Company $company, string $callbackUrl): array
    {
        $store = $company->getConfig('shopify_store_name');
        $token = $company->getConfig('shopify_access_token');
        $version = $company->getConfig('shopify_api_version', '2024-10');

        if (! $store || ! $token) {
            return ['success' => false, 'message' => 'Shopify not configured'];
        }

        $topics = ['products/create', 'products/update', 'products/delete', 'inventory_levels/update'];
        $ids = [];

        foreach ($topics as $topic) {
            $response = Http::withHeaders(['X-Shopify-Access-Token' => $token])
                ->post("https://{$store}.myshopify.com/admin/api/{$version}/webhooks.json", [
                    'webhook' => [
                        'topic' => $topic,
                        'address' => $callbackUrl,
                        'format' => 'json',
                    ],
                ]);

            if ($response->successful()) {
                $ids[] = $response->json('webhook.id');
            }
        }

        $company->setConfig('catalog_shopify_webhook_ids', json_encode($ids));

        return ['success' => true, 'webhook_ids' => $ids];
    }

    private function registerWooCommerceWebhooks(Company $company, string $callbackUrl): array
    {
        $storeUrl = rtrim((string) $company->getConfig('woocommerce_store_url'), '/');
        $key = $company->getConfig('woocommerce_consumer_key');
        $secret = $company->getConfig('woocommerce_consumer_secret');

        if (! $storeUrl || ! $key || ! $secret) {
            return ['success' => false, 'message' => 'WooCommerce not configured'];
        }

        $topics = ['product.created', 'product.updated', 'product.deleted'];
        $ids = [];

        foreach ($topics as $topic) {
            $url = "{$storeUrl}/wp-json/wc/v3/webhooks?consumer_key={$key}&consumer_secret={$secret}";
            $response = Http::post($url, [
                'name' => 'Convocon Catalog '.$topic,
                'topic' => $topic,
                'delivery_url' => $callbackUrl,
            ]);

            if ($response->successful()) {
                $ids[] = $response->json('id');
            }
        }

        $company->setConfig('catalog_woocommerce_webhook_ids', json_encode($ids));

        return ['success' => true, 'webhook_ids' => $ids];
    }
}
