<?php

namespace Tests\Unit;

use App\Models\WhatsappFlow;
use App\Services\WhatsappFlowEndpointHandlers\BookingSlotsHandler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Reminders\Models\AppointmentStaff;
use Modules\Reminders\Models\Source;
use Modules\Reminders\Models\SourceStaff;
use Tests\TestCase;

class BookingSlotsHandlerTest extends TestCase
{
    use RefreshDatabase;

    private BookingSlotsHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->handler = app(BookingSlotsHandler::class);
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
        $owner = \App\Models\User::factory()->create();
        $company = \App\Models\Company::factory()->create(['user_id' => $owner->id]);
        $staff = \App\Models\User::factory()->create(['company_id' => $company->id]);

        $source = Source::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'name' => 'Consultation',
            'is_bookable' => true,
            'default_duration_minutes' => 30,
            'duration_options' => [30],
            'buffer_minutes' => 0,
            'timezone' => 'UTC',
            'min_notice_hours' => 0,
            'max_advance_days' => 30,
            'working_hours' => collect(['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'])
                ->mapWithKeys(fn ($day) => [$day => ['enabled' => true, 'start' => '00:00', 'end' => '23:59']])
                ->all(),
        ]);

        $member = AppointmentStaff::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'user_id' => $staff->id,
            'name' => $staff->name,
            'email' => $staff->email,
            'is_active' => true,
        ]);

        SourceStaff::create([
            'source_id' => $source->id,
            'appointment_staff_id' => $member->id,
            'is_active' => true,
        ]);

        session(['company_id' => $company->id]);

        $flow = new WhatsappFlow([
            'flow_json' => [
                'screens' => [
                    [
                        'id' => 'BOOKING',
                        'endpoint_template' => 'booking_slots',
                        'booking_source' => 'Consultation',
                    ],
                ],
            ],
        ]);

        $date = now('UTC')->addDay()->format('Y-m-d');

        $response = $this->handler->handleDataExchange('BOOKING', [
            'component_action' => 'update_date',
            'date_1' => $date,
        ], $flow);

        $this->assertNotNull($response);
        $this->assertSame('BOOKING', $response['screen']);
        $this->assertNotEmpty($response['data']['available_slots']);
    }

    public function test_init_data_hides_dropdown(): void
    {
        $data = $this->handler->initData();

        $this->assertFalse($data['is_dropdown_visible']);
        $this->assertSame([], $data['available_slots']);
    }
}
