<?php

namespace App\Services\Catalog;

use App\Models\Company;
use App\Models\ListCatalog;
use App\Scopes\CompanyScope;

class CatalogGoLiveService
{
    public function __construct(
        protected CatalogUrlService $catalogUrlService,
        protected CatalogFlowUsageService $catalogFlowUsageService,
    ) {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function steps(Company $company, ?ListCatalog $catalog = null): array
    {
        $catalog ??= ListCatalog::withoutGlobalScope(CompanyScope::class)
            ->where('company_id', $company->id)
            ->whereNull('parent_id')
            ->orderByDesc('id')
            ->first();

        $hasCatalog = (bool) $catalog;
        $itemCount = $catalog ? count($catalog->items ?? []) : 0;
        $hasWhatsApp = (bool) $company->getConfig('whatsapp_phone_number_id');
        $hasPayment = filled($company->getConfig('paystack_commerce_secret_key'))
            || filled($company->getConfig('mpesa_consumer_key'));
        $flows = $catalog
            ? $this->catalogFlowUsageService->flowsUsingCatalog($catalog->id, $company->id)
            : [];
        $hasPublishedFlow = collect($flows)->contains(fn ($flow) => ! empty($flow['is_active']) || ! empty($flow['id']));

        $mode = $catalog?->resolvedCatalogMode() ?? 'commerce';
        $isBookableMode = $catalog
            ? (bool) app(CatalogTemplateRegistry::class)->presentationForCatalog($catalog)['supports_booking']
            : in_array($mode, [CatalogMode::LISTING, CatalogMode::SERVICE], true);
        $linkedBookableCount = 0;
        if ($catalog && $isBookableMode) {
            foreach ($catalog->items ?? [] as $item) {
                $metadata = is_array($item['metadata'] ?? null) ? $item['metadata'] : [];
                if ((int) ($metadata['booking_source_id'] ?? 0) > 0) {
                    $linkedBookableCount++;
                }
            }
        }

        $steps = [
            [
                'key' => 'catalog',
                'title' => __('Create your catalog'),
                'description' => __('Pick Sell / List / Book and add your first items.'),
                'completed' => $hasCatalog,
                'action_route' => 'catalogs.page',
                'action_label' => __('Open catalogs'),
            ],
            [
                'key' => 'items',
                'title' => __('Add products or listings'),
                'description' => __('Import Excel/Shopify/Woo or add items manually.'),
                'completed' => $itemCount > 0,
                'action_route' => $catalog ? 'catalogs.items.page' : 'catalogs.page',
                'action_params' => $catalog ? ['id' => $catalog->id] : [],
                'action_label' => __('Manage items'),
            ],
        ];

        if ($isBookableMode) {
            $steps[] = [
                'key' => 'bookable_links',
                'title' => __('Link bookable services'),
                'description' => __('Connect each listing to an appointment service so customers can book slots.'),
                'completed' => $itemCount > 0 && $linkedBookableCount === $itemCount,
                'optional' => true,
                'action_route' => $catalog ? 'catalogs.items.page' : 'catalogs.page',
                'action_params' => $catalog ? ['id' => $catalog->id] : [],
                'action_label' => __('Manage items'),
            ];
        }

        return array_merge($steps, [
            [
                'key' => 'whatsapp',
                'title' => __('Connect WhatsApp checkout number'),
                'description' => __('Customers need a WhatsApp number to complete orders and inquiries.'),
                'completed' => $hasWhatsApp || filled($company->getConfig('whatsapp_phone_number')),
                'action_route' => 'catalogs.page',
                'action_label' => __('Commerce settings'),
            ],
            [
                'key' => 'payments',
                'title' => __('Connect payments'),
                'description' => $mode === 'commerce'
                    ? __('Connect Paystack and/or M-Pesa so checkout can collect money.')
                    : __('Optional for deposits on bookings — connect Paystack or M-Pesa.'),
                'completed' => $hasPayment,
                'optional' => $mode !== 'commerce',
                'action_route' => 'catalogs.page',
                'action_label' => __('Payment settings'),
            ],
            [
                'key' => 'flow',
                'title' => __('Publish a shop / listing flow'),
                'description' => __('Install a Flowmaker template and bind this catalog.'),
                'completed' => count($flows) > 0,
                'action_route' => 'flows.index',
                'action_label' => __('Open Flowmaker'),
                'suggested_template' => $mode === 'commerce' ? 'whatsapp_shop_checkout' : 'services_listing_booking',
            ],
            [
                'key' => 'share',
                'title' => __('Share your storefront'),
                'description' => __('Send the public shop link or QR from chat.'),
                'completed' => $hasCatalog && $itemCount > 0,
                'action_url' => $catalog ? $this->catalogUrlService->publicUrl($catalog, $company) : null,
                'action_label' => __('Open public page'),
            ],
        ]);
    }

    /**
     * @return array{completed: int, total: int, percent: int, steps: list<array<string, mixed>>, catalog: array<string, mixed>|null}
     */
    public function summary(Company $company, ?ListCatalog $catalog = null): array
    {
        $steps = $this->steps($company, $catalog);
        $required = array_values(array_filter($steps, fn ($step) => empty($step['optional'])));
        $completed = count(array_filter($required, fn ($step) => ! empty($step['completed'])));
        $total = max(1, count($required));

        $catalog ??= ListCatalog::withoutGlobalScope(CompanyScope::class)
            ->where('company_id', $company->id)
            ->whereNull('parent_id')
            ->orderByDesc('id')
            ->first();

        return [
            'completed' => $completed,
            'total' => $total,
            'percent' => (int) round(($completed / $total) * 100),
            'steps' => $steps,
            'catalog' => $catalog ? [
                'id' => $catalog->id,
                'name' => $catalog->name,
                'mode' => $catalog->resolvedCatalogMode(),
                'vertical' => $catalog->resolvedVertical(),
                'public_url' => $this->catalogUrlService->publicUrl($catalog, $company),
            ] : null,
        ];
    }
}
