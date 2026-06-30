<?php

namespace Tests\Unit;

use App\Models\Company;
use App\Models\ListCatalog;
use App\Scopes\CompanyScope;
use App\Services\Catalog\CatalogItemPayloadService;
use App\Services\Catalog\CatalogMode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogItemPayloadServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_build_payload_stores_listing_fields_in_metadata(): void
    {
        $company = Company::factory()->create();

        $catalog = ListCatalog::withoutGlobalScope(CompanyScope::class)->create([
            'company_id' => $company->id,
            'name' => 'Homes',
            'slug' => 'homes',
            'catalog_mode' => CatalogMode::LISTING,
            'vertical' => 'real_estate',
            'version' => 1,
            'items' => [],
            'columns' => [],
            'source' => 'manual',
        ]);

        $payload = app(CatalogItemPayloadService::class)->buildPayload([
            'id' => 'home-1',
            'title' => 'Karen Apartment',
            'price' => 15000000,
            'location' => 'Karen',
            'bedrooms' => '3',
            'listing_status' => 'Available',
            'tags' => ['Featured'],
        ], $catalog);

        $this->assertSame('Karen', $payload['metadata']['location']);
        $this->assertSame('3', $payload['metadata']['bedrooms']);
        $this->assertSame('Available', $payload['metadata']['listing_status']);
        $this->assertSame('Karen', $payload['location']);
    }
}
