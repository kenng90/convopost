<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\ListCatalog;
use App\Scopes\CompanyScope;
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

    public function test_generate_order_dispatches_flow_resume_when_flow_token_present(): void
    {
        Queue::fake();

        [$company, $catalog, $contact, $flow, $flowToken] = $this->catalogFlowContext();

        app(CatalogItemRepository::class)->replaceAllFromArray($catalog, [[
            'id' => 'iphone-15',
            'title' => 'Apple iPhone 15 Pro Max',
            'price' => 1,
            'quantityAvailable' => 5,
        ]]);

        $response = $this->postJson(route('catalog.generate-order', $catalog->id), [
            'items' => [['id' => 'iphone-15', 'quantity' => 1]],
            'flow_token' => $flowToken,
        ]);

        $response->assertOk();

        Queue::assertPushed(ResumeFlowFromCatalogCheckout::class, function (ResumeFlowFromCatalogCheckout $job) use ($flow, $contact) {
            return $job->flowId === $flow->id
                && $job->contactId === $contact->id
                && $job->productId === CatalogFlowCallbackService::CHECKOUT_COMPLETE_EXTRA;
        });
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

    public function test_whatsapp_checkout_resume_advances_catalog_node(): void
    {
        [$company, $catalog, $contact, $flow] = $this->catalogFlowContext();

        $contact->setContactState($flow->id, 'current_node', 'catalog-1');

        $flow->resumeFromCatalogCheckout($contact, CatalogFlowCallbackService::CHECKOUT_COMPLETE_EXTRA, [
            ['id' => 'iphone-15', 'quantity' => 1],
        ]);

        $this->assertNull($contact->getContactStateValue($flow->id, 'current_node'));
        $this->assertSame('1', $contact->getContactStateValue($flow->id, 'catalog_checkout_resumed'));
        $this->assertNotNull($contact->getContactStateValue($flow->id, 'catalog_cart'));
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

        $this->assertNull($contact->getContactStateValue($flow->id, 'current_node'));
        $this->assertSame('1', $contact->getContactStateValue($flow->id, 'catalog_checkout_resumed'));

        $contact->setContactState($flow->id, 'current_node', 'catalog-1');

        $flow->processMessage($message);

        $this->assertSame('1', $contact->getContactStateValue($flow->id, 'catalog_checkout_resumed'));
    }

    /**
     * @return array{0: Company, 1: ListCatalog, 2: Contact, 3: Flow, 4: string}
     */
    private function catalogFlowContext(): array
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

        $flow = Flow::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'name' => 'Shop Flow',
            'flow_data' => json_encode([
                'nodes' => [
                    [
                        'id' => 'catalog-1',
                        'type' => 'whatsapp_catalog',
                        'data' => [
                            'settings' => [
                                'catalogId' => $catalog->id,
                                'header' => 'Browse',
                                'displayMode' => 'link',
                            ],
                        ],
                    ],
                    [
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
                    ],
                ],
                'edges' => [
                    [
                        'id' => 'e-catalog-quick',
                        'source' => 'catalog-1',
                        'target' => 'quick-1',
                        'sourceHandle' => 'onProductSelected',
                    ],
                ],
            ]),
        ]);

        $flowToken = app(CatalogFlowCallbackService::class)->makeToken(
            $flow->id,
            $contact->id,
            'catalog-1',
            $catalog->id
        );

        return [$company, $catalog, $contact, $flow, $flowToken];
    }
}
