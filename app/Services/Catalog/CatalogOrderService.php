<?php

namespace App\Services\Catalog;

use App\Models\CatalogOrder;
use App\Models\CatalogOrderItem;
use App\Models\Company;
use App\Models\ListCatalog;
use App\Services\Campaign\CampaignTriggerService;
use Illuminate\Support\Facades\DB;
use Modules\Invoice\Models\Invoice;

class CatalogOrderService
{
    public function __construct(
        protected CatalogCurrencyService $currencyService,
        protected CatalogFlowCallbackService $flowCallbackService,
    ) {
    }

    /**
     * @param  list<array<string, mixed>>  $cartItems
     * @param  list<array<string, mixed>>  $catalogItems
     */
    public function createFromCheckout(
        ListCatalog $catalog,
        array $cartItems,
        array $catalogItems,
        string $channel,
        array $details = [],
        ?Invoice $invoice = null,
    ): CatalogOrder {
        $resolvedItems = $this->resolveLineItems($cartItems, $catalogItems);
        $total = array_sum(array_column($resolvedItems, 'line_total'));
        $flowContext = $this->flowCallbackService->decodeToken($details['flow_token'] ?? null);

        return DB::transaction(function () use ($catalog, $resolvedItems, $total, $channel, $details, $invoice, $flowContext) {
            $order = CatalogOrder::withoutGlobalScopes()->create([
                'company_id' => $catalog->company_id,
                'catalog_id' => $catalog->id,
                'contact_id' => $flowContext['contact_id'] ?? null,
                'invoice_id' => $invoice?->id,
                'flow_id' => $flowContext['flow_id'] ?? null,
                'order_number' => $this->nextOrderNumber($catalog->company),
                'status' => $channel === CatalogOrder::CHANNEL_INVOICE
                    ? CatalogOrder::STATUS_PENDING
                    : CatalogOrder::STATUS_CONFIRMED,
                'checkout_channel' => $channel,
                'customer_name' => $details['customer_name'] ?? null,
                'customer_phone' => $details['customer_phone'] ?? null,
                'delivery_address' => $details['delivery_address'] ?? null,
                'notes' => $details['notes'] ?? null,
                'total_amount' => $total,
                'currency' => $this->currencyService->codeForCompany($catalog->company),
                'metadata' => [
                    'order_message' => $details['order_message'] ?? null,
                    'item_count' => count($resolvedItems),
                ],
            ]);

            foreach ($resolvedItems as $line) {
                CatalogOrderItem::query()->create([
                    'catalog_order_id' => $order->id,
                    'item_id' => $line['item_id'],
                    'title' => $line['title'],
                    'quantity' => $line['quantity'],
                    'unit_price' => $line['unit_price'],
                    'line_total' => $line['line_total'],
                    'variant' => $line['variant'],
                    'metadata' => $line['metadata'],
                ]);
            }

            if ($catalog->company && ($details['customer_phone'] ?? null)) {
                app(CampaignTriggerService::class)->fire(
                    $catalog->company,
                    CampaignTriggerService::EVENT_ORDER_CREATED,
                    [
                        'phone' => $details['customer_phone'],
                        'customer_phone' => $details['customer_phone'],
                        'customer_name' => $details['customer_name'] ?? null,
                        'order_number' => $order->order_number,
                        'total_amount' => $order->total_amount,
                        'currency' => $order->currency,
                    ]
                );
            }

            return $order->load('items');
        });
    }

    /**
     * @param  list<array<string, mixed>>  $cartItems
     * @param  list<array<string, mixed>>  $catalogItems
     * @return list<array<string, mixed>>
     */
    public function resolveLineItems(array $cartItems, array $catalogItems): array
    {
        $byId = [];
        foreach ($catalogItems as $item) {
            $byId[(string) ($item['id'] ?? '')] = $item;
        }

        $lines = [];
        foreach ($cartItems as $cartItem) {
            $id = (string) ($cartItem['id'] ?? '');
            $product = $byId[$id] ?? null;
            if (! $product) {
                continue;
            }

            $qty = max(1, (int) ($cartItem['quantity'] ?? 1));
            $unit = (float) ($product['price'] ?? 0);

            $lines[] = [
                'item_id' => $id,
                'title' => (string) ($product['title'] ?? 'Item'),
                'quantity' => $qty,
                'unit_price' => $unit,
                'line_total' => $unit * $qty,
                'variant' => $cartItem['variant'] ?? null,
                'metadata' => [
                    'category' => $product['category'] ?? null,
                ],
            ];
        }

        return $lines;
    }

    public function nextOrderNumber(?Company $company): string
    {
        $prefix = 'ORD-'.now()->format('ymd');
        $companyId = $company?->id ?? 0;

        $count = CatalogOrder::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->whereDate('created_at', today())
            ->count() + 1;

        return sprintf('%s-%04d', $prefix, $count);
    }
}
