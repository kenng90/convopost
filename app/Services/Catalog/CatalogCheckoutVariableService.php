<?php

namespace App\Services\Catalog;

use App\Models\Company;
use App\Models\ListCatalog;
use Modules\Flowmaker\Models\Contact;
use Modules\Flowmaker\Models\Flow;

class CatalogCheckoutVariableService
{
    public const DEFAULT_PREFIX = 'catalog_order';

    public const LEGACY_CART_KEY = 'catalog_cart';

    public function resolvePrefix(?string $prefix): string
    {
        $clean = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $prefix);

        return $clean !== '' ? $clean : self::DEFAULT_PREFIX;
    }

    public function resolvePrefixFromFlowNode(Flow $flow, string $nodeId): string
    {
        $flowData = json_decode($flow->flow_data ?? '{}', true);
        if (! is_array($flowData)) {
            return self::DEFAULT_PREFIX;
        }

        foreach ($flowData['nodes'] ?? [] as $node) {
            if (! is_array($node) || ($node['id'] ?? '') !== $nodeId) {
                continue;
            }

            $settings = $node['data']['settings'] ?? [];

            return $this->resolvePrefix($settings['checkoutVariablePrefix'] ?? null);
        }

        return self::DEFAULT_PREFIX;
    }

    /**
     * @param  list<array<string, mixed>>  $cartItems
     * @return array{
     *     items: list<array<string, mixed>>,
     *     total: float,
     *     item_count: int,
     *     items_text: string,
     *     currency: string
     * }
     */
    public function enrichCartItems(ListCatalog $catalog, array $cartItems): array
    {
        $company = $catalog->company;
        $currencyService = app(CatalogCurrencyService::class);
        $currency = $currencyService->codeForCompany($company);

        $enrichedItems = [];
        $total = 0.0;
        $itemCount = 0;
        $lines = [];

        foreach ($cartItems as $cartItem) {
            $productId = (string) ($cartItem['id'] ?? '');
            $quantity = max(1, (int) ($cartItem['quantity'] ?? 1));
            $variant = $cartItem['variant'] ?? null;

            $product = $this->findProductInCatalog($catalog->items ?? [], $productId);
            $title = $product['title'] ?? $productId;
            $unitPrice = (float) ($product['price'] ?? 0);
            $lineTotal = $unitPrice * $quantity;
            $total += $lineTotal;
            $itemCount += $quantity;

            $variantSuffix = is_string($variant) && $variant !== '' ? " ({$variant})" : '';

            $enrichedItems[] = [
                'id' => $productId,
                'title' => $title,
                'quantity' => $quantity,
                'variant' => $variant,
                'unit_price' => $unitPrice,
                'line_total' => $lineTotal,
            ];

            $lines[] = $title.$variantSuffix.' (x'.$quantity.') - '.$currencyService->formatAmount($company, $lineTotal);
        }

        return [
            'items' => $enrichedItems,
            'total' => $total,
            'item_count' => $itemCount,
            'items_text' => implode(', ', $lines),
            'currency' => $currency,
        ];
    }

    /**
     * @param  array{
     *     items: list<array<string, mixed>>,
     *     total: float,
     *     item_count: int,
     *     items_text: string,
     *     currency: string
     * }  $enriched
     * @return array<string, string>
     */
    public function buildVariableMap(
        string $prefix,
        array $enriched,
        Company $company,
        ?string $orderMessage = null
    ): array {
        $currencyService = app(CatalogCurrencyService::class);
        $formattedTotal = $currencyService->formatAmount($company, $enriched['total']);

        return [
            $prefix.'_items' => $enriched['items_text'],
            $prefix.'_total' => $formattedTotal,
            $prefix.'_item_count' => (string) $enriched['item_count'],
            $prefix.'_json' => json_encode($enriched['items'], JSON_THROW_ON_ERROR),
            $prefix.'_message' => $orderMessage ?? '',
            self::LEGACY_CART_KEY => json_encode($enriched['items'], JSON_THROW_ON_ERROR),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $cartItems
     */
    public function storeOnContact(
        Contact $contact,
        int $flowId,
        string $prefix,
        ListCatalog $catalog,
        array $cartItems,
        ?string $orderMessage = null
    ): void {
        if ($cartItems === []) {
            return;
        }

        $enriched = $this->enrichCartItems($catalog, $cartItems);
        $variables = $this->buildVariableMap($prefix, $enriched, $catalog->company, $orderMessage);

        foreach ($variables as $name => $value) {
            $contact->setContactState($flowId, $name, $value);
        }
    }

    /**
     * @param  list<array<string, mixed>>  $items
     */
    private function findProductInCatalog(array $items, string $productId): ?array
    {
        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            if (($item['id'] ?? null) === $productId) {
                return $item;
            }
        }

        return null;
    }
}
