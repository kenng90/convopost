<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Config;
use App\Models\ListCatalog;
use App\Services\Catalog\CatalogStoreSyncService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CatalogWebhookController extends Controller
{
    public function __construct(
        protected CatalogStoreSyncService $syncService,
    ) {
    }

    public function shopify(Request $request, string $token)
    {
        $company = $this->resolveCompanyByToken($token);
        if (! $company) {
            return response()->json(['success' => false], 401);
        }

        $topic = $request->header('X-Shopify-Topic', 'unknown');
        Log::info('Shopify catalog webhook', ['topic' => $topic, 'company_id' => $company->id]);

        $this->syncLinkedCatalogs($company, 'shopify');

        return response()->json(['success' => true]);
    }

    public function woocommerce(Request $request, string $token)
    {
        $company = $this->resolveCompanyByToken($token);
        if (! $company) {
            return response()->json(['success' => false], 401);
        }

        Log::info('WooCommerce catalog webhook', [
            'topic' => $request->input('topic', 'unknown'),
            'company_id' => $company->id,
        ]);

        $this->syncLinkedCatalogs($company, 'woocommerce');

        return response()->json(['success' => true]);
    }

    private function resolveCompanyByToken(string $token): ?Company
    {
        $config = Config::query()
            ->where('key', 'plain_token')
            ->where('value', $token)
            ->where('model_type', Company::class)
            ->orderByDesc('id')
            ->first();

        return $config ? Company::find($config->model_id) : null;
    }

    private function syncLinkedCatalogs(Company $company, string $storeType): void
    {
        ListCatalog::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->whereJsonContains('metadata->store_source', $storeType)
            ->each(function (ListCatalog $catalog) use ($storeType) {
                try {
                    $this->syncService->pullFromStore($catalog, $storeType);
                } catch (\Throwable $e) {
                    Log::error('Webhook catalog sync failed', [
                        'catalog_id' => $catalog->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            });
    }
}
