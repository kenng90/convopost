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

class CatalogListingModeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'owner']);
    }

    public function test_templates_endpoint_returns_modes_and_verticals(): void
    {
        [$owner, $company] = $this->actingOwner();

        $response = $this->actingAs($owner)
            ->withSession(['company_id' => $company->id])
            ->getJson(route('catalogs.templates'));

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonStructure([
            'modes' => [
                '*' => ['key', 'label', 'verticals'],
            ],
        ]);
    }

    public function test_create_empty_listing_catalog_with_real_estate_vertical(): void
    {
        [$owner, $company] = $this->actingOwner();

        $response = $this->actingAs($owner)
            ->withSession(['company_id' => $company->id])
            ->postJson(route('catalogs.create-empty'), [
                'name' => 'Karen Homes',
                'description' => 'Apartments and houses',
                'catalog_mode' => CatalogMode::LISTING,
                'vertical' => 'real_estate',
            ]);

        $response->assertOk();
        $response->assertJsonPath('catalog.catalog_mode', CatalogMode::LISTING);
        $response->assertJsonPath('catalog.vertical', 'real_estate');
        $response->assertJsonPath('catalog.presentation.supports_cart', false);

        $this->assertDatabaseHas('list_catalogs', [
            'company_id' => $company->id,
            'name' => 'Karen Homes',
            'catalog_mode' => CatalogMode::LISTING,
            'vertical' => 'real_estate',
        ]);
    }

    public function test_public_listing_catalog_shows_inquiry_cta_not_cart(): void
    {
        [, $company] = $this->actingOwner();

        $catalog = ListCatalog::withoutGlobalScope(CompanyScope::class)->create([
            'company_id' => $company->id,
            'name' => 'Vehicle Yard',
            'slug' => 'vehicle-yard',
            'catalog_mode' => CatalogMode::LISTING,
            'vertical' => 'automotive',
            'version' => 1,
            'items' => [[
                'id' => 'car-1',
                'title' => 'Toyota RAV4 2021',
                'description' => 'Low mileage SUV',
                'price' => 3200000,
                'category' => 'SUV',
                'imageUrl' => '',
                'metadata' => [
                    'make' => 'Toyota',
                    'model' => 'RAV4',
                    'year' => '2021',
                    'listing_status' => 'Available',
                ],
            ]],
            'columns' => ['id', 'title', 'price', 'make', 'model'],
            'source' => 'manual',
        ]);

        $response = $this->get(route('catalog.public', $catalog->id));

        $response->assertOk();
        $response->assertSee('Inquire on WhatsApp', false);
        $response->assertSee('Toyota RAV4 2021', false);
        $response->assertDontSee('Your Cart', false);
        $response->assertDontSee('Add to Cart', false);
    }

    public function test_generate_inquiry_endpoint_returns_whatsapp_message(): void
    {
        [, $company] = $this->actingOwner();
        $company->setConfig('whatsapp_phone_number', '+254712345678');

        $catalog = ListCatalog::withoutGlobalScope(CompanyScope::class)->create([
            'company_id' => $company->id,
            'name' => 'Beach Homes',
            'slug' => 'beach-homes',
            'catalog_mode' => CatalogMode::LISTING,
            'vertical' => 'real_estate',
            'version' => 1,
            'items' => [[
                'id' => 'home-1',
                'title' => '3BR Apartment',
                'description' => 'Sea view',
                'price' => 15000000,
                'metadata' => [
                    'location' => 'Diani',
                    'bedrooms' => '3',
                    'listing_status' => 'Available',
                ],
            ]],
            'columns' => [],
            'source' => 'manual',
        ]);

        $response = $this->postJson(route('catalog.generate-inquiry', $catalog->id), [
            'item_id' => 'home-1',
        ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonStructure(['message', 'whatsapp_url']);
        $response->assertJsonPath('whatsapp_url', fn ($url) => is_string($url) && str_contains($url, 'wa.me'));
    }

    public function test_generate_inquiry_rejected_for_commerce_catalog(): void
    {
        [, $company] = $this->actingOwner();

        $catalog = ListCatalog::withoutGlobalScope(CompanyScope::class)->create([
            'company_id' => $company->id,
            'name' => 'Shop',
            'slug' => 'shop',
            'catalog_mode' => CatalogMode::COMMERCE,
            'vertical' => 'retail',
            'version' => 1,
            'items' => [[
                'id' => 'sku-1',
                'title' => 'T-Shirt',
                'price' => 1200,
            ]],
            'columns' => [],
            'source' => 'manual',
        ]);

        $response = $this->postJson(route('catalog.generate-inquiry', $catalog->id), [
            'item_id' => 'sku-1',
        ]);

        $response->assertStatus(422);
    }

    public function test_owner_can_add_listing_item_with_vertical_fields(): void
    {
        [$owner, $company] = $this->actingOwner();

        $catalog = ListCatalog::withoutGlobalScope(CompanyScope::class)->create([
            'company_id' => $company->id,
            'name' => 'Estates',
            'slug' => 'estates',
            'catalog_mode' => CatalogMode::LISTING,
            'vertical' => 'real_estate',
            'version' => 1,
            'items' => [],
            'columns' => [],
            'source' => 'manual',
        ]);

        $response = $this->actingAs($owner)
            ->withSession(['company_id' => $company->id])
            ->postJson(route('catalogs.items.add', $catalog->id), [
                'id' => 'home-22',
                'title' => 'Diani Villa',
                'price' => 22000000,
                'location' => 'Diani',
                'bedrooms' => '4',
                'listing_status' => 'Available',
            ]);

        $response->assertOk();
        $response->assertJsonPath('item.metadata.location', 'Diani');
        $response->assertJsonPath('item.metadata.bedrooms', '4');
    }

    public function test_analytics_summary_includes_listing_inquiries(): void
    {
        [, $company] = $this->actingOwner();

        $catalog = ListCatalog::withoutGlobalScope(CompanyScope::class)->create([
            'company_id' => $company->id,
            'name' => 'Listings',
            'slug' => 'listings',
            'catalog_mode' => CatalogMode::LISTING,
            'vertical' => 'general_listing',
            'version' => 1,
            'items' => [],
            'columns' => [],
            'source' => 'manual',
        ]);

        app(\App\Services\Catalog\CatalogAnalyticsService::class)->record(
            $company->id,
            $catalog->id,
            'listing_inquiry',
            ['item_id' => 'x1']
        );

        $summary = app(\App\Services\Catalog\CatalogAnalyticsService::class)->summary($catalog->id);

        $this->assertSame(1, $summary['listing_inquiries']);
    }

    public function test_generate_inquiry_with_flow_token_stores_pending_instead_of_resuming_immediately(): void
    {
        [, $company] = $this->actingOwner();

        $catalog = ListCatalog::withoutGlobalScope(CompanyScope::class)->create([
            'company_id' => $company->id,
            'name' => 'Estates',
            'slug' => 'estates-flow',
            'catalog_mode' => CatalogMode::LISTING,
            'vertical' => 'real_estate',
            'version' => 1,
            'items' => [[
                'id' => 'home-flow',
                'title' => 'Flow Home',
                'price' => 1000000,
            ]],
            'columns' => [],
            'source' => 'manual',
        ]);

        $token = app(\App\Services\Catalog\CatalogFlowCallbackService::class)->makeToken(9, 42, 'node-1', $catalog->id);

        \Illuminate\Support\Facades\Queue::fake();

        $response = $this->postJson(route('catalog.generate-inquiry', $catalog->id), [
            'item_id' => 'home-flow',
            'flow_token' => $token,
        ]);

        $response->assertOk();
        \Illuminate\Support\Facades\Queue::assertNotPushed(\Modules\Flowmaker\Jobs\ResumeFlowFromListingInquiry::class);
    }

    public function test_owner_can_add_listing_with_gallery_images(): void
    {
        [$owner, $company] = $this->actingOwner();

        $catalog = ListCatalog::withoutGlobalScope(CompanyScope::class)->create([
            'company_id' => $company->id,
            'name' => 'Gallery',
            'slug' => 'gallery',
            'catalog_mode' => CatalogMode::LISTING,
            'vertical' => 'general_listing',
            'version' => 1,
            'items' => [],
            'columns' => [],
            'source' => 'manual',
        ]);

        $response = $this->actingAs($owner)
            ->withSession(['company_id' => $company->id])
            ->postJson(route('catalogs.items.add', $catalog->id), [
                'id' => 'gal-1',
                'title' => 'Gallery Listing',
                'imageUrl' => 'https://example.com/cover.jpg',
                'imagesText' => "https://example.com/one.jpg\nhttps://example.com/two.jpg",
            ]);

        $response->assertOk();
        $response->assertJsonPath('item.images.0', 'https://example.com/cover.jpg');
        $this->assertCount(3, $response->json('item.images'));
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
