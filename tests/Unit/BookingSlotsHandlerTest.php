<?php

namespace Tests\Unit;

use App\Models\WhatsappFlow;
use App\Services\WhatsappFlowEndpointHandlers\BookingSlotsHandler;
use Tests\TestCase;

class BookingSlotsHandlerTest extends TestCase
{
    private BookingSlotsHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->handler = new BookingSlotsHandler;
    }

    public function test_it_detects_booking_template_screen(): void
    {
        $flow = new WhatsappFlow([
            'flow_json' => [
                'screens' => [
                    ['id' => 'BOOKING', 'endpoint_template' => 'booking_slots'],
                ],
            ],
        ]);

        $this->assertTrue($this->handler->supportsScreen($flow, 'BOOKING'));
        $this->assertFalse($this->handler->supportsScreen($flow, 'OTHER'));
    }

    public function test_date_selection_returns_slots_on_same_screen(): void
    {
        $response = $this->handler->handleDataExchange('BOOKING', [
            'component_action' => 'update_date',
            'date_1' => '2026-06-15',
        ]);

        $this->assertNotNull($response);
        $this->assertSame('BOOKING', $response['screen']);
        $this->assertTrue($response['data']['is_dropdown_visible']);
        $this->assertCount(5, $response['data']['available_slots']);
        $this->assertSame('2026-06-15_09', $response['data']['available_slots'][1]['id']);
    }

    public function test_init_data_hides_dropdown(): void
    {
        $data = $this->handler->initData();

        $this->assertFalse($data['is_dropdown_visible']);
        $this->assertSame([], $data['available_slots']);
    }
}
