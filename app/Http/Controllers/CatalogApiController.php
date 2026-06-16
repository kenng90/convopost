<?php

namespace App\Http\Controllers;

use App\Models\CatalogCollection;
use App\Models\Company;
use App\Models\ListCatalog;
use App\Models\User;
use App\Scopes\CompanyScope;
use App\Services\Catalog\ApiCatalogImportService;
use App\Services\Catalog\CatalogExperimentService;
use App\Services\Catalog\CatalogItemRepository;
use App\Services\Catalog\CatalogStoreSyncService;
use App\Services\Catalog\CatalogUrlService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\PersonalAccessToken;

class CatalogApiController extends Controller
{
    public function __construct(
        protected CatalogItemRepository $catalogItemRepository,
        protected CatalogUrlService $catalogUrlService,
        protected CatalogStoreSyncService $catalogStoreSyncService,
        protected ApiCatalogImportService $apiCatalogImportService,
        protected CatalogExperimentService $catalogExperimentService,
    ) {
    }

    public function me(Request $request)
    {
        $company = $this->resolveCompany();

        return response()->json([
            'success' => true,
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'subdomain' => $company->subdomain,
            ],
        ]);
    }

    public function listCatalogs(Request $request)
    {
        $company = $this->resolveCompany();

        $catalogs = ListCatalog::withoutGlobalScope(CompanyScope::class)
            ->where('company_id', $company->id)
            ->whereNull('parent_id')
            ->orderByDesc('id')
            ->get()
            ->map(fn (ListCatalog $catalog) => $this->formatCatalog($catalog));

        return response()->json(['success' => true, 'catalogs' => $catalogs]);
    }

    public function showCatalog(Request $request, int $id)
    {
        $catalog = $this->findCatalog($id);

        return response()->json([
            'success' => true,
            'catalog' => $this->formatCatalog($catalog, true),
        ]);
    }

    public function listItems(Request $request, int $id)
    {
        $catalog = $this->findCatalog($id);

        return response()->json([
            'success' => true,
            'catalog_id' => $catalog->id,
            'items' => $this->catalogItemRepository->getItemsArray($catalog),
        ]);
    }

    public function listCollections(Request $request)
    {
        $company = $this->resolveCompany();

        $collections = CatalogCollection::withoutGlobalScope(CompanyScope::class)
            ->where('company_id', $company->id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get()
            ->map(function (CatalogCollection $collection) {
                return [
                    'id' => $collection->id,
                    'name' => $collection->name,
                    'slug' => $collection->slug,
                    'description' => $collection->description,
                    'item_ids' => $collection->items()->pluck('item_id')->all(),
                ];
            });

        return response()->json(['success' => true, 'collections' => $collections]);
    }

    public function showCollection(Request $request, string $slug)
    {
        $company = $this->resolveCompany();

        $collection = CatalogCollection::withoutGlobalScope(CompanyScope::class)
            ->where('company_id', $company->id)
            ->where('slug', $slug)
            ->where('is_active', true)
            ->firstOrFail();

        return response()->json([
            'success' => true,
            'collection' => [
                'id' => $collection->id,
                'name' => $collection->name,
                'slug' => $collection->slug,
                'description' => $collection->description,
                'items' => $collection->items()->get()->map(fn ($item) => $item->toCatalogArray())->values()->all(),
            ],
        ]);
    }

    public function syncCatalog(Request $request, int $id)
    {
        $catalog = $this->findCatalog($id);
        $storeType = $catalog->metadata['store_source'] ?? null;

        if (in_array($storeType, ['shopify', 'woocommerce'], true)) {
            $result = $this->catalogStoreSyncService->pullFromStore($catalog, $storeType);

            return response()->json([
                'success' => true,
                'message' => 'Store catalog synced.',
                'stats' => $result['stats'] ?? [],
            ]);
        }

        if ($catalog->source === 'api' && is_array($catalog->api_config) && ! empty($catalog->api_config['url'])) {
            $stats = $this->apiCatalogImportService->importIntoCatalog($catalog);

            return response()->json([
                'success' => true,
                'message' => 'API catalog synced.',
                'stats' => $stats,
            ]);
        }

        return response()->json(['success' => false, 'message' => 'Catalog has no sync source configured.'], 400);
    }

    public function listExperiments(Request $request)
    {
        $company = $this->resolveCompany();

        return response()->json([
            'success' => true,
            'experiments' => $this->catalogExperimentService->listExperiments($company->id),
        ]);
    }

    private function resolveCompany(): Company
    {
        if (! Auth::check()) {
            $token = PersonalAccessToken::findToken(request()->input('token'));
            if (! $token) {
                abort(401, 'Invalid token');
            }

            Auth::login(User::findOrFail($token->tokenable_id));
        }

        $user = auth()->user();
        $company = $user?->currentCompany();

        if (! $company) {
            abort(403, 'No active company.');
        }

        return $company;
    }

    private function findCatalog(int $id): ListCatalog
    {
        $company = $this->resolveCompany();

        return ListCatalog::withoutGlobalScope(CompanyScope::class)
            ->where('company_id', $company->id)
            ->whereNull('parent_id')
            ->findOrFail($id);
    }

    private function formatCatalog(ListCatalog $catalog, bool $includeItems = false): array
    {
        $data = [
            'id' => $catalog->id,
            'name' => $catalog->name,
            'slug' => $catalog->slug,
            'description' => $catalog->description,
            'source' => $catalog->source,
            'publish_status' => $catalog->publish_status,
            'experiment_key' => $catalog->experiment_key,
            'traffic_weight' => $catalog->traffic_weight,
            'public_url' => $this->catalogUrlService->publicUrl($catalog),
            'item_count' => count($this->catalogItemRepository->getItemsArray($catalog)),
            'store_source' => $catalog->metadata['store_source'] ?? null,
        ];

        if ($includeItems) {
            $data['items'] = $this->catalogItemRepository->getItemsArray($catalog);
            $data['api_config'] = $catalog->api_config;
        }

        return $data;
    }
}
