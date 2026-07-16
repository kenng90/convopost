<?php

namespace Tests\Unit;

use App\Models\WhatsappFlow;
use App\Services\WhatsappFlowSendService;
use Tests\TestCase;

class WhatsappFlowSendServiceTest extends TestCase
{
    public function test_live_booking_form_opens_with_data_exchange(): void
    {
        $flow = new WhatsappFlow([
            'name' => 'Live Appointment Booking',
            'requires_endpoint' => true,
            'flow_json' => [
                'screens' => [[
                    'id' => 'PICK_SERVICE',
                    'endpoint_template' => 'booking_catalog',
                    'dynamic_data' => [
                        ['key' => 'service_options', 'type' => 'option_list'],
                    ],
                    'fields' => [
                        [
                            'type' => 'select',
                            'name' => 'service',
                            'dynamic_data_source' => true,
                            'data_source_key' => 'service_options',
                        ],
                    ],
                ]],
            ],
        ]);

        $service = app(WhatsappFlowSendService::class);

        $this->assertTrue($service->formNeedsEndpointOnOpen($flow));
        $this->assertSame('data_exchange', $service->resolveFlowAction($flow));
    }

    public function test_static_form_opens_with_navigate(): void
    {
        $flow = new WhatsappFlow([
            'name' => 'Static Lead Form',
            'requires_endpoint' => false,
            'flow_json' => [
                'screens' => [[
                    'id' => 'LEAD',
                    'fields' => [
                        ['type' => 'text', 'name' => 'full_name', 'label' => 'Name'],
                    ],
                ]],
            ],
        ]);

        $service = app(WhatsappFlowSendService::class);

        $this->assertFalse($service->formNeedsEndpointOnOpen($flow));
        $this->assertSame('navigate', $service->resolveFlowAction($flow));
    }
}
