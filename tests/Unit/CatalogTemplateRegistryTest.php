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
        $this->assertSame('Inquire on WhatsApp', $presentation['inquire_cta_label']);
        $this->assertSame('Book viewing', $presentation['book_cta_label']);
        $this->assertSame('Book viewing', $presentation['cta_label']);
    }

    public function test_presentation_book_labels_by_vertical(): void
    {
        $company = Company::factory()->create();
        $registry = app(CatalogTemplateRegistry::class);

        $automotive = ListCatalog::withoutGlobalScope(CompanyScope::class)->create([
            'company_id' => $company->id,
            'name' => 'Cars',
            'slug' => 'cars',
            'catalog_mode' => CatalogMode::LISTING,
            'vertical' => 'automotive',
            'version' => 1,
            'items' => [],
            'columns' => [],
            'source' => 'manual',
        ]);

        $service = ListCatalog::withoutGlobalScope(CompanyScope::class)->create([
            'company_id' => $company->id,
            'name' => 'Spa',
            'slug' => 'spa',
            'catalog_mode' => CatalogMode::SERVICE,
            'vertical' => 'general_service',
            'version' => 1,
            'items' => [],
            'columns' => [],
            'source' => 'manual',
        ]);

        $this->assertSame('Book test drive', $registry->presentationForCatalog($automotive)['book_cta_label']);
        $this->assertSame('Book', $registry->presentationForCatalog($service)['book_cta_label']);
        $this->assertSame('Inquire on WhatsApp', $registry->presentationForCatalog($service)['inquire_cta_label']);
    }

    public function test_presentation_for_jobs_catalog_disables_booking_and_sets_apply_labels(): void
    {
        $company = Company::factory()->create();

        $catalog = ListCatalog::withoutGlobalScope(CompanyScope::class)->create([
            'company_id' => $company->id,
            'name' => 'Careers',
            'slug' => 'careers',
            'catalog_mode' => CatalogMode::LISTING,
            'vertical' => 'jobs',
            'version' => 1,
            'items' => [],
            'columns' => [],
            'source' => 'manual',
        ]);

        $presentation = app(CatalogTemplateRegistry::class)->presentationForCatalog($catalog);

        $this->assertSame('jobs', $presentation['vertical']);
        $this->assertFalse($presentation['supports_booking']);
        $this->assertTrue($presentation['supports_email_apply']);
        $this->assertSame('Apply on WhatsApp', $presentation['apply_cta_label']);
        $this->assertSame('Apply via email', $presentation['email_apply_cta_label']);
        $this->assertSame('listing_status', $presentation['status_field']);
        $this->assertSame(['Open', 'Closed', 'Filled'], $presentation['status_options']);
        $this->assertContains('company', $presentation['card_highlights']);
    }
}
