<?php

namespace Modules\Whatsappcatalog\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\ListCatalog;
use App\Services\Catalog\CatalogItemRepository;
use App\Services\Catalog\CatalogUrlService;
use App\Services\Catalog\StoreProductFetcher;
use Illuminate\Http\Request;

class SidebarController extends Controller
{
    public function __construct(
        protected CatalogItemRepository $catalogItemRepository,
        protected CatalogUrlService $catalogUrlService,
        protected StoreProductFetcher $storeProductFetcher,
    ) {
    }

    public function catalogs(Request $request)
    {
        $company = $this->getCompany() ?? abort(403);

        $catalogs = ListCatalog::where('company_id', $company->id)
            ->whereNull('parent_id')
            ->orderByDesc('id')
            ->get()
            ->map(fn (ListCatalog $catalog) => [
                'id' => $catalog->id,
                'name' => $catalog->name,
                'catalog_mode' => $catalog->resolvedCatalogMode(),
                'vertical' => $catalog->resolvedVertical(),
                'item_count' => count($this->catalogItemRepository->getItemsArray($catalog)),
                'public_url' => $this->catalogUrlService->publicUrl($catalog, $company),
            ]);

        $recentOrders = \App\Models\CatalogOrder::query()
            ->where('company_id', $company->id)
            ->with('items')
            ->orderByDesc('id')
            ->limit(8)
            ->get()
            ->map(fn (\App\Models\CatalogOrder $order) => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'status' => $order->status,
                'customer_name' => $order->customer_name,
                'customer_phone' => $order->customer_phone,
                'total_amount' => $order->total_amount,
                'currency' => $order->currency,
                'checkout_channel' => $order->checkout_channel,
                'item_count' => $order->items->count(),
                'created_at' => optional($order->created_at)?->toDateTimeString(),
            ]);

        $storeSource = $this->storeProductFetcher->resolveSource($company, 'auto');
        $storeProducts = $storeSource
            ? $this->storeProductFetcher->fetch($company, 'auto')
            : [];

        return response()->json([
            'success' => true,
            'catalogs' => $catalogs,
            'recent_orders' => $recentOrders,
            'store_products' => $storeProducts,
            'store_source' => $storeSource,
        ]);
    }

    public function searchProducts(Request $request)
    {
        $company = $this->getCompany() ?? abort(403);

        $validated = $request->validate([
            'query' => 'nullable|string|max:120',
            'catalog_id' => 'nullable|integer',
            'limit' => 'nullable|integer|min:1|max:25',
        ]);

        $query = mb_strtolower(trim($validated['query'] ?? ''));
        $limit = $validated['limit'] ?? 10;

        $catalogQuery = ListCatalog::where('company_id', $company->id)->whereNull('parent_id');
        if (! empty($validated['catalog_id'])) {
            $catalogQuery->where('id', $validated['catalog_id']);
        }

        $results = [];

        foreach ($catalogQuery->get() as $catalog) {
            foreach ($this->catalogItemRepository->getItemsArray($catalog) as $item) {
                $haystack = mb_strtolower(($item['title'] ?? '').' '.($item['category'] ?? ''));
                if ($query === '' || str_contains($haystack, $query)) {
                    $results[] = [
                        'catalog_id' => $catalog->id,
                        'catalog_name' => $catalog->name,
                        'item' => $item,
                        'shop_url' => $this->catalogUrlService->publicUrl($catalog, $company),
                    ];
                }

                if (count($results) >= $limit) {
                    break 2;
                }
            }
        }

        return response()->json(['success' => true, 'products' => $results]);
    }
}
