<?php

namespace App\Http\Controllers;

use App\Http\Requests\ManageCatalogItemsRequest;
use App\Http\Requests\UpdateCatalogCommerceSettingsRequest;
use App\Models\CatalogCollection;
use App\Models\CatalogItem;
use App\Models\Company;
use App\Models\ListCatalog;
use App\Services\Catalog\ApiCatalogImportService;
use App\Services\Catalog\CatalogAnalyticsService;
use App\Services\Catalog\CatalogCategoryNormalizer;
use App\Services\Catalog\CatalogExperimentService;
use App\Services\Catalog\CatalogFlowUsageService;
use App\Services\Catalog\CatalogItemRepository;
use App\Services\Catalog\CatalogReimportService;
use App\Services\Catalog\CatalogStoreSyncService;
use App\Services\Catalog\CatalogUrlService;
use App\Services\Catalog\CatalogWhatsAppOrderService;
use App\Services\Catalog\StoreCatalogImportService;
use App\Services\CatalogItemFilterService;
use App\Services\CatalogItemPlanLimit;
use App\Services\ExcelImportService;
use App\Services\WhatsApp\OrderInvoiceMessageTemplateService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ListCatalogController extends Controller
{
    public function __construct(
        protected ExcelImportService $excelService,
        protected CatalogItemPlanLimit $catalogItemPlanLimit,
        protected OrderInvoiceMessageTemplateService $orderInvoiceTemplateService,
        protected CatalogItemFilterService $catalogItemFilter,
        protected CatalogUrlService $catalogUrlService,
        protected CatalogFlowUsageService $catalogFlowUsageService,
        protected CatalogReimportService $catalogReimportService,
        protected StoreCatalogImportService $storeCatalogImportService,
        protected CatalogAnalyticsService $catalogAnalyticsService,
        protected CatalogCategoryNormalizer $categoryNormalizer,
        protected CatalogItemRepository $catalogItemRepository,
        protected CatalogStoreSyncService $catalogStoreSyncService,
        protected ApiCatalogImportService $apiCatalogImportService,
        protected CatalogExperimentService $catalogExperimentService,
        protected CatalogWhatsAppOrderService $catalogWhatsAppOrderService,
    ) {
    }

    /**
     * Preview Excel file without saving
     */
    public function previewExcel(Request $request)
    {
        if (! auth()->check()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 401);
        }

        try {
            $request->validate([
                'file' => 'required|file|mimes:xlsx,xls,csv',
            ]);

            $file = $request->file('file');
            $path = $file->store('temp');
            $fullPath = storage_path('app/'.$path);

            // Parse the Excel file
            $parseResult = $this->excelService->parseExcel($fullPath);

            // Clean up temp file
            unlink($fullPath);

            $columnMapping = $parseResult['column_mapping'];
            $previewItems = $this->excelService->transformItems(
                array_slice($parseResult['items'], 0, 5),
                $columnMapping
            );

            return response()->json([
                'success' => true,
                'items' => $this->excelService->previewItems($previewItems),
                'columns' => $parseResult['columns'],
                'column_mapping' => $columnMapping,
                'template_headers' => ExcelImportService::TEMPLATE_HEADERS,
                'total_count' => $parseResult['total_count'],
                'headers' => $parseResult['headers'],
            ]);

        } catch (\Exception $e) {
            Log::error('Excel preview failed', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Import Excel file and create catalog
     */
    public function importExcel(Request $request)
    {
        if (! auth()->check()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 401);
        }

        try {
            $request->validate([
                'file' => 'required|file|mimes:xlsx,xls,csv',
                'catalogName' => 'required|string|max:255',
                'columnMapping' => 'nullable|json',
            ]);

            $file = $request->file('file');
            $catalogName = $request->input('catalogName');

            $path = $file->store('catalogs');
            $fullPath = storage_path('app/'.$path);

            $parseResult = $this->excelService->parseExcel($fullPath);

            $columnMapping = $request->filled('columnMapping')
                ? json_decode($request->input('columnMapping'), true)
                : $parseResult['column_mapping'];

            $transformedItems = $this->excelService->transformItems(
                $parseResult['items'],
                $columnMapping
            );

            // Validate items
            $this->excelService->validateItems($transformedItems);

            $company = $this->getCompany() ?? abort(403);
            $itemCount = count($transformedItems);

            if (! $this->catalogItemPlanLimit->canAdd($company, $itemCount)) {
                return response()->json([
                    'success' => false,
                    'message' => $this->catalogItemPlanLimit->limitExceededMessage($company, $itemCount),
                    'usage' => $this->catalogItemPlanLimit->getUsageSummary($company),
                ], 403);
            }

            // Create catalog
            $catalogData = [
                'company_id' => $this->activeCompanyId(),
                'name' => $catalogName,
                'version' => 1,
                'items' => $transformedItems,
                'columns' => $this->excelService->getColumnsFromItems($transformedItems),
                'source' => 'excel',
                'original_file_name' => $file->getClientOriginalName(),
                'metadata' => [
                    'column_mapping' => $columnMapping,
                    'imported_count' => count($transformedItems),
                    'imported_at' => now(),
                ],
            ];

            $catalog = ListCatalog::create($catalogData);
            $this->catalogItemRepository->replaceAllFromArray($catalog, $transformedItems);

            $this->catalogItemPlanLimit->recordUsage($company->id, $itemCount);

            $templateProvision = $this->orderInvoiceTemplateService->ensureForCompany($company);

            // Clean up original file
            unlink($fullPath);

            $responseMessage = "Catalog '{$catalogName}' created with ".count($transformedItems).' items.';
            if (! $templateProvision['ready']) {
                $responseMessage .= ' '.$templateProvision['message'];
            }

            return response()->json([
                'success' => true,
                'message' => $responseMessage,
                'catalogId' => $catalog->id,
                'catalog' => $this->formatCatalogSummary($catalog),
                'items' => $transformedItems,
                'itemCount' => count($transformedItems),
                'order_template' => [
                    'ready' => $templateProvision['ready'],
                    'status' => $templateProvision['status'],
                    'message' => $templateProvision['message'],
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Excel import failed', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Download standardized catalog import template (.xlsx)
     */
    public function downloadImportTemplate(): StreamedResponse
    {
        if (! auth()->check()) {
            abort(401);
        }

        return response()->streamDownload(function () {
            $this->excelService->writeTemplateToPath('php://output');
        }, 'catalog-import-template.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * Test API endpoint and get preview
     */
    public function testAPI(Request $request)
    {
        if (! auth()->check()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 401);
        }

        try {
            $request->validate([
                'apiUrl' => 'required|url',
                'apiParams' => 'nullable|array',
                'responseDataPath' => 'required|string',
            ]);

            $url = $request->input('apiUrl');
            $params = $request->input('apiParams', []);
            $responseDataPath = $request->input('responseDataPath');

            // Make API call
            $response = \Illuminate\Support\Facades\Http::timeout(10)->get($url, $params);

            if (! $response->successful()) {
                return response()->json([
                    'success' => false,
                    'message' => "API returned status {$response->status()}",
                    'status' => $response->status(),
                ], 400);
            }

            $data = $response->json();

            // Extract data from response path
            $items = $this->getValueByPath($data, $responseDataPath);

            if (! is_array($items)) {
                return response()->json([
                    'success' => false,
                    'message' => "Data at path '{$responseDataPath}' is not an array",
                ], 400);
            }

            if (empty($items)) {
                return response()->json([
                    'success' => false,
                    'message' => "No items returned at path '{$responseDataPath}'",
                ], 400);
            }

            // Return preview
            return response()->json([
                'success' => true,
                'items' => array_slice($items, 0, 5),
                'total_count' => count($items),
                'showing' => min(5, count($items)),
                'has_more' => count($items) > 5,
            ]);

        } catch (\Exception $e) {
            Log::error('API test failed', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Get all catalogs for company
     */
    public function listCatalogs()
    {
        if (! auth()->check()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 401);
        }

        try {
            $companyId = $this->activeCompanyId();

            $catalogs = ListCatalog::where('company_id', $companyId)
                ->where('parent_id', null) // Only root catalogs
                ->with('versions')
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(fn ($catalog) => $this->formatCatalogSummary($catalog));

            $company = $this->getCompany();
            $aiCatalogIds = $company
                ? json_decode($company->getConfig('whatsapp_ai_catalog_ids', '[]'), true) ?: []
                : [];

            return response()->json([
                'success' => true,
                'catalogs' => $catalogs,
                'catalog_item_usage' => $company
                    ? $this->catalogItemPlanLimit->getUsageSummary($company)
                    : null,
                'ai_catalog_ids' => $aiCatalogIds,
                'has_shopify' => $company ? (bool) $company->getConfig('shopify_access_token') : false,
                'has_woocommerce' => $company ? (bool) $company->getConfig('woocommerce_consumer_key') : false,
                'commerce_settings' => $company ? $this->commerceSettingsPayload($company) : null,
            ]);

        } catch (\Exception $e) {
            Log::error('List catalogs failed', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Get catalog details
     */
    public function getCatalog($id)
    {
        if (! auth()->check()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 401);
        }

        try {
            $companyId = $this->activeCompanyId();
            $catalog = ListCatalog::where('id', $id)
                ->where('company_id', $companyId)
                ->firstOrFail();

            return response()->json([
                'success' => true,
                'catalog' => [
                    'id' => $catalog->id,
                    'name' => $catalog->name,
                    'version' => $catalog->version,
                    'description' => $catalog->description,
                    'source' => $catalog->source,
                    'items' => $catalog->items,
                    'columns' => $catalog->columns,
                    'item_count' => count($catalog->items ?? []),
                    'created_at' => $catalog->created_at,
                    'versions' => $catalog->getAllVersions()
                        ->map(fn ($v) => [
                            'id' => $v->id,
                            'version' => $v->version,
                            'created_at' => $v->created_at,
                        ])
                        ->toArray(),
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Get catalog failed', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Catalog not found',
            ], 404);
        }
    }

    /**
     * Update catalog details
     */
    public function updateCatalog(Request $request, $id)
    {
        if (! auth()->check()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 401);
        }

        try {
            $companyId = $this->activeCompanyId();
            $catalog = ListCatalog::where('id', $id)
                ->where('company_id', $companyId)
                ->firstOrFail();

            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'description' => 'nullable|string|max:1000',
            ]);

            $catalog->name = $validated['name'];
            $catalog->description = $validated['description'] ?? null;
            $catalog->save();

            return response()->json([
                'success' => true,
                'message' => 'Catalog updated successfully',
                'catalog' => [
                    'id' => $catalog->id,
                    'name' => $catalog->name,
                    'description' => $catalog->description,
                    'version' => $catalog->version,
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Update catalog failed', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Delete catalog
     */
    public function deleteCatalog($id)
    {
        if (! auth()->check()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 401);
        }

        try {
            $companyId = $this->activeCompanyId();
            $catalog = ListCatalog::where('id', $id)
                ->where('company_id', $companyId)
                ->firstOrFail();

            $flows = $this->catalogFlowUsageService->flowsUsingCatalog($catalog->id, $companyId);
            if ($flows !== []) {
                $names = collect($flows)->pluck('name')->implode(', ');

                return response()->json([
                    'success' => false,
                    'message' => 'This catalog is used in active flows: '.$names.'. Remove it from those flows before deleting.',
                    'flows' => $flows,
                ], 409);
            }

            $catalog->delete();

            return response()->json([
                'success' => true,
                'message' => 'Catalog deleted successfully',
            ]);

        } catch (\Exception $e) {
            Log::error('Delete catalog failed', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Display catalog items management page
     */
    public function itemsPage($id)
    {
        if (! auth()->check()) {
            return redirect()->route('login');
        }

        $companyId = $this->activeCompanyId();
        $catalog = ListCatalog::where('id', $id)
            ->where('company_id', $companyId)
            ->firstOrFail();

        return view('settings.catalog-items', [
            'catalog' => $catalog,
        ]);
    }

    /**
     * Get catalog items for management (paginated)
     */
    public function getItems(ManageCatalogItemsRequest $request, $id)
    {
        if (! auth()->check()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 401);
        }

        try {
            $companyId = $this->activeCompanyId();
            $catalog = ListCatalog::where('id', $id)
                ->where('company_id', $companyId)
                ->firstOrFail();

            $browse = $this->catalogItemFilter->browse(
                $catalog->items ?? [],
                $request->filters(),
                route('catalogs.items', ['id' => $catalog->id])
            );

            $paginator = $browse['items'];

            return response()->json([
                'success' => true,
                'items' => $paginator->items(),
                'pagination' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'from' => $paginator->firstItem(),
                    'to' => $paginator->lastItem(),
                ],
                'total_in_catalog' => $browse['totalInCatalog'],
                'filtered_total' => $browse['filteredTotal'],
            ]);

        } catch (\Exception $e) {
            Log::error('Get catalog items failed', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Catalog not found',
            ], 404);
        }
    }

    /**
     * Get a single catalog item for editing
     */
    public function getItem($id, $itemId)
    {
        if (! auth()->check()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 401);
        }

        try {
            $companyId = $this->activeCompanyId();
            $catalog = ListCatalog::where('id', $id)
                ->where('company_id', $companyId)
                ->firstOrFail();

            $item = $this->findProductInCatalog($catalog->items ?? [], $itemId);

            if ($item === null) {
                return response()->json([
                    'success' => false,
                    'message' => 'Item not found',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'item' => $item,
            ]);

        } catch (\Exception $e) {
            Log::error('Get catalog item failed', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Item not found',
            ], 404);
        }
    }

    /**
     * Add item to catalog
     */
    public function addItem(Request $request, $id)
    {
        if (! auth()->check()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 401);
        }

        try {
            $companyId = $this->activeCompanyId();
            $catalog = ListCatalog::where('id', $id)
                ->where('company_id', $companyId)
                ->firstOrFail();

            $validated = $request->validate([
                'id' => 'required|string|max:100',
                'title' => 'required|string|max:255',
                'description' => 'nullable|string|max:1000',
                'price' => 'nullable|numeric|min:0',
                'category' => 'nullable|string|max:255',
                'imageUrl' => 'nullable|url|max:2048',
                'stockStatus' => 'nullable|string|in:In Stock,Out of Stock,Low Stock',
                'quantityAvailable' => 'nullable|integer|min:0',
                'variants' => 'nullable|array',
                'tags' => 'nullable|array',
            ]);

            $items = $this->catalogItemRepository->getItemsArray($catalog);

            // Check if item with same ID already exists
            $exists = array_search($validated['id'], array_column($items, 'id'));
            if ($exists !== false) {
                return response()->json([
                    'success' => false,
                    'message' => 'Item with this ID already exists',
                ], 400);
            }

            $company = Company::findOrFail($companyId);

            if (! $this->catalogItemPlanLimit->canAdd($company, 1)) {
                return response()->json([
                    'success' => false,
                    'message' => $this->catalogItemPlanLimit->limitExceededMessage($company, 1),
                    'usage' => $this->catalogItemPlanLimit->getUsageSummary($company),
                ], 403);
            }

            $newItem = [
                'id' => $validated['id'],
                'title' => $validated['title'],
                'description' => $validated['description'] ?? '',
                'price' => $validated['price'] ?? 0,
                'category' => $validated['category'] ?? '',
                'imageUrl' => $validated['imageUrl'] ?? '',
                'stockStatus' => $validated['stockStatus'] ?? 'In Stock',
                'quantityAvailable' => $validated['quantityAvailable'] ?? null,
                'variants' => $validated['variants'] ?? [],
                'tags' => $validated['tags'] ?? [],
            ];

            $this->catalogItemRepository->upsertFromArray($catalog, $newItem);
            $items = $this->catalogItemRepository->getItemsArray($catalog->fresh());

            $this->catalogItemPlanLimit->recordUsage($company->id, 1);

            return response()->json([
                'success' => true,
                'message' => 'Item added successfully',
                'item' => $newItem,
                'items' => $items,
                'usage' => $this->catalogItemPlanLimit->getUsageSummary($company),
            ]);

        } catch (\Exception $e) {
            Log::error('Add item failed', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Update item in catalog
     */
    public function updateItem(Request $request, $id, $itemId)
    {
        if (! auth()->check()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 401);
        }

        try {
            $companyId = $this->activeCompanyId();
            $catalog = ListCatalog::where('id', $id)
                ->where('company_id', $companyId)
                ->firstOrFail();

            $validated = $request->validate([
                'title' => 'required|string|max:255',
                'description' => 'nullable|string|max:1000',
                'price' => 'nullable|numeric|min:0',
                'category' => 'nullable|string|max:255',
                'imageUrl' => 'nullable|url|max:2048',
                'stockStatus' => 'nullable|string|in:In Stock,Out of Stock,Low Stock',
                'quantityAvailable' => 'nullable|integer|min:0',
                'variants' => 'nullable|array',
                'tags' => 'nullable|array',
            ]);

            $items = $this->catalogItemRepository->getItemsArray($catalog);
            $existing = null;

            foreach ($items as $item) {
                if (($item['id'] ?? null) === $itemId) {
                    $existing = $item;
                    break;
                }
            }

            if ($existing === null) {
                return response()->json([
                    'success' => false,
                    'message' => 'Item not found',
                ], 404);
            }

            $updatedItem = array_merge($existing, [
                'id' => $itemId,
                'title' => $validated['title'],
                'description' => $validated['description'] ?? '',
                'price' => $validated['price'] ?? 0,
                'category' => $validated['category'] ?? '',
                'imageUrl' => $validated['imageUrl'] ?? '',
                'stockStatus' => $validated['stockStatus'] ?? 'In Stock',
                'quantityAvailable' => array_key_exists('quantityAvailable', $validated)
                    ? $validated['quantityAvailable']
                    : ($existing['quantityAvailable'] ?? null),
                'variants' => $validated['variants'] ?? [],
                'tags' => $validated['tags'] ?? [],
            ]);

            $this->catalogItemRepository->upsertFromArray($catalog, $updatedItem);
            $items = $this->catalogItemRepository->getItemsArray($catalog->fresh());

            return response()->json([
                'success' => true,
                'message' => 'Item updated successfully',
                'item' => collect($items)->firstWhere('id', $itemId),
                'items' => $items,
            ]);

        } catch (\Exception $e) {
            Log::error('Update item failed', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Delete item from catalog
     */
    public function deleteItem($id, $itemId)
    {
        if (! auth()->check()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 401);
        }

        try {
            $companyId = $this->activeCompanyId();
            $catalog = ListCatalog::where('id', $id)
                ->where('company_id', $companyId)
                ->firstOrFail();

            $items = $this->catalogItemRepository->getItemsArray($catalog);
            $found = collect($items)->contains(fn ($item) => ($item['id'] ?? null) === $itemId);

            if (! $found) {
                return response()->json([
                    'success' => false,
                    'message' => 'Item not found',
                ], 404);
            }

            $this->catalogItemRepository->deleteByItemId($catalog, $itemId);
            $items = $this->catalogItemRepository->getItemsArray($catalog->fresh());

            return response()->json([
                'success' => true,
                'message' => 'Item deleted successfully',
                'items' => $items,
            ]);

        } catch (\Exception $e) {
            Log::error('Delete item failed', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Get value from nested array using dot notation
     */
    private function getValueByPath($data, $path)
    {
        $keys = explode('.', $path);
        $value = $data;

        foreach ($keys as $key) {
            if (is_array($value) && isset($value[$key])) {
                $value = $value[$key];
            } else {
                return null;
            }
        }

        return $value;
    }

    private function findProductInCatalog(array $items, string $productId): ?array
    {
        foreach ($items as $item) {
            if (($item['id'] ?? null) === $productId) {
                return $item;
            }
        }

        return null;
    }

    public function createEmpty(Request $request)
    {
        if (! auth()->check()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
        ]);

        $catalog = ListCatalog::create([
            'company_id' => $this->activeCompanyId(),
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'version' => 1,
            'items' => [],
            'columns' => [],
            'source' => 'manual',
            'metadata' => ['created_via' => 'empty'],
        ]);

        return response()->json([
            'success' => true,
            'message' => "Catalog '{$catalog->name}' created.",
            'catalogId' => $catalog->id,
            'catalog' => $this->formatCatalogSummary($catalog),
        ]);
    }

    public function reimportExcel(Request $request, $id)
    {
        if (! auth()->check()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv',
            'columnMapping' => 'nullable|json',
            'remove_missing' => 'nullable|boolean',
            'preview_only' => 'nullable|boolean',
        ]);

        $companyId = $this->activeCompanyId();
        $catalog = ListCatalog::where('id', $id)->where('company_id', $companyId)->firstOrFail();
        $company = Company::findOrFail($companyId);

        $file = $request->file('file');
        $path = $file->store('catalogs');
        $fullPath = storage_path('app/'.$path);

        try {
            $parseResult = $this->excelService->parseExcel($fullPath);
            $columnMapping = $request->filled('columnMapping')
                ? json_decode($request->input('columnMapping'), true)
                : $parseResult['column_mapping'];

            $importedItems = $this->excelService->transformItems($parseResult['items'], $columnMapping);
            $this->excelService->validateItems($importedItems);

            $preview = $this->catalogReimportService->previewMerge(
                $this->catalogItemRepository->getItemsArray($catalog),
                $importedItems
            );

            if ($request->boolean('preview_only')) {
                return response()->json([
                    'success' => true,
                    'preview' => $preview,
                    'import_count' => count($importedItems),
                ]);
            }

            $merge = $this->catalogReimportService->mergeByItemId(
                $this->catalogItemRepository->getItemsArray($catalog),
                $importedItems,
                $request->boolean('remove_missing')
            );

            $netNew = $merge['added'];
            if ($netNew > 0 && ! $this->catalogItemPlanLimit->canAdd($company, $netNew)) {
                return response()->json([
                    'success' => false,
                    'message' => $this->catalogItemPlanLimit->limitExceededMessage($company, $netNew),
                    'usage' => $this->catalogItemPlanLimit->getUsageSummary($company),
                    'preview' => $preview,
                ], 403);
            }

            $this->catalogItemRepository->replaceAllFromArray($catalog, $merge['items']);
            $catalog->columns = $this->excelService->getColumnsFromItems($merge['items']);
            $catalog->metadata = array_merge($catalog->metadata ?? [], [
                'last_reimport_at' => now()->toIso8601String(),
                'last_reimport_file' => $file->getClientOriginalName(),
                'reimport_stats' => [
                    'added' => $merge['added'],
                    'updated' => $merge['updated'],
                    'unchanged' => $merge['unchanged'],
                ],
            ]);
            $catalog->save();

            if ($merge['added'] > 0) {
                $this->catalogItemPlanLimit->recordUsage($company->id, $merge['added']);
            }

            return response()->json([
                'success' => true,
                'message' => "Catalog updated: {$merge['added']} added, {$merge['updated']} updated.",
                'catalog' => $this->formatCatalogSummary($catalog->fresh()),
                'stats' => $merge,
            ]);
        } finally {
            if (file_exists($fullPath)) {
                unlink($fullPath);
            }
        }
    }

    public function importShopify(Request $request)
    {
        return $this->importFromStore($request, 'shopify');
    }

    public function importWooCommerce(Request $request)
    {
        return $this->importFromStore($request, 'woocommerce');
    }

    public function getAnalytics($id)
    {
        if (! auth()->check()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $companyId = $this->activeCompanyId();
        $catalog = ListCatalog::where('id', $id)->where('company_id', $companyId)->firstOrFail();

        return response()->json([
            'success' => true,
            'analytics' => $this->catalogAnalyticsService->summary($catalog->id),
            'flows' => $this->catalogFlowUsageService->flowsUsingCatalog($catalog->id, $companyId),
        ]);
    }

    public function updateAttachments(Request $request)
    {
        if (! auth()->check()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $validated = $request->validate([
            'ai_catalog_ids' => 'nullable|array',
            'ai_catalog_ids.*' => 'integer',
        ]);

        $company = $this->getCompany() ?? abort(403);
        $ids = array_values(array_unique(array_map('intval', $validated['ai_catalog_ids'] ?? [])));

        $validIds = ListCatalog::where('company_id', $company->id)
            ->whereIn('id', $ids)
            ->pluck('id')
            ->all();

        $company->setConfig('whatsapp_ai_catalog_ids', json_encode($validIds));

        return response()->json([
            'success' => true,
            'message' => 'Catalog attachments updated.',
            'ai_catalog_ids' => $validIds,
        ]);
    }

    public function updateCommerceSettings(UpdateCatalogCommerceSettingsRequest $request)
    {
        $company = $this->getCompany() ?? abort(403);

        $this->catalogWhatsAppOrderService->save(
            $company,
            $request->validated('whatsapp_order_number')
        );

        return response()->json([
            'success' => true,
            'message' => 'Catalog checkout settings saved.',
            'commerce_settings' => $this->commerceSettingsPayload($company),
        ]);
    }

    /**
     * @return array{whatsapp_order_number: string, whatsapp_order_number_configured: bool}
     */
    private function commerceSettingsPayload(Company $company): array
    {
        return [
            'whatsapp_order_number' => $this->catalogWhatsAppOrderService->displayValue($company),
            'whatsapp_order_number_configured' => $this->catalogWhatsAppOrderService->resolveNumber($company) !== null,
        ];
    }

    public function uploadItemImage(Request $request, $id, $itemId)
    {
        if (! auth()->check()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $request->validate([
            'image' => 'required|image|max:5120',
        ]);

        $companyId = $this->activeCompanyId();
        $catalog = ListCatalog::where('id', $id)->where('company_id', $companyId)->firstOrFail();
        $items = $this->catalogItemRepository->getItemsArray($catalog);
        $existing = collect($items)->firstWhere('id', $itemId);

        if (! $existing) {
            return response()->json(['success' => false, 'message' => 'Item not found'], 404);
        }

        $path = $request->file('image')->store("catalog-images/{$companyId}", 'public');
        $url = Storage::disk('public')->url($path);

        $existing['imageUrl'] = $url;
        $this->catalogItemRepository->upsertFromArray($catalog, $existing);
        $updatedItem = collect($this->catalogItemRepository->getItemsArray($catalog->fresh()))->firstWhere('id', $itemId);

        return response()->json([
            'success' => true,
            'message' => 'Image uploaded successfully.',
            'imageUrl' => $url,
            'item' => $updatedItem,
        ]);
    }

    private function importFromStore(Request $request, string $source)
    {
        if (! auth()->check()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $validated = $request->validate([
            'catalogName' => 'required|string|max:255',
            'catalogId' => 'nullable|integer',
        ]);

        $company = $this->getCompany() ?? abort(403);

        try {
            $items = $source === 'shopify'
                ? $this->storeCatalogImportService->fetchShopifyItems($company)
                : $this->storeCatalogImportService->fetchWooCommerceItems($company);
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }

        if ($items === []) {
            return response()->json(['success' => false, 'message' => 'No products found in your store.'], 400);
        }

        $companyId = $this->activeCompanyId();

        if (! empty($validated['catalogId'])) {
            $catalog = ListCatalog::where('id', $validated['catalogId'])
                ->where('company_id', $companyId)
                ->firstOrFail();

            $merge = $this->catalogReimportService->mergeByItemId(
                $this->catalogItemRepository->getItemsArray($catalog),
                $items,
                true
            );
            $netNew = $merge['added'];

            if ($netNew > 0 && ! $this->catalogItemPlanLimit->canAdd($company, $netNew)) {
                return response()->json([
                    'success' => false,
                    'message' => $this->catalogItemPlanLimit->limitExceededMessage($company, $netNew),
                    'usage' => $this->catalogItemPlanLimit->getUsageSummary($company),
                ], 403);
            }

            $this->catalogItemRepository->replaceAllFromArray($catalog, $merge['items']);
            $catalog->columns = $this->excelService->getColumnsFromItems($merge['items']);
            $catalog->source = 'api';
            $catalog->metadata = array_merge($catalog->metadata ?? [], [
                'store_source' => $source,
                'synced_at' => now()->toIso8601String(),
            ]);
            $catalog->save();

            $this->catalogStoreSyncService->linkStoreProducts($catalog, $source, $items);

            if ($merge['added'] > 0) {
                $this->catalogItemPlanLimit->recordUsage($company->id, $merge['added']);
            }

            return response()->json([
                'success' => true,
                'message' => ucfirst($source).' catalog synced: '.$merge['added'].' added, '.$merge['updated'].' updated.',
                'catalog' => $this->formatCatalogSummary($catalog->fresh()),
                'itemCount' => count($merge['items']),
            ]);
        }

        $itemCount = count($items);
        if (! $this->catalogItemPlanLimit->canAdd($company, $itemCount)) {
            return response()->json([
                'success' => false,
                'message' => $this->catalogItemPlanLimit->limitExceededMessage($company, $itemCount),
                'usage' => $this->catalogItemPlanLimit->getUsageSummary($company),
            ], 403);
        }

        $catalog = ListCatalog::create([
            'company_id' => $companyId,
            'name' => $validated['catalogName'],
            'version' => 1,
            'items' => $items,
            'columns' => $this->excelService->getColumnsFromItems($items),
            'source' => 'api',
            'metadata' => [
                'store_source' => $source,
                'imported_count' => $itemCount,
                'imported_at' => now(),
            ],
        ]);

        $this->catalogItemRepository->replaceAllFromArray($catalog, $items);
        $this->catalogStoreSyncService->linkStoreProducts($catalog, $source, $items);

        $this->catalogItemPlanLimit->recordUsage($company->id, $itemCount);
        $this->orderInvoiceTemplateService->ensureForCompany($company);

        return response()->json([
            'success' => true,
            'message' => ucfirst($source)." catalog '{$catalog->name}' created with {$itemCount} products.",
            'catalogId' => $catalog->id,
            'catalog' => $this->formatCatalogSummary($catalog),
            'itemCount' => $itemCount,
        ]);
    }

    public function importFromApi(Request $request, $id)
    {
        if (! auth()->check()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $validated = $request->validate([
            'api_config' => 'nullable|array',
            'api_config.url' => 'nullable|url',
            'api_config.method' => 'nullable|string|in:GET,POST,PUT',
            'api_config.headers' => 'nullable|array',
            'api_config.params' => 'nullable|array',
            'api_config.data_path' => 'nullable|string',
            'api_config.column_mapping' => 'nullable|array',
            'replace_missing' => 'nullable|boolean',
        ]);

        $companyId = $this->activeCompanyId();
        $catalog = ListCatalog::where('id', $id)->where('company_id', $companyId)->firstOrFail();

        if (! empty($validated['api_config'])) {
            $catalog->update(['api_config' => array_merge($catalog->api_config ?? [], $validated['api_config'])]);
            $catalog->refresh();
        }

        try {
            $stats = $this->apiCatalogImportService->importIntoCatalog(
                $catalog,
                $request->boolean('replace_missing')
            );
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }

        return response()->json([
            'success' => true,
            'message' => "API import complete: {$stats['added']} added, {$stats['updated']} updated.",
            'catalog' => $this->formatCatalogSummary($catalog->fresh()),
            'stats' => $stats,
        ]);
    }

    public function syncStore(Request $request, $id)
    {
        if (! auth()->check()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $companyId = $this->activeCompanyId();
        $catalog = ListCatalog::where('id', $id)->where('company_id', $companyId)->firstOrFail();
        $storeType = $catalog->metadata['store_source'] ?? null;

        if (! in_array($storeType, ['shopify', 'woocommerce'], true)) {
            if ($catalog->source === 'api' && is_array($catalog->api_config) && ! empty($catalog->api_config['url'])) {
                try {
                    $stats = $this->apiCatalogImportService->importIntoCatalog($catalog);
                } catch (\Throwable $e) {
                    return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
                }

                return response()->json([
                    'success' => true,
                    'message' => 'API catalog synced.',
                    'stats' => $stats,
                    'catalog' => $this->formatCatalogSummary($catalog->fresh()),
                ]);
            }

            return response()->json(['success' => false, 'message' => 'Catalog is not linked to a store or API source.'], 400);
        }

        try {
            $result = $this->catalogStoreSyncService->pullFromStore($catalog, $storeType);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }

        return response()->json([
            'success' => true,
            'message' => 'Store catalog synced.',
            'catalog' => $this->formatCatalogSummary($catalog->fresh()),
            'stats' => $result['stats'] ?? [],
        ]);
    }

    public function registerStoreWebhooks(Request $request)
    {
        if (! auth()->check()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $validated = $request->validate([
            'store_type' => 'required|string|in:shopify,woocommerce',
        ]);

        $company = $this->getCompany() ?? abort(403);
        $result = $this->catalogStoreSyncService->registerWebhooks($company, $validated['store_type']);

        return response()->json($result, ($result['success'] ?? false) ? 200 : 400);
    }

    public function listCollections()
    {
        if (! auth()->check()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $collections = CatalogCollection::where('company_id', $this->activeCompanyId())
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (CatalogCollection $collection) => [
                'id' => $collection->id,
                'name' => $collection->name,
                'slug' => $collection->slug,
                'description' => $collection->description,
                'image_url' => $collection->image_url,
                'is_active' => $collection->is_active,
                'item_count' => $collection->items()->count(),
            ]);

        return response()->json(['success' => true, 'collections' => $collections]);
    }

    public function createCollection(Request $request)
    {
        if (! auth()->check()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:120',
            'description' => 'nullable|string',
            'image_url' => 'nullable|url|max:2048',
            'is_active' => 'nullable|boolean',
            'sort_order' => 'nullable|integer|min:0',
            'item_ids' => 'nullable|array',
            'item_ids.*' => 'string',
        ]);

        $companyId = $this->activeCompanyId();
        $slug = $validated['slug'] ?? Str::slug($validated['name']);

        $collection = CatalogCollection::create([
            'company_id' => $companyId,
            'name' => $validated['name'],
            'slug' => $slug,
            'description' => $validated['description'] ?? null,
            'image_url' => $validated['image_url'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
            'sort_order' => $validated['sort_order'] ?? 0,
        ]);

        if (! empty($validated['item_ids'])) {
            $this->syncCollectionItemIds($collection, $validated['item_ids']);
        }

        return response()->json(['success' => true, 'collection' => $collection->fresh()]);
    }

    public function updateCollection(Request $request, $collectionId)
    {
        if (! auth()->check()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'slug' => 'nullable|string|max:120',
            'description' => 'nullable|string',
            'image_url' => 'nullable|url|max:2048',
            'is_active' => 'nullable|boolean',
            'sort_order' => 'nullable|integer|min:0',
            'item_ids' => 'nullable|array',
            'item_ids.*' => 'string',
        ]);

        $collection = CatalogCollection::where('id', $collectionId)
            ->where('company_id', $this->activeCompanyId())
            ->firstOrFail();

        $collection->update(collect($validated)->except('item_ids')->all());

        if (array_key_exists('item_ids', $validated)) {
            $this->syncCollectionItemIds($collection, $validated['item_ids'] ?? []);
        }

        return response()->json(['success' => true, 'collection' => $collection->fresh()]);
    }

    public function deleteCollection($collectionId)
    {
        if (! auth()->check()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $collection = CatalogCollection::where('id', $collectionId)
            ->where('company_id', $this->activeCompanyId())
            ->firstOrFail();

        $collection->delete();

        return response()->json(['success' => true, 'message' => 'Collection deleted.']);
    }

    public function listExperiments()
    {
        if (! auth()->check()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        return response()->json([
            'success' => true,
            'experiments' => $this->catalogExperimentService->listExperiments($this->activeCompanyId()),
        ]);
    }

    public function createExperimentVariant(Request $request, $id)
    {
        if (! auth()->check()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
            'traffic_weight' => 'nullable|integer|min:0|max:100',
            'publish_status' => 'nullable|string|in:draft,published',
        ]);

        $catalog = ListCatalog::where('id', $id)
            ->where('company_id', $this->activeCompanyId())
            ->firstOrFail();

        $variant = $this->catalogExperimentService->createVariant($catalog, $validated);

        return response()->json([
            'success' => true,
            'variant' => $this->formatCatalogSummary($variant),
        ]);
    }

    public function updateExperimentWeights(Request $request)
    {
        if (! auth()->check()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $validated = $request->validate([
            'variants' => 'required|array|min:1',
            'variants.*.id' => 'required|integer',
            'variants.*.traffic_weight' => 'required|integer|min:0|max:100',
            'variants.*.publish_status' => 'nullable|string|in:draft,published',
        ]);

        $companyId = $this->activeCompanyId();

        foreach ($validated['variants'] as $row) {
            ListCatalog::where('id', $row['id'])
                ->where('company_id', $companyId)
                ->update([
                    'traffic_weight' => $row['traffic_weight'],
                    'publish_status' => $row['publish_status'] ?? 'draft',
                ]);
        }

        return response()->json([
            'success' => true,
            'experiments' => $this->catalogExperimentService->listExperiments($companyId),
        ]);
    }

    /**
     * @param  list<string>  $itemIds
     */
    private function syncCollectionItemIds(CatalogCollection $collection, array $itemIds): void
    {
        $catalogItems = CatalogItem::withoutGlobalScopes()
            ->where('company_id', $collection->company_id)
            ->whereIn('item_id', $itemIds)
            ->get()
            ->keyBy('item_id');

        $sync = [];
        foreach (array_values($itemIds) as $index => $itemId) {
            $item = $catalogItems->get($itemId);
            if ($item) {
                $sync[$item->id] = ['sort_order' => $index];
            }
        }

        $collection->items()->sync($sync);
    }

    private function formatCatalogSummary(ListCatalog $catalog): array
    {
        $companyId = $catalog->company_id;
        $flows = $this->catalogFlowUsageService->flowsUsingCatalog($catalog->id, $companyId);

        return [
            'id' => $catalog->id,
            'name' => $catalog->name,
            'slug' => $catalog->slug,
            'description' => $catalog->description,
            'version' => $catalog->version,
            'source' => $catalog->source,
            'item_count' => count($catalog->items ?? []),
            'created_at' => $catalog->created_at,
            'versions_count' => ($catalog->relationLoaded('versions') ? $catalog->versions->count() : 0) + 1,
            'public_url' => $this->catalogUrlService->publicUrl($catalog),
            'flows_count' => count($flows),
            'flows' => $flows,
            'store_source' => $catalog->metadata['store_source'] ?? null,
        ];
    }
}
