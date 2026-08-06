<?php

namespace Tests\Unit;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Modules\Reminders\Models\AppointmentStaff;
use Modules\Reminders\Models\Source;
use Modules\Reminders\Models\SourceStaff;
use Modules\Reminders\Services\AvailabilityService;
use Tests\TestCase;

class AvailabilityServicePrefetchTest extends TestCase
{
    use RefreshDatabase;

    public function test_available_dates_fetches_google_freebusy_once_per_staff_for_range(): void
    {
        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'access-token',
                'expires_in' => 3600,
            ], 200),
            'https://www.googleapis.com/calendar/v3/freeBusy' => Http::response([
                'calendars' => [
                    'primary' => [
                        'busy' => [],
                    ],
                ],
            ], 200),
        ]);

        $company = Company::factory()->create();
        $staffUser = User::factory()->create(['company_id' => $company->id]);
        $staffUser->setConfig('google_calendar_refresh_token', 'refresh-token');
        $staffUser->setConfig('google_calendar_access_token', 'access-token');
        $staffUser->setConfig('google_calendar_token_expires_at', now()->addHour()->toDateTimeString());
        $staffUser->setConfig('google_calendar_id', 'primary');

        $member = AppointmentStaff::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'user_id' => $staffUser->id,
            'name' => 'Alex',
            'email' => 'alex@example.com',
            'is_active' => true,
        ]);

        $source = Source::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'name' => 'Massage',
            'is_bookable' => true,
            'default_duration_minutes' => 30,
            'duration_options' => [30],
            'buffer_minutes' => 0,
            'timezone' => 'UTC',
            'min_notice_hours' => 0,
            'max_advance_days' => 7,
            'working_hours' => collect(['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'])
                ->mapWithKeys(fn ($day) => [$day => ['enabled' => true, 'start' => '09:00', 'end' => '17:00']])
                ->all(),
        ]);

        SourceStaff::create([
            'source_id' => $source->id,
            'appointment_staff_id' => $member->id,
            'is_active' => true,
        ]);

        $from = now('UTC')->startOfDay();
        $to = $from->copy()->addDays(7);

        $dates = app(AvailabilityService::class)->availableDates($source, $from, $to, 30);

        $this->assertNotEmpty($dates);

        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => str_contains($request->url(), '/freeBusy'));
    }
}
