<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\ListCatalog;
use App\Scopes\CompanyScope;
use App\Services\Catalog\CatalogItemRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogInvoiceDeliveryAddressTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_invoice_requires_delivery_address(): void
    {
        $company = Company::factory()->create();
        $catalog = ListCatalog::withoutGlobalScope(CompanyScope::class)->create([
            'company_id' => $company->id,
            'name' => 'Shop',
            'slug' => 'shop',
            'version' => 1,
            'items' => [],
            'columns' => [],
            'source' => 'manual',
        ]);

        app(CatalogItemRepository::class)->replaceAllFromArray($catalog, [[
            'id' => 'sku-1',
            'title' => 'Product',
            'price' => 10,
            'quantityAvailable' => 5,
        ]]);

        $response = $this->postJson(route('catalog.create-invoice', $catalog->id), [
            'items' => [['id' => 'sku-1', 'quantity' => 1]],
            'customerPhone' => '254712345678',
            'amount' => 10,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['deliveryAddress']);
    }

    public function test_create_invoice_stores_delivery_address(): void
    {
        $company = Company::factory()->create();
        $catalog = ListCatalog::withoutGlobalScope(CompanyScope::class)->create([
            'company_id' => $company->id,
            'name' => 'Shop',
            'slug' => 'shop',
            'version' => 1,
            'items' => [],
            'columns' => [],
            'source' => 'manual',
        ]);

        app(CatalogItemRepository::class)->replaceAllFromArray($catalog, [[
            'id' => 'sku-1',
            'title' => 'Product',
            'price' => 10,
            'quantityAvailable' => 5,
        ]]);

        $response = $this->postJson(route('catalog.create-invoice', $catalog->id), [
            'items' => [['id' => 'sku-1', 'quantity' => 1]],
            'customerPhone' => '254712345678',
            'deliveryAddress' => 'Kilimani, Nairobi',
            'amount' => 10,
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('invoices', [
            'catalog_id' => $catalog->id,
            'customer_phone' => '254712345678',
            'delivery_address' => 'Kilimani, Nairobi',
        ]);
    }
}
