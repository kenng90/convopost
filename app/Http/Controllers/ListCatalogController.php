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
            $catalog = ListCatalog::create([
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
            ]);

            // Clean up original file
            unlink($fullPath);

            return response()->json([
                'success' => true,
                'message' => "Catalog '{$catalogName}' created with " . count($transformedItems) . ' items',
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
