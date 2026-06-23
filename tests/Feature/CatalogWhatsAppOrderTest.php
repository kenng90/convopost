<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\ListCatalog;
use App\Models\User;
use App\Scopes\CompanyScope;
use App\Services\Catalog\CatalogWhatsAppOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CatalogWhatsAppOrderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'owner']);
    }

    public function test_service_falls_back_to_company_whatsapp_phone_column(): void
    {
        $company = Company::factory()->create([
            'whatsapp_phone' => '+254712345678',
        ]);

        $service = app(CatalogWhatsAppOrderService::class);

        $this->assertSame('254712345678', $service->resolveNumber($company));
        $this->assertSame('+254712345678', $service->displayValue($company));
    }

    public function test_service_prefers_configured_order_number(): void
    {
        $company = Company::factory()->create([
            'whatsapp_phone' => '+10000000001',
        ]);
        $company->setConfig('whatsapp_phone_number', '+254799999999');

        $service = app(CatalogWhatsAppOrderService::class);

        $this->assertSame('254799999999', $service->resolveNumber($company));
        $this->assertSame('+254799999999', $service->displayValue($company));
    }

    public function test_owner_can_save_commerce_settings(): void
    {
        [$owner, $company] = $this->actingOwner();

        $response = $this->actingAs($owner)
            ->withSession(['company_id' => $company->id])
            ->putJson(route('catalogs.commerce-settings'), [
                'whatsapp_order_number' => '+254712345670',
            ]);

        $response->assertOk();
        $response->assertJsonPath('commerce_settings.whatsapp_order_number', '+254712345670');
        $response->assertJsonPath('commerce_settings.whatsapp_order_number_configured', true);

        $company->refresh();
        $this->assertSame('+254712345670', $company->getConfig('whatsapp_phone_number'));
    }

    public function test_list_catalogs_includes_commerce_settings(): void
    {
        [$owner, $company] = $this->actingOwner();
        $company->setConfig('whatsapp_phone_number', '254700000001');

        $response = $this->actingAs($owner)
            ->withSession(['company_id' => $company->id])
            ->getJson(route('catalogs.list'));

        $response->assertOk();
        $response->assertJsonPath('commerce_settings.whatsapp_order_number', '254700000001');
        $response->assertJsonPath('commerce_settings.whatsapp_order_number_configured', true);
    }

    public function test_public_catalog_page_includes_whatsapp_order_number(): void
    {
        $company = Company::factory()->create([
            'subdomain' => 'test-shop',
            'whatsapp_phone' => '+254712345670',
        ]);

        $catalog = ListCatalog::withoutGlobalScope(CompanyScope::class)->create([
            'company_id' => $company->id,
            'name' => 'Shop Catalog',
            'slug' => 'shop-catalog',
            'version' => 1,
            'items' => [],
            'columns' => [],
            'source' => 'manual',
        ]);

        $response = $this->get(route('catalog.shop', [
            'subdomain' => $company->subdomain,
            'slug' => $catalog->slug,
        ]));

        $response->assertOk();
        $response->assertSee('const whatsappNumber = "254712345670"', false);
    }

    /**
     * @return array{0: User, 1: Company}
     */
    private function actingOwner(): array
    {
        $user = User::factory()->create();
        $user->assignRole('owner');
        $company = Company::factory()->create(['user_id' => $user->id]);

        return [$user, $company];
    }
}
