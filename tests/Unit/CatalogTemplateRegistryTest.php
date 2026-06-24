<?php

namespace Tests\Unit;

use App\Models\Company;
use App\Models\ListCatalog;
use App\Scopes\CompanyScope;
use App\Services\Catalog\CatalogMode;
use App\Services\Catalog\CatalogTemplateRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogTemplateRegistryTest extends TestCase
{
    use RefreshDatabase;

    public function test_presentation_for_listing_catalog_disables_cart(): void
    {
        $company = Company::factory()->create();

        $catalog = ListCatalog::withoutGlobalScope(CompanyScope::class)->create([
            'company_id' => $company->id,
            'name' => 'Listings',
            'slug' => 'listings',
            'catalog_mode' => CatalogMode::LISTING,
            'vertical' => 'real_estate',
            'version' => 1,
            'items' => [],
            'columns' => [],
            'source' => 'manual',
        ]);

        $presentation = app(CatalogTemplateRegistry::class)->presentationForCatalog($catalog);

        $this->assertSame(CatalogMode::LISTING, $presentation['mode']);
        $this->assertSame('real_estate', $presentation['vertical']);
        $this->assertFalse($presentation['supports_cart']);
        $this->assertSame('listing_status', $presentation['status_field']);
        $this->assertContains('location', $presentation['card_highlights']);
    }
}
