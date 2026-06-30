<?php

namespace Tests\Unit;

use App\Models\Company;
use App\Models\ListCatalog;
use App\Scopes\CompanyScope;
use App\Services\Catalog\CatalogCheckoutVariableService;
use App\Services\Catalog\CatalogItemRepository;
use Modules\Flowmaker\Models\Contact;
use Modules\Flowmaker\Models\Flow;
use Tests\TestCase;

class CatalogCheckoutVariableServiceTest extends TestCase
{
    public function test_enrich_cart_items_builds_line_totals_and_text(): void
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
            'id' => 'case-1',
            'title' => 'Phone Case',
            'price' => 500,
            'quantityAvailable' => 10,
        ]]);

        $catalog->refresh();

        $service = app(CatalogCheckoutVariableService::class);
        $enriched = $service->enrichCartItems($catalog, [
            ['id' => 'case-1', 'quantity' => 2, 'variant' => 'Blue'],
        ]);

        $this->assertSame(1000.0, $enriched['total']);
        $this->assertSame(2, $enriched['item_count']);
        $this->assertStringContainsString('Phone Case (Blue) (x2)', $enriched['items_text']);
        $this->assertCount(1, $enriched['items']);
        $this->assertSame(500.0, $enriched['items'][0]['unit_price']);
    }

    public function test_store_on_contact_writes_prefixed_variables(): void
    {
        $company = Company::factory()->create();
        $catalog = ListCatalog::withoutGlobalScope(CompanyScope::class)->create([
            'company_id' => $company->id,
            'name' => 'Shop',
            'slug' => 'shop-vars',
            'version' => 1,
            'items' => [],
            'columns' => [],
            'source' => 'manual',
        ]);

        app(CatalogItemRepository::class)->replaceAllFromArray($catalog, [[
            'id' => 'item-1',
            'title' => 'Widget',
            'price' => 100,
            'quantityAvailable' => 5,
        ]]);

        $catalog->refresh();

        $contact = Contact::withoutGlobalScopes()->create([
            'name' => 'Buyer',
            'phone' => '254700000001',
            'company_id' => $company->id,
            'has_chat' => true,
            'enabled_ai_bot' => true,
        ]);

        $flow = Flow::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'name' => 'Shop Flow',
            'flow_data' => json_encode(['nodes' => [], 'edges' => []]),
        ]);

        $service = app(CatalogCheckoutVariableService::class);
        $service->storeOnContact(
            $contact,
            $flow->id,
            'shop_order',
            $catalog,
            [['id' => 'item-1', 'quantity' => 1]],
            'Order message text'
        );

        $this->assertStringContainsString('Widget', $contact->getContactStateValue($flow->id, 'shop_order_items'));
        $this->assertNotSame('', $contact->getContactStateValue($flow->id, 'shop_order_total'));
        $this->assertSame('1', $contact->getContactStateValue($flow->id, 'shop_order_item_count'));
        $this->assertSame('Order message text', $contact->getContactStateValue($flow->id, 'shop_order_message'));
        $this->assertNotNull($contact->getContactStateValue($flow->id, 'catalog_cart'));
    }

    public function test_resolve_prefix_from_flow_node_settings(): void
    {
        $company = Company::factory()->create();
        $flow = Flow::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'name' => 'Flow',
            'flow_data' => json_encode([
                'nodes' => [
                    [
                        'id' => 'catalog-1',
                        'type' => 'whatsapp_catalog',
                        'data' => [
                            'settings' => [
                                'checkoutVariablePrefix' => 'my_cart',
                            ],
                        ],
                    ],
                ],
                'edges' => [],
            ]),
        ]);

        $prefix = app(CatalogCheckoutVariableService::class)->resolvePrefixFromFlowNode($flow, 'catalog-1');

        $this->assertSame('my_cart', $prefix);
    }
}
