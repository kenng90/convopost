<?php

namespace App\Services\Catalog;

use App\Models\Company;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class StoreProductFetcher
{
    /**
     * @return list<array{title: string, description: string, image: string|null, link: string, source: string, price?: float|null}>
     */
    public function fetch(Company $company, string $source = 'auto', bool $forceReload = false): array
    {
        $source = $this->resolveSource($company, $source);
        if ($source === null) {
            return [];
        }

        $cacheKey = 'store_products_'.$source.'_'.$company->id;
        if (! $forceReload && Cache::has($cacheKey)) {
            return Cache::get($cacheKey, []);
        }

        $products = $source === 'shopify'
            ? $this->fetchShopify($company)
            : $this->fetchWoo($company);

        Cache::put($cacheKey, $products, now()->addMinutes(30));

        return $products;
    }

    public function resolveSource(Company $company, string $requested = 'auto'): ?string
    {
        $hasShopify = filled($company->getConfig('shopify_access_token'));
        $hasWoo = filled($company->getConfig('woocommerce_consumer_key'));

        if ($requested === 'shopify' && $hasShopify) {
            return 'shopify';
        }
        if ($requested === 'woocommerce' && $hasWoo) {
            return 'woocommerce';
        }
        if ($requested === 'auto') {
            if ($hasShopify) {
                return 'shopify';
            }
            if ($hasWoo) {
                return 'woocommerce';
            }
        }

        return null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchShopify(Company $company): array
    {
        if (config('settings.is_demo')) {
            return [[
                'title' => 'Demo Shopify Product',
                'description' => 'KES 1000',
                'image' => null,
                'link' => 'https://example.com',
                'source' => 'shopify',
                'price' => 1000,
            ]];
        }

        $store = $company->getConfig('shopify_store_name');
        $version = $company->getConfig('shopify_api_version', '2024-01');
        $token = $company->getConfig('shopify_access_token');
        $currency = $company->getConfig('shopify_currency', 'KES');

        try {
            $response = Http::withHeaders([
                'X-Shopify-Access-Token' => $token,
            ])->get("https://{$store}/admin/api/{$version}/products.json", [
                'limit' => 50,
            ]);

            if (! $response->successful()) {
                return [];
            }

            $products = [];
            foreach ($response->json('products') ?? [] as $product) {
                $variant = $product['variants'][0] ?? [];
                $price = isset($variant['price']) ? (float) $variant['price'] : null;
                $products[] = [
                    'title' => (string) ($product['title'] ?? 'Product'),
                    'description' => $price !== null ? $currency.' '.$price : '',
                    'image' => $product['image']['src'] ?? ($product['images'][0]['src'] ?? null),
                    'link' => "https://{$store}/products/".($product['handle'] ?? ''),
                    'source' => 'shopify',
                    'price' => $price,
                    'external_id' => (string) ($product['id'] ?? ''),
                ];
            }

            return $products;
        } catch (\Throwable $e) {
            Log::warning('StoreProductFetcher Shopify failed', ['error' => $e->getMessage()]);

            return [];
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchWoo(Company $company): array
    {
        if (config('settings.is_demo')) {
            return [[
                'title' => 'Demo Woo Product',
                'description' => 'KES 1000',
                'image' => null,
                'link' => 'https://example.com',
                'source' => 'woocommerce',
                'price' => 1000,
            ]];
        }

        $storeUrl = rtrim((string) $company->getConfig('woocommerce_store_url'), '/');
        $key = $company->getConfig('woocommerce_consumer_key');
        $secret = $company->getConfig('woocommerce_consumer_secret');
        $currency = $company->getConfig('woocommerce_currency', 'KES');

        try {
            $response = Http::withBasicAuth($key, $secret)
                ->get($storeUrl.'/wp-json/wc/v3/products', ['per_page' => 50]);

            if (! $response->successful()) {
                return [];
            }

            $products = [];
            foreach ($response->json() ?? [] as $product) {
                $price = isset($product['price']) ? (float) $product['price'] : null;
                $products[] = [
                    'title' => (string) ($product['name'] ?? 'Product'),
                    'description' => $price !== null ? $currency.' '.$price : '',
                    'image' => $product['images'][0]['src'] ?? null,
                    'link' => (string) ($product['permalink'] ?? $storeUrl),
                    'source' => 'woocommerce',
                    'price' => $price,
                    'external_id' => (string) ($product['id'] ?? ''),
                ];
            }

            return $products;
        } catch (\Throwable $e) {
            Log::warning('StoreProductFetcher Woo failed', ['error' => $e->getMessage()]);

            return [];
        }
    }
}
