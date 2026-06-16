<?php

namespace Tests\Feature;

use App\Models\CatalogItem;
use App\Models\Company;
use App\Models\ListCatalog;
use App\Models\User;
use App\Scopes\CompanyScope;
use App\Services\Catalog\CatalogItemRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CatalogStrategicCommerceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'owner']);
    }

    public function test_add_item_persists_relational_catalog_item(): void
    {
        [$owner, $company, $catalog] = $this->actingOwnerWithCatalog();

        $response = $this->actingAs($owner)
            ->withSession(['company_id' => $company->id])
            ->postJson(route('catalogs.items.add', $catalog->id), [
                'id' => 'rel-1',
                'title' => 'Relational Product',
                'price' => 25,
                'quantityAvailable' => 10,
            ]);

        $response->assertOk();

        $this->assertDatabaseHas('catalog_items', [
            'catalog_id' => $catalog->id,
            'item_id' => 'rel-1',
            'title' => 'Relational Product',
            'quantity_available' => 10,
        ]);
    }

    public function test_checkout_rejects_oversell(): void
    {
        [$owner, $company, $catalog] = $this->actingOwnerWithCatalog();

        app(CatalogItemRepository::class)->replaceAllFromArray($catalog, [[
            'id' => 'low-stock',
            'title' => 'Limited',
            'price' => 5,
            'quantityAvailable' => 1,
            'stockStatus' => 'In Stock',
        ]]);

        $response = $this->postJson(route('catalog.generate-order', $catalog->id), [
            'items' => [['id' => 'low-stock', 'quantity' => 3]],
        ]);

        $response->assertStatus(409);
        $this->assertStringContainsString('Insufficient stock', $response->json('message'));
    }

    public function test_collection_crud_endpoints(): void
    {
        [$owner, $company, $catalog] = $this->actingOwnerWithCatalog();

        app(CatalogItemRepository::class)->replaceAllFromArray($catalog, [[
            'id' => 'c-1',
            'title' => 'Collection Item',
            'price' => 9,
        ]]);

        $create = $this->actingAs($owner)
            ->withSession(['company_id' => $company->id])
            ->postJson(route('catalogs.collections.create'), [
                'name' => 'Summer',
                'item_ids' => ['c-1'],
            ]);

        $create->assertOk();
        $collectionId = $create->json('collection.id');

        $list = $this->actingAs($owner)
            ->withSession(['company_id' => $company->id])
            ->getJson(route('catalogs.collections.list'));

        $list->assertOk();
        $list->assertJsonPath('collections.0.name', 'Summer');

        $this->assertSame(1, CatalogItem::withoutGlobalScopes()->where('item_id', 'c-1')->count());
    }

    /**
     * @return array{0: User, 1: Company, 2: ListCatalog}
     */
    private function actingOwnerWithCatalog(): array
    {
        $user = User::factory()->create();
        $user->assignRole('owner');
        $company = Company::factory()->create(['user_id' => $user->id]);

        $catalog = ListCatalog::withoutGlobalScope(CompanyScope::class)->create([
            'company_id' => $company->id,
            'name' => 'Test Catalog',
            'slug' => 'test-catalog',
            'version' => 1,
            'items' => [],
            'columns' => [],
            'source' => 'manual',
        ]);

        return [$user, $company, $catalog];
    }
}
