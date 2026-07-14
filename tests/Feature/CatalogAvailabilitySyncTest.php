<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\ListCatalog;
use App\Models\User;
use App\Scopes\CompanyScope;
use App\Services\Catalog\CatalogAvailabilitySyncService;
use App\Services\Catalog\CatalogMode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Mockery;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CatalogAvailabilitySyncTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'owner']);
    }

    public function test_sync_availability_endpoint_updates_listing_catalog(): void
    {
        [$owner, $company] = $this->actingOwner();

        $catalog = ListCatalog::withoutGlobalScope(CompanyScope::class)->create([
            'company_id' => $company->id,
            'name' => 'Salon Services',
            'slug' => 'salon-services',
            'catalog_mode' => CatalogMode::SERVICE,
            'vertical' => 'services',
            'version' => 1,
            'items' => [[
                'id' => 'SVC1',
                'title' => 'Haircut',
                'price' => 500,
            ]],
            'columns' => [],
            'source' => 'manual',
        ]);

        $this->mock(CatalogAvailabilitySyncService::class, function ($mock) use ($catalog) {
            $mock->shouldReceive('syncCatalog')
                ->once()
                ->with(Mockery::on(fn ($arg) => $arg->id === $catalog->id))
                ->andReturn(1);
        });

        $response = $this->actingAs($owner)
            ->withSession(['company_id' => $company->id])
            ->postJson(route('catalogs.sync-availability', $catalog->id));

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('updated', 1);
    }

    public function test_sync_availability_rejects_commerce_catalogs(): void
    {
        [$owner, $company] = $this->actingOwner();

        $catalog = ListCatalog::withoutGlobalScope(CompanyScope::class)->create([
            'company_id' => $company->id,
            'name' => 'Shop',
            'slug' => 'shop',
            'catalog_mode' => CatalogMode::COMMERCE,
            'vertical' => 'retail',
            'version' => 1,
            'items' => [],
            'columns' => [],
            'source' => 'manual',
        ]);

        $response = $this->actingAs($owner)
            ->withSession(['company_id' => $company->id])
            ->postJson(route('catalogs.sync-availability', $catalog->id));

        $response->assertStatus(400);
        $response->assertJsonPath('success', false);
    }

    public function test_catalog_sync_availability_command_accepts_company_option(): void
    {
        $company = Company::factory()->create();

        $this->mock(CatalogAvailabilitySyncService::class, function ($mock) use ($company) {
            $mock->shouldReceive('syncCompany')
                ->once()
                ->with(Mockery::on(fn ($arg) => $arg->id === $company->id))
                ->andReturn(0);
        });

        $exit = Artisan::call('catalog:sync-availability', ['--company' => $company->id]);

        $this->assertSame(0, $exit);
    }

    public function test_catalog_canonicalize_items_command_runs(): void
    {
        $company = Company::factory()->create();

        $exit = Artisan::call('catalog:canonicalize-items', ['--company' => $company->id]);

        $this->assertSame(0, $exit);
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
