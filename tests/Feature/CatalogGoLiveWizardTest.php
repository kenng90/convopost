<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\ListCatalog;
use App\Models\User;
use App\Scopes\CompanyScope;
use App\Services\Catalog\CatalogMode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CatalogGoLiveWizardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'owner']);
    }

    public function test_go_live_status_endpoint_returns_summary(): void
    {
        [$owner, $company] = $this->actingOwner();

        $catalog = ListCatalog::withoutGlobalScope(CompanyScope::class)->create([
            'company_id' => $company->id,
            'name' => 'Launch Shop',
            'slug' => 'launch-shop',
            'catalog_mode' => CatalogMode::COMMERCE,
            'vertical' => 'retail',
            'version' => 1,
            'items' => [[
                'id' => 'SKU1',
                'title' => 'Widget',
                'price' => 100,
            ]],
            'columns' => [],
            'source' => 'manual',
        ]);

        $response = $this->actingAs($owner)
            ->withSession(['company_id' => $company->id])
            ->getJson(route('catalogs.go-live', ['catalog_id' => $catalog->id]));

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('catalog.id', $catalog->id);
        $response->assertJsonStructure([
            'completed',
            'total',
            'percent',
            'steps' => [
                '*' => ['key', 'title', 'description', 'completed'],
            ],
            'catalog' => ['id', 'name', 'mode', 'vertical', 'public_url'],
        ]);

        $this->assertTrue(collect($response->json('steps'))->contains(
            fn ($step) => $step['key'] === 'catalog' && $step['completed'] === true
        ));
        $this->assertTrue(collect($response->json('steps'))->contains(
            fn ($step) => $step['key'] === 'items' && $step['completed'] === true
        ));
    }

    public function test_go_live_status_without_catalog_id_uses_latest(): void
    {
        [$owner, $company] = $this->actingOwner();

        ListCatalog::withoutGlobalScope(CompanyScope::class)->create([
            'company_id' => $company->id,
            'name' => 'Older',
            'slug' => 'older',
            'version' => 1,
            'items' => [],
            'columns' => [],
            'source' => 'manual',
        ]);

        $latest = ListCatalog::withoutGlobalScope(CompanyScope::class)->create([
            'company_id' => $company->id,
            'name' => 'Newest',
            'slug' => 'newest',
            'version' => 1,
            'items' => [],
            'columns' => [],
            'source' => 'manual',
        ]);

        $response = $this->actingAs($owner)
            ->withSession(['company_id' => $company->id])
            ->getJson(route('catalogs.go-live'));

        $response->assertOk();
        $response->assertJsonPath('catalog.id', $latest->id);
    }

    public function test_go_live_status_requires_authentication(): void
    {
        $this->getJson(route('catalogs.go-live'))->assertUnauthorized();
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
