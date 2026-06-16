<?php

namespace Tests\Unit;

use App\Models\Company;
use App\Models\ListCatalog;
use App\Models\User;
use App\Scopes\CompanyScope;
use App\Services\Catalog\CatalogExperimentService;
use App\Services\Catalog\CatalogItemRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CatalogExperimentServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'owner']);
    }

    public function test_resolve_catalog_picks_configured_variant(): void
    {
        $company = $this->makeCompany();
        $service = app(CatalogExperimentService::class);
        $repository = app(CatalogItemRepository::class);

        $base = ListCatalog::withoutGlobalScope(CompanyScope::class)->create([
            'company_id' => $company->id,
            'name' => 'Base',
            'experiment_key' => 'homepage',
            'traffic_weight' => 50,
            'publish_status' => 'published',
            'version' => 1,
            'items' => [['id' => 'a', 'title' => 'A', 'price' => 1]],
            'columns' => [],
            'source' => 'manual',
        ]);
        $repository->replaceAllFromArray($base, $base->items);

        $variant = $service->createVariant($base, [
            'name' => 'Variant B',
            'traffic_weight' => 50,
            'publish_status' => 'published',
        ]);

        $resolved = $service->resolveCatalog('homepage', $company->id, 'visitor-fixed-key');

        $this->assertNotNull($resolved);
        $this->assertContains($resolved->id, [$base->id, $variant->id]);
    }

    private function makeCompany(): Company
    {
        $user = User::factory()->create();
        $user->assignRole('owner');

        return Company::factory()->create(['user_id' => $user->id]);
    }
}
