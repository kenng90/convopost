<?php

namespace App\Http\Controllers;

use App\Models\ListCatalog;
use App\Services\ExcelImportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ListCatalogController extends Controller
{
    protected $excelService;

    public function __construct(ExcelImportService $excelService)
    {
        $this->excelService = $excelService;
    }

    /**
     * Preview Excel file without saving
     */
    public function previewExcel(Request $request)
    {
        if (!auth()->check()) {
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
            $fullPath = storage_path('app/' . $path);

            // Parse the Excel file
            $parseResult = $this->excelService->parseExcel($fullPath);

            // Clean up temp file
            unlink($fullPath);

            return response()->json([
                'success' => true,
                'items' => $this->excelService->previewItems($parseResult['items']),
                'columns' => $parseResult['columns'],
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
        if (!auth()->check()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 401);
        }

        try {
            $request->validate([
                'file' => 'required|file|mimes:xlsx,xls,csv',
                'catalogName' => 'required|string|max:255',
                'columnMapping' => 'required|json',
            ]);

            $file = $request->file('file');
            $catalogName = $request->input('catalogName');
            $columnMapping = json_decode($request->input('columnMapping'), true);

            $path = $file->store('catalogs');
            $fullPath = storage_path('app/' . $path);

            // Parse Excel
            $parseResult = $this->excelService->parseExcel($fullPath);

            // Transform items using column mapping
            $transformedItems = $this->excelService->transformItems(
                $parseResult['items'],
                $columnMapping
            );

            // Validate items
            $this->excelService->validateItems($transformedItems);

            // Create catalog
            $catalogData = [
                'company_id' => auth()->user()->company_id,
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

            // Clean up original file
            unlink($fullPath);

            return response()->json([
                'success' => true,
                'message' => "Catalog '{$catalogName}' created with " . count($transformedItems) . ' items.',
                'catalogId' => $catalog->id,
                'items' => $transformedItems,
                'itemCount' => count($transformedItems),
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
     * Test API endpoint and get preview
     */
    public function testAPI(Request $request)
    {
        if (!auth()->check()) {
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

            if (!$response->successful()) {
                return response()->json([
                    'success' => false,
                    'message' => "API returned status {$response->status()}",
                    'status' => $response->status(),
                ], 400);
            }

            $data = $response->json();

            // Extract data from response path
            $items = $this->getValueByPath($data, $responseDataPath);

            if (!is_array($items)) {
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
        if (!auth()->check()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 401);
        }

        try {
            $companyId = auth()->user()->company_id;

            $catalogs = ListCatalog::where('company_id', $companyId)
                ->where('parent_id', null) // Only root catalogs
                ->with('versions')
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($catalog) {
                    return [
                        'id' => $catalog->id,
                        'name' => $catalog->name,
                        'version' => $catalog->version,
                        'source' => $catalog->source,
                        'item_count' => count($catalog->items ?? []),
                        'created_at' => $catalog->created_at,
                        'versions_count' => $catalog->versions->count() + 1,
                    ];
                });

            return response()->json([
                'success' => true,
                'catalogs' => $catalogs,
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
        if (!auth()->check()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 401);
        }

        try {
            $companyId = auth()->user()->company_id;
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
        if (!auth()->check()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 401);
        }

        try {
            $companyId = auth()->user()->company_id;
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
        if (!auth()->check()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 401);
        }

        try {
            $companyId = auth()->user()->company_id;
            $catalog = ListCatalog::where('id', $id)
                ->where('company_id', $companyId)
                ->firstOrFail();
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
     * Get catalog items for management
     */
    public function getItems($id)
    {
        if (!auth()->check()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 401);
        }

        try {
            $companyId = auth()->user()->company_id;
            $catalog = ListCatalog::where('id', $id)
                ->where('company_id', $companyId)
                ->firstOrFail();

            return response()->json([
                'success' => true,
                'items' => $catalog->items ?? [],
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
     * Add item to catalog
     */
    public function addItem(Request $request, $id)
    {
        if (!auth()->check()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 401);
        }

        try {
            $companyId = auth()->user()->company_id;
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
                'variants' => 'nullable|array',
                'tags' => 'nullable|array',
            ]);

            $items = $catalog->items ?? [];

            // Check if item with same ID already exists
            $exists = array_search($validated['id'], array_column($items, 'id'));
            if ($exists !== false) {
                return response()->json([
                    'success' => false,
                    'message' => 'Item with this ID already exists',
                ], 400);
            }

            // Add new item
            $newItem = [
                'id' => $validated['id'],
                'title' => $validated['title'],
                'description' => $validated['description'] ?? '',
                'price' => $validated['price'] ?? 0,
                'category' => $validated['category'] ?? '',
                'imageUrl' => $validated['imageUrl'] ?? '',
                'stockStatus' => $validated['stockStatus'] ?? 'In Stock',
                'variants' => $validated['variants'] ?? [],
                'tags' => $validated['tags'] ?? [],
            ];

            $items[] = $newItem;
            $catalog->items = $items;
            $catalog->save();

            return response()->json([
                'success' => true,
                'message' => 'Item added successfully',
                'item' => $newItem,
                'items' => $items,
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
        if (!auth()->check()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 401);
        }

        try {
            $companyId = auth()->user()->company_id;
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
                'variants' => 'nullable|array',
                'tags' => 'nullable|array',
            ]);

            $items = $catalog->items ?? [];

            // Find item by ID
            $itemIndex = null;
            foreach ($items as $index => $item) {
                if ($item['id'] === $itemId) {
                    $itemIndex = $index;
                    break;
                }
            }

            if ($itemIndex === null) {
                return response()->json([
                    'success' => false,
                    'message' => 'Item not found',
                ], 404);
            }

            // Update item
            $items[$itemIndex]['title'] = $validated['title'];
            $items[$itemIndex]['description'] = $validated['description'] ?? '';
            $items[$itemIndex]['price'] = $validated['price'] ?? 0;
            $items[$itemIndex]['category'] = $validated['category'] ?? '';
            $items[$itemIndex]['imageUrl'] = $validated['imageUrl'] ?? '';
            $items[$itemIndex]['stockStatus'] = $validated['stockStatus'] ?? 'In Stock';
            $items[$itemIndex]['variants'] = $validated['variants'] ?? [];
            $items[$itemIndex]['tags'] = $validated['tags'] ?? [];

            $catalog->items = $items;
            $catalog->save();

            return response()->json([
                'success' => true,
                'message' => 'Item updated successfully',
                'item' => $items[$itemIndex],
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
        if (!auth()->check()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 401);
        }

        try {
            $companyId = auth()->user()->company_id;
            $catalog = ListCatalog::where('id', $id)
                ->where('company_id', $companyId)
                ->firstOrFail();

            $items = $catalog->items ?? [];

            // Find and remove item
            $items = array_filter($items, function($item) use ($itemId) {
                return $item['id'] !== $itemId;
            });

            // Re-index array
            $items = array_values($items);

            $catalog->items = $items;
            $catalog->save();

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
}
