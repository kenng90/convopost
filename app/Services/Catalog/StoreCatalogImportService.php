<?php

namespace App\Services\Catalog;

use App\Models\Company;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class StoreCatalogImportService
{
    public function __construct(
        protected CatalogCategoryNormalizer $categoryNormalizer,
    ) {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function fetchShopifyItems(Company $company): array
    {
        $storeName = $company->getConfig('shopify_store_name');
        $apiVersion = $company->getConfig('shopify_api_version', '2024-10');
        $accessToken = $company->getConfig('shopify_access_token');

        if (config('settings.is_demo', false)) {
            $storeName = 'vbz32s-vz';
            $apiVersion = '2024-10';
            $accessToken = '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';
        }

        if (empty($storeName) || empty($accessToken)) {
            throw new \RuntimeException('Shopify is not configured. Add your store credentials in Apps → Shopify.');
        }

        $productsUrl = "https://{$storeName}.myshopify.com/admin/api/{$apiVersion}/products.json?limit=250";
        $response = Http::withHeaders([
            'X-Shopify-Access-Token' => $accessToken,
        ])->timeout(30)->get($productsUrl);

        if (! $response->successful()) {
            Log::error('Shopify catalog import failed', ['status' => $response->status(), 'body' => $response->body()]);
            throw new \RuntimeException('Could not fetch products from Shopify (HTTP '.$response->status().').');
        }

        $products = $response->json('products') ?? [];

        return $this->mapShopifyProducts($products);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function fetchWooCommerceItems(Company $company): array
    {
        $storeUrl = rtrim((string) $company->getConfig('woocommerce_store_url'), '/');
        $consumerKey = $company->getConfig('woocommerce_consumer_key');
        $consumerSecret = $company->getConfig('woocommerce_consumer_secret');

        if (config('settings.is_demo', false)) {
            $storeUrl = 'https://woo.mobidonia.com';
            $consumerKey = 'ck_aa1a18c723f5d1cf6e1f739df69dc49ea601d09e';
            $consumerSecret = 'cs_95a9976b9b256afb631ba5d34aca7987cb9ed523';
        }

        if (empty($storeUrl) || empty($consumerKey) || empty($consumerSecret)) {
            throw new \RuntimeException('WooCommerce is not configured. Add your store credentials in Apps → WooCommerce.');
        }

        $url = $storeUrl.'/wp-json/wc/v3/products?per_page=100&consumer_key='.$consumerKey.'&consumer_secret='.$consumerSecret;
        $response = Http::timeout(30)->get($url);

        if (! $response->successful()) {
            Log::error('WooCommerce catalog import failed', ['status' => $response->status(), 'body' => $response->body()]);
            throw new \RuntimeException('Could not fetch products from WooCommerce (HTTP '.$response->status().').');
        }

        $products = $response->json();
        if (! is_array($products)) {
            throw new \RuntimeException('Unexpected response from WooCommerce.');
        }

        return $this->mapWooCommerceProducts($products);
    }

    /**
     * @param  list<array<string, mixed>>  $products
     * @return list<array<string, mixed>>
     */
    private function mapShopifyProducts(array $products): array
    {
        $items = [];

        foreach ($products as $product) {
            $variant = $product['variants'][0] ?? [];
            $image = $product['images'][0]['src'] ?? '';
            $variants = [];

            foreach ($product['variants'] ?? [] as $v) {
                if (! empty($v['title']) && $v['title'] !== 'Default Title') {
                    $variants[] = $v['title'];
                }
            }

            $stock = 'In Stock';
            if (($variant['inventory_quantity'] ?? 1) <= 0 && ($variant['inventory_management'] ?? null)) {
                $stock = 'Out of Stock';
            }

            $items[] = [
                'id' => (string) ($variant['sku'] ?: $product['id']),
                'title' => (string) ($product['title'] ?? 'Product'),
                'description' => trim(strip_tags((string) ($product['body_html'] ?? ''))),
                'price' => (float) ($variant['price'] ?? 0),
                'category' => (string) ($product['product_type'] ?? ''),
                'imageUrl' => (string) $image,
                'stockStatus' => $stock,
                'quantityAvailable' => isset($variant['inventory_quantity']) ? (int) $variant['inventory_quantity'] : null,
                'variants' => array_values(array_unique($variants)),
                'tags' => array_values(array_filter(array_map('trim', explode(',', (string) ($product['tags'] ?? ''))))),
                'metadata' => [
                    'external_product_id' => (string) $product['id'],
                    'external_variant_id' => (string) ($variant['id'] ?? ''),
                    'external_inventory_item_id' => (string) ($variant['inventory_item_id'] ?? ''),
                ],
            ];
        }

        return $this->categoryNormalizer->normalizeItems($items);
    }

    /**
     * @param  list<array<string, mixed>>  $products
     * @return list<array<string, mixed>>
     */
    private function mapWooCommerceProducts(array $products): array
    {
        $items = [];

        foreach ($products as $product) {
            if (! is_array($product)) {
                continue;
            }

            $stock = match ($product['stock_status'] ?? 'instock') {
                'outofstock' => 'Out of Stock',
                'onbackorder' => 'Low Stock',
                default => 'In Stock',
            };

            $items[] = [
                'id' => (string) ($product['sku'] ?: $product['id']),
                'title' => (string) ($product['name'] ?? 'Product'),
                'description' => trim(strip_tags((string) ($product['short_description'] ?? $product['description'] ?? ''))),
                'price' => (float) ($product['price'] ?? 0),
                'category' => (string) (($product['categories'][0]['name'] ?? '') ?: ''),
                'imageUrl' => (string) ($product['images'][0]['src'] ?? ''),
                'stockStatus' => $stock,
                'quantityAvailable' => isset($product['stock_quantity']) ? (int) $product['stock_quantity'] : null,
                'variants' => [],
                'tags' => array_values(array_map(fn ($tag) => (string) ($tag['name'] ?? ''), $product['tags'] ?? [])),
                'metadata' => [
                    'external_product_id' => (string) $product['id'],
                ],
            ];
        }

        return $this->categoryNormalizer->normalizeItems($items);
    }
}
