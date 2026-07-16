<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Modules\Reminders\Models\AppointmentStaff;
use Modules\Reminders\Models\Source;
use Modules\Reminders\Models\SourceStaff;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RemindersCalendarTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private User $owner;

    private User $staffUser;

    private AppointmentStaff $staff;

    private Source $source;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'owner']);
        Role::firstOrCreate(['name' => 'staff']);
        $this->owner = User::factory()->create();
        $this->owner->assignRole('owner');
        $this->company = Company::factory()->create([
            'user_id' => $this->owner->id,
            'subdomain' => 'calendar-company',
        ]);
        $this->owner->update(['company_id' => $this->company->id]);
        session(['company_id' => $this->company->id]);

        $this->staffUser = User::factory()->create(['company_id' => $this->company->id]);
        $this->staffUser->assignRole('staff');
        $this->staff = AppointmentStaff::withoutGlobalScopes()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->staffUser->id,
            'name' => 'Amina',
            'email' => 'amina@example.com',
            'is_active' => true,
        ]);
        $this->source = Source::withoutGlobalScopes()->create([
            'company_id' => $this->company->id,
            'name' => 'Consultation',
            'is_bookable' => true,
            'default_duration_minutes' => 30,
            'duration_options' => [30],
            'buffer_minutes' => 0,
            'timezone' => 'Africa/Nairobi',
            'min_notice_hours' => 0,
            'max_advance_days' => 30,
            'google_calendar_id' => '8b843f72bc901fd071c6f181ec069b16af5170c0c2023abd75bf78c14b48efbb@group.calendar.google.com',
            'working_hours' => collect(['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'])
                ->mapWithKeys(fn ($day) => [$day => ['enabled' => true, 'start' => '00:00', 'end' => '23:59']])
                ->all(),
        ]);
        SourceStaff::withoutGlobalScopes()->create([
            'source_id' => $this->source->id,
            'appointment_staff_id' => $this->staff->id,
            'is_active' => true,
        ]);
    }

    private function connectGoogleCalendar(User $user, string $calendarId = 'primary'): void
    {
        $user->setConfig('google_calendar_refresh_token', 'refresh-token');
        $user->setConfig('google_calendar_access_token', 'access-token');
        $user->setConfig('google_calendar_token_expires_at', now()->addHour()->toDateTimeString());
        $user->setConfig('google_calendar_id', $calendarId);
    }

    public function test_calendar_page_prompts_to_connect_google_when_disconnected(): void
    {
        $this->actingAs($this->owner)
            ->withSession(['company_id' => $this->company->id])
            ->get(route('reminders.calendar.index'))
            ->assertOk()
            ->assertSee(__('Connect Google Calendar'))
            ->assertDontSee('google-calendar-embed', false);
    }

    public function test_calendar_page_renders_google_iframe_and_calendar_dropdown(): void
    {
        $this->connectGoogleCalendar($this->owner, 'owner@example.com');

        Http::fake([
            'https://www.googleapis.com/calendar/v3/users/me/calendarList*' => Http::response([
                'items' => [
                    ['id' => 'owner@example.com', 'summary' => 'Main', 'primary' => true],
                    ['id' => 'team@group.calendar.google.com', 'summary' => 'Team bookings'],
                ],
            ], 200),
        ]);

        $response = $this->actingAs($this->owner)
            ->withSession(['company_id' => $this->company->id])
            ->get(route('reminders.calendar.index'));

        $response->assertOk()
            ->assertSee('id="google-calendar-picker"', false)
            ->assertSee('id="google-calendar-embed"', false)
            ->assertSee('calendar.google.com/calendar/embed', false)
            ->assertSee('owner@example.com', false)
            ->assertSee('Team bookings')
            ->assertSee('Consultation ('.__('Service').')')
            ->assertSee('Africa/Nairobi');
    }

    public function test_staff_without_team_link_cannot_open_calendar(): void
    {
        $orphanStaff = User::factory()->create(['company_id' => $this->company->id]);
        $orphanStaff->assignRole('staff');

        $this->actingAs($orphanStaff)
            ->withSession(['company_id' => $this->company->id])
            ->get(route('reminders.calendar.index'))
            ->assertForbidden();
    }
}
