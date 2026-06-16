<?php

namespace Tests\Unit;

use App\Models\CatalogItem;
use App\Models\Company;
use App\Models\ListCatalog;
use App\Models\User;
use App\Scopes\CompanyScope;
use App\Services\Catalog\CatalogInventoryService;
use App\Services\Catalog\CatalogItemRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CatalogInventoryServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'owner']);
    }

    public function test_reserve_and_commit_reduces_available_quantity(): void
    {
        [$catalog, $item] = $this->makeCatalogWithStock(5);

        $service = app(CatalogInventoryService::class);
        $reservations = $service->reserveForCart($catalog, [['id' => $item->item_id, 'quantity' => 2]]);
        $service->commitReservation($reservations->first());

        $item->refresh();
        $this->assertSame(3, $item->quantity_available);
        $this->assertSame(0, $item->quantity_reserved);
    }

    public function test_reserve_fails_when_insufficient_stock(): void
    {
        [$catalog, $item] = $this->makeCatalogWithStock(1);

        $this->expectException(RuntimeException::class);

        app(CatalogInventoryService::class)->reserveForCart($catalog, [['id' => $item->item_id, 'quantity' => 2]]);
    }

    /**
     * @return array{0: ListCatalog, 1: CatalogItem}
     */
    private function makeCatalogWithStock(int $quantity): array
    {
        $user = User::factory()->create();
        $user->assignRole('owner');

        $company = Company::factory()->create(['user_id' => $user->id]);

        $catalog = ListCatalog::withoutGlobalScope(CompanyScope::class)->create([
            'company_id' => $company->id,
            'name' => 'Stock Catalog',
            'version' => 1,
            'items' => [],
            'columns' => [],
            'source' => 'manual',
        ]);

        app(CatalogItemRepository::class)->replaceAllFromArray($catalog, [[
            'id' => 'sku-1',
            'title' => 'Widget',
            'price' => 10,
            'quantityAvailable' => $quantity,
            'stockStatus' => 'In Stock',
        ]]);

        $item = CatalogItem::withoutGlobalScopes()->where('catalog_id', $catalog->id)->firstOrFail();

        return [$catalog->fresh(), $item];
    }
}
