<?php

namespace Tests\Unit;

use App\Models\Company;
use App\Models\WhatsappFlow;
use App\Services\WhatsappFlowEndpointHandlers\BookingServicesHandler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Reminders\Models\Source;
use Tests\TestCase;

class BookingServicesHandlerTest extends TestCase
{
    use RefreshDatabase;

    public function test_init_data_returns_available_services_for_flow_company(): void
    {
        $owner = \App\Models\User::factory()->create();
        $company = Company::factory()->create(['user_id' => $owner->id]);

        Source::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'name' => 'Consultation',
            'is_bookable' => true,
            'default_duration_minutes' => 30,
        ]);

        $flow = new WhatsappFlow([
            'company_id' => $company->id,
            'flow_json' => [
                'screens' => [
                    ['id' => 'SERVICE_SELECT', 'endpoint_template' => 'booking_services'],
                ],
            ],
        ]);

        $handler = app(BookingServicesHandler::class);

        $this->assertTrue($handler->supportsScreen($flow, 'SERVICE_SELECT'));

        $data = $handler->initData($flow);

        $this->assertTrue($data['is_service_list_visible']);
        $this->assertCount(1, $data['available_services']);
        $this->assertSame('Consultation', $data['available_services'][0]['title']);
    }
}
