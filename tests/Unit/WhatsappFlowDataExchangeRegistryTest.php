<?php

namespace Tests\Unit;

use App\Models\Company;
use App\Models\WhatsappFlow;
use App\Models\WhatsappFlowResponse;
use App\Services\WhatsappFlows\WhatsappFlowDataExchangeRegistry;
use App\Services\WhatsappFlows\WhatsappFlowDataExchangeResolver;
use App\Services\WhatsappFlows\WhatsappFlowScreenInitService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Wpbox\Http\Controllers\FlowsWebhookController;
use ReflectionMethod;
use Tests\TestCase;

class WhatsappFlowDataExchangeRegistryTest extends TestCase
{
    use RefreshDatabase;

    public function test_registry_lists_booking_and_dynamic_handlers(): void
    {
        $registry = app(WhatsappFlowDataExchangeRegistry::class);

        $this->assertContains('dynamic_options', $registry->keys());
        $this->assertContains('booking_catalog', $registry->keys());
        $this->assertContains('booking_slots', $registry->keys());
        $this->assertContains('booking_occurrences', $registry->keys());
    }

    public function test_sequential_router_advances_multi_screen_form_without_endpoint_template(): void
    {
        $flow = new WhatsappFlow([
            'flow_json' => [
                'screens' => [
                    ['id' => 'COVER', 'title' => 'Cover'],
                    ['id' => 'DETAILS', 'title' => 'Details'],
                    ['id' => 'CONTACT', 'title' => 'Contact'],
                ],
            ],
        ]);

        $resolver = app(WhatsappFlowDataExchangeResolver::class);

        $first = $resolver->resolve($flow, 'COVER', ['cover_type' => 'comprehensive'], 'flow_1_1');
        $this->assertSame('DETAILS', $first['screen']);

        $second = $resolver->resolve($flow, 'DETAILS', ['vehicle_year' => '2020'], 'flow_1_1');
        $this->assertSame('CONTACT', $second['screen']);

        $final = $resolver->resolve($flow, 'CONTACT', ['phone' => '+254700000000'], 'flow_1_1');
        $this->assertSame('SUCCESS', $final['screen']);
        $this->assertSame('+254700000000', $final['data']['extension_message_response']['params']['phone'] ?? null);
        $this->assertSame('flow_1_1', $final['data']['extension_message_response']['params']['flow_token'] ?? null);
    }

    public function test_dynamic_options_handler_stays_on_same_screen(): void
    {
        $flow = new WhatsappFlow([
            'flow_json' => [
                'screens' => [[
                    'id' => 'OPTIONS',
                    'endpoint_template' => 'dynamic_options',
                    'dynamic_data' => [[
                        'key' => 'departments',
                        'type' => 'option_list',
                        'example_items' => [['id' => '1', 'title' => 'General']],
                    ]],
                ]],
            ],
        ]);

        $resolver = app(WhatsappFlowDataExchangeResolver::class);
        $response = $resolver->resolve($flow, 'OPTIONS', ['department' => '1'], null);

        $this->assertSame('OPTIONS', $response['screen']);
        $this->assertArrayHasKey('departments', (array) $response['data']);
    }

    public function test_init_data_for_screen_uses_registry_handlers(): void
    {
        $company = Company::factory()->create();
        $flow = new WhatsappFlow([
            'company_id' => $company->id,
            'flow_json' => [
                'screens' => [[
                    'id' => 'SCREEN_A',
                    'dynamic_data' => [[
                        'key' => 'departments',
                        'type' => 'option_list',
                        'example_items' => [['id' => '1', 'title' => 'General']],
                    ]],
                ]],
            ],
        ]);

        $data = app(WhatsappFlowScreenInitService::class)->initDataForScreen($flow, 'SCREEN_A');

        $this->assertArrayHasKey('departments', $data);
        $this->assertIsArray($data['departments']);
    }

    public function test_finalize_data_exchange_merges_partial_answers_and_completes_only_on_success(): void
    {
        $owner = \App\Models\User::factory()->create();
        $company = Company::factory()->create(['user_id' => $owner->id]);
        $contact = \Modules\Wpbox\Models\Contact::withoutGlobalScopes()->create([
            'name' => 'Jane Doe',
            'phone' => '+254700000000',
            'company_id' => $company->id,
            'has_chat' => true,
        ]);

        $flow = WhatsappFlow::create([
            'company_id' => $company->id,
            'name' => 'Insurance Quote',
            'flow_json' => ['screens' => []],
            'status' => 'draft',
        ]);

        $flowResponse = WhatsappFlowResponse::create([
            'company_id' => $company->id,
            'whatsapp_flow_id' => $flow->id,
            'contact_id' => $contact->id,
            'flow_token' => 'flow_'.$company->id.'_'.time(),
            'status' => 'pending',
            'sent_at' => now(),
        ]);

        $controller = app(FlowsWebhookController::class);
        $method = new ReflectionMethod($controller, 'finalizeDataExchangeResponse');
        $method->setAccessible(true);

        $mid = $method->invoke($controller, [
            'action' => 'data_exchange',
            'flow_token' => 'flow_'.$flowResponse->id.'_123',
            'data' => ['cover_type' => 'comprehensive'],
        ], [
            'screen' => 'DETAILS',
            'data' => (object) [],
        ], $company);

        $flowResponse->refresh();
        $this->assertSame('pending', $flowResponse->status);
        $this->assertSame('comprehensive', $flowResponse->responses['cover_type'] ?? null);
        $this->assertSame('DETAILS', $mid['screen']);

        \Illuminate\Support\Facades\Event::fake([
            \Modules\Wpbox\Events\ContactReplies::class,
        ]);

        $done = $method->invoke($controller, [
            'action' => 'data_exchange',
            'flow_token' => 'flow_'.$flowResponse->id.'_123',
            'data' => ['phone' => '+254700000000'],
        ], [
            'screen' => 'SUCCESS',
            'data' => [
                'extension_message_response' => [
                    'params' => ['flow_token' => 'flow_'.$flowResponse->id.'_123'],
                ],
            ],
        ], $company);

        $flowResponse->refresh();
        $this->assertSame('completed', $flowResponse->status);
        $this->assertSame('comprehensive', $flowResponse->responses['cover_type'] ?? null);
        $this->assertSame('+254700000000', $flowResponse->responses['phone'] ?? null);
        $this->assertSame('comprehensive', $done['data']['extension_message_response']['params']['cover_type'] ?? null);
        $this->assertSame('+254700000000', $done['data']['extension_message_response']['params']['phone'] ?? null);

        \Illuminate\Support\Facades\Event::assertDispatched(\Modules\Wpbox\Events\ContactReplies::class);
    }
}
