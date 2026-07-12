<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\ListCatalog;
use App\Scopes\CompanyScope;
use App\Services\Catalog\CatalogMode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CatalogCartAbandonedTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'owner']);
    }

    public function test_cart_sync_and_abandon_endpoints_accept_visitor_key(): void
    {
        $company = Company::factory()->create();
        $catalog = ListCatalog::withoutGlobalScope(CompanyScope::class)->create([
            'company_id' => $company->id,
            'name' => 'Shop',
            'slug' => 'shop-cart',
            'catalog_mode' => CatalogMode::COMMERCE,
            'vertical' => 'retail',
            'version' => 1,
            'items' => [[
                'id' => 'sku-1',
                'title' => 'Mug',
                'price' => 500,
                'stockStatus' => 'In Stock',
            ]],
            'columns' => [],
            'source' => 'manual',
        ]);

        $sync = $this->postJson(route('catalog.cart.sync', $catalog->id), [
            'visitor_key' => 'visitor-abc-123',
            'items' => [
                ['id' => 'sku-1', 'quantity' => 2],
            ],
            'customerPhone' => '254712345678',
            'customerName' => 'Jane',
        ]);

        $sync->assertOk();
        $sync->assertJsonPath('success', true);
        $sync->assertJsonPath('session.item_count', 2);

        $abandon = $this->postJson(route('catalog.cart.abandon', $catalog->id), [
            'visitor_key' => 'visitor-abc-123',
            'items' => [
                ['id' => 'sku-1', 'quantity' => 2],
            ],
            'customerPhone' => '254712345678',
            'customerName' => 'Jane',
        ]);

        $abandon->assertOk();
        $abandon->assertJsonPath('success', true);
        $abandon->assertJsonPath('abandoned', true);
    }

    public function test_public_commerce_catalog_includes_cart_sync_javascript(): void
    {
        $company = Company::factory()->create();
        $catalog = ListCatalog::withoutGlobalScope(CompanyScope::class)->create([
            'company_id' => $company->id,
            'name' => 'Retail Shop',
            'slug' => 'retail-shop',
            'catalog_mode' => CatalogMode::COMMERCE,
            'vertical' => 'retail',
            'version' => 1,
            'items' => [[
                'id' => 'sku-1',
                'title' => 'Mug',
                'price' => 500,
                'stockStatus' => 'In Stock',
            ]],
            'columns' => [],
            'source' => 'manual',
        ]);

        $response = $this->get(route('catalog.public', $catalog->id));

        $response->assertOk();
        $response->assertSee('catalog_visitor_'.$catalog->id, false);
        $response->assertSee('/cart/sync', false);
        $response->assertSee('/cart/abandon', false);
        $response->assertSee('visitor_key', false);
    }
}
