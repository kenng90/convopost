<?php

namespace Tests\Feature;

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

class CatalogWhatsAppCheckoutFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'owner']);
    }

    public function test_generate_order_stores_pending_checkout_without_resuming_flow(): void
    {
        Queue::fake();

        [$company, $catalog, $contact, $flow, $flowToken] = $this->catalogFlowContext();

        app(CatalogItemRepository::class)->replaceAllFromArray($catalog, [[
            'id' => 'iphone-15',
            'title' => 'Apple iPhone 15 Pro Max',
            'price' => 1,
            'quantityAvailable' => 5,
        ]]);

        $contact->setContactState($flow->id, 'current_node', 'catalog-1');

        $response = $this->postJson(route('catalog.generate-order', $catalog->id), [
            'items' => [['id' => 'iphone-15', 'quantity' => 1]],
            'flow_token' => $flowToken,
        ]);

        $response->assertOk();

        Queue::assertNotPushed(ResumeFlowFromCatalogCheckout::class);

        $contact->refresh();

        $this->assertSame('1', $contact->getContactStateValue($flow->id, CatalogCheckoutPendingService::PENDING_FLAG));
        $this->assertSame('catalog-1', $contact->getContactStateValue($flow->id, 'current_node'));
        $this->assertNotSame('1', $contact->getContactStateValue($flow->id, 'catalog_checkout_resumed'));
        $this->assertStringContainsString('Apple iPhone 15 Pro Max', $contact->getContactStateValue($flow->id, 'catalog_order_items'));
    }

    public function test_whatsapp_checkout_advances_flow_only_after_inbound_order_message(): void
    {
        [$company, $catalog, $contact, $flow, $flowToken] = $this->catalogFlowContext();

        app(CatalogItemRepository::class)->replaceAllFromArray($catalog, [[
            'id' => 'iphone-15',
            'title' => 'Apple iPhone 15 Pro Max',
            'price' => 1,
            'quantityAvailable' => 5,
        ]]);

        $catalog->refresh();
        $contact->setContactState($flow->id, 'current_node', 'catalog-1');

        $this->postJson(route('catalog.generate-order', $catalog->id), [
            'items' => [['id' => 'iphone-15', 'quantity' => 1]],
            'flow_token' => $flowToken,
        ])->assertOk();

        $contact->refresh();

        $this->assertSame('catalog-1', $contact->getContactStateValue($flow->id, 'current_node'));
        $this->assertNotSame('1', $contact->getContactStateValue($flow->id, 'catalog_checkout_resumed'));

        $orderText = "📦 *New Order from Catalog: {$catalog->name}*\n\n📋 *Items:*\n• Apple iPhone 15 Pro Max (x1) - KSh 1.00\n\n💰 *Total:* KSh 1.00";

        $message = new \stdClass();
        $message->contact_id = $contact->id;
        $message->company_id = $company->id;
        $message->value = $orderText;
        $message->extra = '';

        $flow->processMessage($message);

        $this->assertSame('quick-1', $contact->getContactStateValue($flow->id, 'current_node'));
        $this->assertSame('1', $contact->getContactStateValue($flow->id, 'catalog_checkout_resumed'));
        $this->assertNotSame('1', $contact->getContactStateValue($flow->id, CatalogCheckoutPendingService::PENDING_FLAG));
    }

    public function test_create_invoice_does_not_dispatch_flow_resume(): void
    {
        Queue::fake();

        [$company, $catalog, $contact, $flow, $flowToken] = $this->catalogFlowContext();

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
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('invoices', ['catalog_id' => $catalog->id]);

        Queue::assertNotPushed(ResumeFlowFromCatalogCheckout::class);
    }

    public function test_inbound_order_message_advances_catalog_node_once(): void
    {
        [$company, $catalog, $contact, $flow] = $this->catalogFlowContext();

        $contact->setContactState($flow->id, 'current_node', 'catalog-1');

        $orderText = "📦 *New Order from Catalog: {$catalog->name}*\n\n📋 *Items:*\n• Apple iPhone 15 Pro Max (x1) - KSh 1.00\n\n💰 *Total:* KSh 1.00";

        $message = new \stdClass();
        $message->contact_id = $contact->id;
        $message->company_id = $company->id;
        $message->value = $orderText;
        $message->extra = '';

        $flow->processMessage($message);

        $this->assertSame('quick-1', $contact->getContactStateValue($flow->id, 'current_node'));
        $this->assertSame('1', $contact->getContactStateValue($flow->id, 'catalog_checkout_resumed'));

        $contact->setContactState($flow->id, 'current_node', 'catalog-1');

        $flow->processMessage($message);

        $this->assertSame('1', $contact->getContactStateValue($flow->id, 'catalog_checkout_resumed'));
    }

    public function test_catalog_order_message_does_not_restart_flow_when_keyword_contains_catalog(): void
    {
        [$company, $catalog, $contact, $flow] = $this->catalogFlowContext(withKeywordTrigger: true);

        app(CatalogItemRepository::class)->replaceAllFromArray($catalog, [[
            'id' => 'iphone-15',
            'title' => 'Apple iPhone 15 Pro Max',
            'price' => 1,
            'quantityAvailable' => 5,
        ]]);

        $contact->setContactState($flow->id, 'current_node', 'catalog-1');

        $orderText = "📦 *New Order from Catalog: {$catalog->name}*\n\n📋 *Items:*\n• Apple iPhone 15 Pro Max (x1) - KSh 1.00\n\n💰 *Total:* KSh 1.00";

        $message = new \stdClass();
        $message->contact_id = $contact->id;
        $message->company_id = $company->id;
        $message->value = $orderText;
        $message->extra = '';

        $flow->processMessage($message);

        $this->assertSame('quick-1', $contact->getContactStateValue($flow->id, 'current_node'));
        $this->assertSame('1', $contact->getContactStateValue($flow->id, 'catalog_checkout_resumed'));
    }

    public function test_interactive_list_product_selection_resolves_product_with_underscore_node_id(): void
    {
        [$company, $catalog, $contact, $flow] = $this->catalogFlowContext(nodeId: 'whatsapp_catalog-1');

        app(CatalogItemRepository::class)->replaceAllFromArray($catalog, [[
            'id' => 'LIST_001',
            'title' => 'Conference Room Hire',
            'price' => 1,
            'quantityAvailable' => 5,
        ]]);

        $contact->setContactState($flow->id, 'current_node', 'whatsapp_catalog-1');

        $message = new \stdClass();
        $message->contact_id = $contact->id;
        $message->company_id = $company->id;
        $message->value = 'Conference Room Hire';
        $message->extra = 'catalog_LIST_001_idwhatsapp_catalog-1_flow'.$flow->id;

        $flow->processMessage($message);

        $selectedProduct = json_decode($contact->getContactStateValue($flow->id, 'selected_product'), true);
        $this->assertSame('LIST_001', $selectedProduct['id'] ?? null);
    }

    /**
     * @return array{0: Company, 1: ListCatalog, 2: Contact, 3: Flow, 4: string}
     */
    private function catalogFlowContext(bool $withKeywordTrigger = false, string $nodeId = 'catalog-1'): array
    {
        $company = Company::factory()->create();
        $company->setConfig('whatsapp_phone_number', '254712345678');

        $catalog = ListCatalog::withoutGlobalScope(CompanyScope::class)->create([
            'company_id' => $company->id,
            'name' => 'Test Catalog',
            'slug' => 'test-catalog',
            'version' => 1,
            'items' => [],
            'columns' => [],
            'source' => 'manual',
        ]);

        $contact = Contact::withoutGlobalScopes()->create([
            'name' => 'Buyer',
            'phone' => '254712345670',
            'company_id' => $company->id,
            'has_chat' => true,
            'enabled_ai_bot' => true,
        ]);

        $nodes = [];
        $edges = [];

        if ($withKeywordTrigger) {
            $nodes[] = [
                'id' => 'keyword-1',
                'type' => 'keyword_trigger',
                'position' => ['x' => 0, 'y' => 0],
                'data' => [
                    'keywords' => [
                        ['id' => 'kw3', 'value' => 'catalog', 'matchType' => 'contains'],
                    ],
                ],
            ];
            $nodes[] = [
                'id' => 'welcome-1',
                'type' => 'message',
                'data' => [
                    'settings' => [
                        'message' => 'Welcome to our shop!',
                    ],
                ],
            ];
            $edges[] = [
                'id' => 'e-kw-welcome',
                'source' => 'keyword-1',
                'target' => 'welcome-1',
                'sourceHandle' => 'keyword-kw3',
            ];
            $edges[] = [
                'id' => 'e-welcome-catalog',
                'source' => 'welcome-1',
                'target' => $nodeId,
            ];
        }

        $nodes[] = [
            'id' => $nodeId,
            'type' => 'whatsapp_catalog',
            'data' => [
                'settings' => [
                    'catalogId' => $catalog->id,
                    'header' => 'Browse',
                    'displayMode' => 'link',
                ],
            ],
        ];
        $nodes[] = [
            'id' => 'quick-1',
            'type' => 'quick_replies',
            'data' => [
                'settings' => [
                    'header' => 'Next',
                    'body' => 'Choose',
                    'activeButtons' => 2,
                    'button1' => 'Inquire',
                    'button2' => 'Sales',
                ],
            ],
        ];
        $edges[] = [
            'id' => 'e-catalog-quick',
            'source' => $nodeId,
            'target' => 'quick-1',
            'sourceHandle' => 'onProductSelected',
        ];

        $flow = Flow::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'name' => 'Shop Flow',
            'flow_data' => json_encode([
                'nodes' => $nodes,
                'edges' => $edges,
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
