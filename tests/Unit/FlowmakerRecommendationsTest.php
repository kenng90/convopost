<?php

namespace Tests\Unit;

use App\Models\Company;
use App\Services\Flowmaker\CommerceFlowAnalyticsService;
use App\Services\Flowmaker\FlowDispatchService;
use App\Services\Flowmaker\FlowHealthValidator;
use App\Services\Flowmaker\FlowRunLogger;
use App\Services\Flowmaker\FlowSimulateService;
use App\Services\Flowmaker\FlowTemplateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Modules\Flowmaker\Models\Contact;
use Modules\Flowmaker\Models\ContactState;
use Modules\Flowmaker\Models\Flow;
use Modules\Flowmaker\Models\Nodes\OrderStatus;
use Tests\TestCase;

class FlowmakerRecommendationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_dispatch_service_prefers_active_session_flow(): void
    {
        $company = Company::factory()->create();
        $companyMock = Mockery::mock(Company::class);
        $companyMock->shouldReceive('getConfig')->with('whatsapp_ai_flow_id', 0)->andReturn(0);

        $sessionFlow = new Flow([
            'id' => 5,
            'name' => 'Session',
            'priority' => 1,
            'is_active' => true,
            'exclusive_on_match' => false,
            'company_id' => $company->id,
            'flow_data' => '{}',
        ]);
        $otherFlow = new Flow([
            'id' => 6,
            'name' => 'Other',
            'priority' => 100,
            'is_active' => true,
            'exclusive_on_match' => false,
            'company_id' => $company->id,
            'flow_data' => '{}',
        ]);

        $contact = Contact::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'name' => 'Test',
            'phone' => '+254700000001',
        ]);

        ContactState::create([
            'contact_id' => $contact->id,
            'flow_id' => 5,
            'state' => 'current_node',
            'value' => 'node-1',
        ]);

        $selected = app(FlowDispatchService::class)->selectFlowsForMessage(
            $companyMock,
            collect([$sessionFlow, $otherFlow]),
            $contact,
            'hello'
        );

        $this->assertCount(1, $selected);
        $this->assertSame(5, $selected->first()->id);
    }

    public function test_dispatch_service_honors_exclusive_keyword_match(): void
    {
        $companyMock = Mockery::mock(Company::class);
        $companyMock->shouldReceive('getConfig')->with('whatsapp_ai_flow_id', 0)->andReturn(0);

        $exclusive = new Flow([
            'id' => 10,
            'name' => 'Shop',
            'priority' => 5,
            'is_active' => true,
            'exclusive_on_match' => true,
            'flow_data' => json_encode([
                'nodes' => [[
                    'id' => 'kw',
                    'type' => 'keyword_trigger',
                    'data' => ['keywords' => [['id' => 'kw1', 'value' => 'shop', 'matchType' => 'contains']]],
                ]],
                'edges' => [],
            ]),
        ]);
        $other = new Flow([
            'id' => 11,
            'name' => 'Support',
            'priority' => 50,
            'is_active' => true,
            'exclusive_on_match' => false,
            'flow_data' => json_encode([
                'nodes' => [[
                    'id' => 'kw2',
                    'type' => 'keyword_trigger',
                    'data' => ['keywords' => [['id' => 'kw1', 'value' => 'shop', 'matchType' => 'contains']]],
                ]],
                'edges' => [],
            ]),
        ]);

        $contact = new Contact;
        $contact->id = 100;

        $selected = app(FlowDispatchService::class)->selectFlowsForMessage(
            $companyMock,
            collect([$exclusive, $other]),
            $contact,
            'I want to shop now'
        );

        $this->assertCount(1, $selected);
        $this->assertSame(10, $selected->first()->id);
    }

    public function test_dispatch_skips_inactive_flows(): void
    {
        $companyMock = Mockery::mock(Company::class);
        $companyMock->shouldReceive('getConfig')->with('whatsapp_ai_flow_id', 0)->andReturn(0);

        $active = new Flow(['id' => 1, 'priority' => 1, 'is_active' => true, 'exclusive_on_match' => false, 'flow_data' => '{}']);
        $inactive = new Flow(['id' => 2, 'priority' => 99, 'is_active' => false, 'exclusive_on_match' => false, 'flow_data' => '{}']);
        $contact = new Contact;
        $contact->id = 101;

        $selected = app(FlowDispatchService::class)->selectFlowsForMessage(
            $companyMock,
            collect([$active, $inactive]),
            $contact,
            'hi'
        );

        $this->assertCount(1, $selected);
        $this->assertSame(1, $selected->first()->id);
    }

    public function test_template_bindings_rewrite_catalog_and_payment(): void
    {
        $template = config('flow-templates.whatsapp_shop_checkout.flow_data');
        $bound = app(FlowTemplateService::class)->applyBindings($template, [
            'catalog_id' => 42,
            'payment_provider' => 'mpesa',
            'group_id' => 7,
            'keywords' => ['buy', 'order'],
        ]);

        $catalog = collect($bound['nodes'])->firstWhere('type', 'whatsapp_catalog');
        $payment = collect($bound['nodes'])->firstWhere('type', 'request_payment');
        $group = collect($bound['nodes'])->firstWhere('type', 'assign_group');
        $trigger = collect($bound['nodes'])->firstWhere('type', 'keyword_trigger');

        $this->assertSame('42', $catalog['data']['settings']['catalogId']);
        $this->assertSame('mpesa', $payment['data']['settings']['payment']['provider']);
        $this->assertSame('7', $group['data']['settings']['groupId']);
        $this->assertSame('buy', $trigger['data']['keywords'][0]['value']);
    }

    public function test_apply_bindings_converts_legacy_mpesa_nodes(): void
    {
        $bound = app(FlowTemplateService::class)->applyBindings([
            'nodes' => [
                [
                    'id' => 'mpesa_stk_push-1',
                    'type' => 'mpesa_stk_push',
                    'data' => [
                        'label' => 'Deposit',
                        'type' => 'mpesa_stk_push',
                        'settings' => [
                            'mpesa' => [
                                'amount' => '100',
                                'accountReference' => 'TEST',
                                'transactionDesc' => 'Test pay',
                            ],
                        ],
                    ],
                ],
            ],
            'edges' => [
                ['id' => 'e1', 'source' => 'mpesa_stk_push-1', 'target' => 'end-1', 'sourceHandle' => 'mpesa-success'],
            ],
        ], ['payment_provider' => 'paystack']);

        $node = $bound['nodes'][0];
        $this->assertSame('request_payment', $node['type']);
        $this->assertSame('request_payment-1', $node['id']);
        $this->assertSame('paystack', $node['data']['settings']['payment']['provider']);
        $this->assertSame('request_payment-1', $bound['edges'][0]['source']);
        $this->assertSame('success', $bound['edges'][0]['sourceHandle']);
    }

    public function test_commerce_analytics_counts_funnel_events(): void
    {
        $company = Company::factory()->create();
        $flow = Flow::withoutGlobalScopes()->create([
            'name' => 'Analytics Flow',
            'company_id' => $company->id,
            'flow_data' => '{}',
        ]);

        FlowRunLogger::log($flow->id, 1, 'catalog_link_sent', 'n1');
        FlowRunLogger::log($flow->id, 1, 'payment_succeeded', 'n2');

        $summary = app(CommerceFlowAnalyticsService::class)->summaryForFlow($flow->id, 30);

        $this->assertSame(1, $summary['totals']['started']);
        $this->assertSame(1, $summary['totals']['payment_succeeded']);
        $this->assertSame(100.0, $summary['conversion_rate']);
    }

    public function test_simulate_payment_success_path(): void
    {
        $payload = [
            'nodes' => [
                [
                    'id' => 'kw',
                    'type' => 'keyword_trigger',
                    'data' => ['label' => 'Start', 'keywords' => [['id' => 'kw1', 'value' => 'shop', 'matchType' => 'contains']]],
                ],
                [
                    'id' => 'cat',
                    'type' => 'whatsapp_catalog',
                    'data' => ['label' => 'Catalog', 'settings' => ['catalogId' => '9']],
                ],
                [
                    'id' => 'pay',
                    'type' => 'request_payment',
                    'data' => ['label' => 'Pay', 'settings' => ['payment' => ['amount' => '10']]],
                ],
                [
                    'id' => 'done',
                    'type' => 'end',
                    'data' => ['label' => 'End'],
                ],
            ],
            'edges' => [
                ['id' => 'e1', 'source' => 'kw', 'target' => 'cat', 'sourceHandle' => 'kw1'],
                ['id' => 'e2', 'source' => 'cat', 'target' => 'pay', 'sourceHandle' => 'onCheckoutComplete'],
                ['id' => 'e3', 'source' => 'pay', 'target' => 'done', 'sourceHandle' => 'success'],
            ],
        ];

        $result = app(FlowSimulateService::class)->simulate($payload, 'shop please', 'payment_success');

        $this->assertTrue($result['would_start']);
        $this->assertSame(['kw', 'cat', 'pay', 'done'], collect($result['path'])->pluck('id')->all());
        $this->assertSame('success', $result['variables']['payment_result_status']);
    }

    public function test_health_blocks_placeholder_catalog_on_publish(): void
    {
        $result = (new FlowHealthValidator)->validate([
            'nodes' => [[
                'id' => 'cat-1',
                'type' => 'whatsapp_catalog',
                'data' => ['settings' => ['catalogId' => '1']],
            ]],
            'edges' => [],
        ]);

        $this->assertFalse($result['valid']);
        $this->assertNotEmpty($result['errors']);
    }

    public function test_order_status_node_instantiates(): void
    {
        $node = new OrderStatus([
            'id' => 'order-1',
            'type' => 'order_status',
            'data' => [
                'settings' => [
                    'status' => 'shipped',
                    'message' => 'Shipped {{order_reference}}',
                ],
            ],
        ], []);

        $this->assertInstanceOf(OrderStatus::class, $node);
        $this->assertSame('order-1', $node->id);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
