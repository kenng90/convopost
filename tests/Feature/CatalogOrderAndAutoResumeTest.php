<?php

namespace Tests\Feature;

use App\Models\CatalogOrder;
use App\Models\Company;
use App\Models\ListCatalog;
use App\Scopes\CompanyScope;
use App\Services\Catalog\CatalogCheckoutPendingService;
use App\Services\Catalog\CatalogFlowCallbackService;
use App\Services\Catalog\CatalogItemRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Modules\Flowmaker\Jobs\ResumeFlowFromCatalogCheckout;
use Modules\Flowmaker\Models\Contact;
use Modules\Flowmaker\Models\Flow;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CatalogOrderAndAutoResumeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'owner']);
    }

    public function test_generate_order_persists_catalog_order(): void
    {
        [$company, $catalog, $contact, $flow, $flowToken] = $this->catalogFlowContext();

        app(CatalogItemRepository::class)->replaceAllFromArray($catalog, [[
            'id' => 'iphone-15',
            'title' => 'Apple iPhone 15 Pro Max',
            'price' => 1000,
            'quantityAvailable' => 5,
        ]]);

        $response = $this->postJson(route('catalog.generate-order', $catalog->id), [
            'items' => [['id' => 'iphone-15', 'quantity' => 2]],
            'customerName' => 'Buyer',
            'customerPhone' => $contact->phone,
            'flow_token' => $flowToken,
            'visitor_key' => 'visitor-order-1',
        ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonStructure(['order' => ['id', 'order_number', 'public_uuid', 'status']]);

        $this->assertDatabaseHas('catalog_orders', [
            'company_id' => $company->id,
            'catalog_id' => $catalog->id,
            'customer_phone' => $contact->phone,
            'checkout_channel' => 'whatsapp',
        ]);

        $order = CatalogOrder::withoutGlobalScopes()->where('catalog_id', $catalog->id)->first();
        $this->assertNotNull($order);
        $this->assertSame(2000.0, (float) $order->total_amount);
        $this->assertCount(1, $order->items);
        $this->assertSame('iphone-15', $order->items->first()->item_id);
    }

    public function test_auto_resume_dispatches_when_node_setting_enabled(): void
    {
        Queue::fake();

        [$company, $catalog, $contact, $flow, $flowToken] = $this->catalogFlowContext(autoResumeFlow: true);

        app(CatalogItemRepository::class)->replaceAllFromArray($catalog, [[
            'id' => 'iphone-15',
            'title' => 'Apple iPhone 15 Pro Max',
            'price' => 1,
            'quantityAvailable' => 5,
        ]]);

        $contact->setContactState($flow->id, 'current_node', 'catalog-1');

        $this->postJson(route('catalog.generate-order', $catalog->id), [
            'items' => [['id' => 'iphone-15', 'quantity' => 1]],
            'flow_token' => $flowToken,
        ])->assertOk();

        Queue::assertPushed(ResumeFlowFromCatalogCheckout::class, function (ResumeFlowFromCatalogCheckout $job) use ($flow, $contact, $catalog) {
            return $job->flowId === $flow->id
                && $job->contactId === $contact->id
                && $job->catalogId === $catalog->id
                && $job->productId === 'iphone-15';
        });

        $contact->refresh();
        $this->assertSame('1', $contact->getContactStateValue($flow->id, CatalogCheckoutPendingService::PENDING_FLAG));
    }

    public function test_create_invoice_persists_order_and_can_auto_resume(): void
    {
        Queue::fake();

        [$company, $catalog, $contact, $flow, $flowToken] = $this->catalogFlowContext(autoResumeFlow: true);

        app(CatalogItemRepository::class)->replaceAllFromArray($catalog, [[
            'id' => 'iphone-15',
            'title' => 'Apple iPhone 15 Pro Max',
            'price' => 1,
            'quantityAvailable' => 5,
        ]]);

        $response = $this->postJson(route('catalog.create-invoice', $catalog->id), [
            'items' => [['id' => 'iphone-15', 'quantity' => 1]],
            'customerPhone' => $contact->phone,
            'deliveryAddress' => '123 Market Street, Nairobi',
            'amount' => 1,
            'flow_token' => $flowToken,
            'visitor_key' => 'visitor-invoice-1',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('invoices', ['catalog_id' => $catalog->id]);
        $this->assertDatabaseHas('catalog_orders', [
            'catalog_id' => $catalog->id,
            'checkout_channel' => 'invoice',
        ]);

        Queue::assertPushed(ResumeFlowFromCatalogCheckout::class);
    }

    /**
     * @return array{0: Company, 1: ListCatalog, 2: Contact, 3: Flow, 4: string}
     */
    private function catalogFlowContext(bool $autoResumeFlow = false, string $nodeId = 'catalog-1'): array
    {
        $company = Company::factory()->create();
        $company->setConfig('whatsapp_phone_number', '254712345678');

        $catalog = ListCatalog::withoutGlobalScope(CompanyScope::class)->create([
            'company_id' => $company->id,
            'name' => 'Test Catalog',
            'slug' => 'test-catalog-orders',
            'version' => 1,
            'items' => [],
            'columns' => [],
            'source' => 'manual',
        ]);

        $contact = Contact::withoutGlobalScopes()->create([
            'name' => 'Buyer',
            'phone' => '254712345671',
            'company_id' => $company->id,
            'has_chat' => true,
            'enabled_ai_bot' => true,
        ]);

        $flow = Flow::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'name' => 'Shop Flow',
            'flow_data' => json_encode([
                'nodes' => [
                    [
                        'id' => $nodeId,
                        'type' => 'whatsapp_catalog',
                        'data' => [
                            'settings' => [
                                'catalogId' => (string) $catalog->id,
                                'autoResumeFlow' => $autoResumeFlow,
                                'checkoutVariablePrefix' => 'catalog_order',
                            ],
                        ],
                    ],
                    [
                        'id' => 'quick-1',
                        'type' => 'quick_replies',
                        'data' => [
                            'settings' => [
                                'header' => 'Next',
                                'body' => 'Continue',
                                'activeButtons' => 1,
                                'button1' => 'Checkout',
                            ],
                        ],
                    ],
                ],
                'edges' => [
                    [
                        'id' => 'e1',
                        'source' => $nodeId,
                        'target' => 'quick-1',
                        'sourceHandle' => 'onProductSelected',
                    ],
                ],
            ]),
        ]);

        $flowToken = app(CatalogFlowCallbackService::class)->makeToken(
            $flow->id,
            $contact->id,
            $nodeId,
            $catalog->id
        );

        return [$company, $catalog, $contact, $flow, $flowToken];
    }
}
