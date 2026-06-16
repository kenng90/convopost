<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\ListCatalog;
use App\Models\User;
use App\Scopes\CompanyScope;
use App\Services\Catalog\CatalogUrlService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Flowmaker\Models\Flow;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CatalogCommerceFeaturesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'owner']);
    }

    public function test_create_empty_catalog_endpoint(): void
    {
        [$owner, $company] = $this->actingOwner();

        $response = $this->actingAs($owner)
            ->withSession(['company_id' => $company->id])
            ->postJson(route('catalogs.create-empty'), [
                'name' => 'Manual Catalog',
                'description' => 'Started empty',
            ]);

        $response->assertOk();
        $response->assertJsonPath('catalog.name', 'Manual Catalog');
        $this->assertDatabaseHas('list_catalogs', [
            'company_id' => $company->id,
            'name' => 'Manual Catalog',
            'source' => 'manual',
        ]);
    }

    public function test_delete_catalog_blocked_when_used_in_flow(): void
    {
        [$owner, $company] = $this->actingOwner();

        $catalog = ListCatalog::withoutGlobalScope(CompanyScope::class)->create([
            'company_id' => $company->id,
            'name' => 'Flow Catalog',
            'slug' => 'flow-catalog',
            'version' => 1,
            'items' => [],
            'columns' => [],
            'source' => 'manual',
        ]);

        Flow::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'name' => 'Sales Flow',
            'flow_data' => json_encode([
                'nodes' => [[
                    'id' => 'catalog-node',
                    'type' => 'whatsapp_catalog',
                    'data' => ['settings' => ['catalogId' => $catalog->id]],
                ]],
                'edges' => [],
            ]),
        ]);

        $response = $this->actingAs($owner)
            ->withSession(['company_id' => $company->id])
            ->deleteJson(route('catalogs.delete', $catalog->id));

        $response->assertStatus(409);
        $this->assertDatabaseHas('list_catalogs', ['id' => $catalog->id]);
    }

    public function test_branded_shop_route_resolves_catalog_by_subdomain_and_slug(): void
    {
        $company = Company::factory()->create(['subdomain' => 'acme-shop']);

        $catalog = ListCatalog::withoutGlobalScope(CompanyScope::class)->create([
            'company_id' => $company->id,
            'name' => 'Main Store',
            'slug' => 'main-store',
            'version' => 1,
            'items' => [['id' => '1', 'title' => 'Widget', 'price' => 10, 'stockStatus' => 'In Stock']],
            'columns' => ['id', 'title', 'price'],
            'source' => 'manual',
        ]);

        $response = $this->get(route('catalog.shop', [
            'subdomain' => 'acme-shop',
            'slug' => 'main-store',
        ]));

        $response->assertOk();
        $response->assertSee('Widget');
        $response->assertSee($catalog->name);
    }

    public function test_public_catalog_url_service_prefers_branded_route(): void
    {
        $company = Company::factory()->create(['subdomain' => 'branded-co']);

        $catalog = ListCatalog::withoutGlobalScope(CompanyScope::class)->create([
            'company_id' => $company->id,
            'name' => 'Holiday',
            'slug' => 'holiday',
            'version' => 1,
            'items' => [],
            'columns' => [],
            'source' => 'manual',
        ]);

        $url = app(CatalogUrlService::class)->publicUrl($catalog, $company);

        $this->assertStringContainsString('/shop/branded-co/holiday', $url);
    }

    /**
     * @return array{0: User, 1: Company}
     */
    private function actingOwner(): array
    {
        $owner = User::factory()->create();
        $owner->assignRole('owner');
        $company = Company::factory()->create(['user_id' => $owner->id]);
        $owner->update(['company_id' => $company->id]);

        return [$owner, $company];
    }
}
