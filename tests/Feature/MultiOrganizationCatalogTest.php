<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\ListCatalog;
use App\Models\User;
use App\Scopes\CompanyScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MultiOrganizationCatalogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'owner']);
    }

    public function test_catalog_list_uses_active_organization_from_session(): void
    {
        $owner = User::factory()->create();
        $owner->assignRole('owner');

        $companyA = Company::factory()->create(['user_id' => $owner->id, 'name' => 'Org A']);
        $companyB = Company::factory()->create(['user_id' => $owner->id, 'name' => 'Org B']);
        $owner->update(['company_id' => $companyA->id]);

        ListCatalog::withoutGlobalScope(CompanyScope::class)->create([
            'company_id' => $companyA->id,
            'name' => 'Catalog A',
            'version' => 1,
            'items' => [['name' => 'Item A']],
            'source' => 'excel',
        ]);

        ListCatalog::withoutGlobalScope(CompanyScope::class)->create([
            'company_id' => $companyB->id,
            'name' => 'Catalog B',
            'version' => 1,
            'items' => [['name' => 'Item B']],
            'source' => 'excel',
        ]);

        $responseForB = $this->actingAs($owner)
            ->withSession(['company_id' => $companyB->id])
            ->getJson(route('catalogs.list'));

        $responseForB->assertOk();
        $responseForB->assertJsonPath('catalogs.0.name', 'Catalog B');
        $responseForB->assertJsonMissing(['name' => 'Catalog A']);

        $responseForA = $this->actingAs($owner)
            ->withSession(['company_id' => $companyA->id])
            ->getJson(route('catalogs.list'));

        $responseForA->assertOk();
        $responseForA->assertJsonPath('catalogs.0.name', 'Catalog A');
        $responseForA->assertJsonMissing(['name' => 'Catalog B']);
    }

    public function test_company_switch_updates_last_active_organization(): void
    {
        $owner = User::factory()->create();
        $owner->assignRole('owner');

        $companyA = Company::factory()->create(['user_id' => $owner->id]);
        $companyB = Company::factory()->create(['user_id' => $owner->id]);
        $owner->update(['company_id' => $companyA->id]);

        $this->actingAs($owner)
            ->get(route('admin.companies.switch', $companyB->id))
            ->assertRedirect(route('home'));

        $this->assertSame($companyB->id, $owner->fresh()->company_id);
        $this->assertSame($companyB->id, session('company_id'));
    }
}
