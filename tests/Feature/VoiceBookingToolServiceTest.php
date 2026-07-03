<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Services\VoiceBooking\VoiceBookingSettingsService;
use App\Services\VoiceBooking\VoiceBookingToolService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Reminders\Models\Source;
use Modules\Whatsappcall\Models\Call as CallModel;
use Tests\TestCase;

class VoiceBookingToolServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_list_services_returns_empty_when_booking_disabled(): void
    {
        $company = Company::factory()->create();
        $call = CallModel::create([
            'company_id' => $company->id,
            'wa_user_id' => '254700000001',
            'status' => 'initiated',
            'direction' => 'inbound',
        ]);

        $result = app(VoiceBookingToolService::class)->invoke($call, 'list_bookable_services', []);

        $this->assertFalse($result['ok']);
    }

    public function test_list_services_when_enabled(): void
    {
        $company = Company::factory()->create();
        $company->setConfig('whatsapp_ai_booking_enabled', true);
        $company->setConfig('whatsapp_ai_booking_appointments', true);

        Source::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'name' => 'Consultation',
            'is_bookable' => true,
            'default_duration_minutes' => 30,
            'sort_order' => 0,
        ]);

        $call = CallModel::create([
            'company_id' => $company->id,
            'wa_user_id' => '254700000002',
            'status' => 'initiated',
            'direction' => 'inbound',
        ]);

        $result = app(VoiceBookingToolService::class)->invoke($call, 'list_bookable_services', []);

        $this->assertTrue($result['ok']);
        $this->assertCount(1, $result['services']);
        $this->assertSame('Consultation', $result['services'][0]['name']);
    }

    public function test_idempotent_tool_calls_return_same_result(): void
    {
        $company = Company::factory()->create();
        $company->setConfig('whatsapp_ai_booking_enabled', true);
        $company->setConfig('whatsapp_ai_booking_appointments', true);

        Source::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'name' => 'Haircut',
            'is_bookable' => true,
            'default_duration_minutes' => 45,
            'sort_order' => 0,
        ]);

        $call = CallModel::create([
            'company_id' => $company->id,
            'wa_user_id' => '254700000003',
            'status' => 'initiated',
            'direction' => 'inbound',
        ]);

        $service = app(VoiceBookingToolService::class);
        $first = $service->invoke($call, 'list_bookable_services', [], 'call-abc-1');
        $second = $service->invoke($call, 'list_bookable_services', [], 'call-abc-1');

        $this->assertTrue($first['ok']);
        $this->assertTrue($second['ok']);
        $this->assertTrue($second['idempotent_replay'] ?? false);
        $this->assertSame($first['services'][0]['name'], $second['services'][0]['name']);
    }
}
