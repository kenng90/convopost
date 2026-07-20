<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Modules\Reminders\Models\AppointmentStaff;
use Modules\Reminders\Models\Reservation;
use Modules\Reminders\Models\Source;
use Modules\Reminders\Models\SourceStaff;
use Modules\Reminders\Services\GoogleCalendarService;
use Modules\Reminders\Services\ReservationBookingService;
use Modules\Wpbox\Models\Contact;
use Tests\TestCase;

class GoogleCalendarAppointmentStaffTest extends TestCase
{
    use RefreshDatabase;

    private function connectGoogleCalendar(User $user, string $calendarId = 'primary'): void
    {
        $user->setConfig('google_calendar_refresh_token', 'refresh-token');
        $user->setConfig('google_calendar_access_token', 'access-token');
        $user->setConfig('google_calendar_token_expires_at', now()->addHour()->toDateTimeString());
        $user->setConfig('google_calendar_id', $calendarId);
    }

    public function test_calendar_sync_info_shows_linked_user_account(): void
    {
        $company = Company::factory()->create();
        $linkedUser = User::factory()->create(['company_id' => $company->id, 'email' => 'kenneth@example.com']);
        $this->connectGoogleCalendar($linkedUser, 'kenneth@gmail.com');

        $member = AppointmentStaff::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'user_id' => $linkedUser->id,
            'name' => 'Brenda',
            'email' => 'brenda@gmail.com',
            'is_active' => true,
        ]);

        $info = app(GoogleCalendarService::class)->calendarSyncInfoForMember($member->load('user'));

        $this->assertTrue($info['connected']);
        $this->assertSame('linked_user', $info['mode']);
        $this->assertSame('kenneth@example.com', $info['calendar_user_email']);
        $this->assertSame('kenneth@gmail.com', $info['calendar_id']);
        $this->assertSame('brenda@gmail.com', $info['attendee_email']);
    }

    public function test_create_event_for_linked_user_adds_team_member_as_attendee(): void
    {
        Http::fake([
            'https://www.googleapis.com/calendar/v3/calendars/*' => Http::response(['id' => 'google-event-123'], 200),
        ]);

        $company = Company::factory()->create();
        $linkedUser = User::factory()->create(['company_id' => $company->id, 'email' => 'kenneth@example.com']);
        $this->connectGoogleCalendar($linkedUser);

        $member = AppointmentStaff::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'user_id' => $linkedUser->id,
            'name' => 'Brenda',
            'email' => 'brenda@gmail.com',
            'is_active' => true,
        ]);

        $source = Source::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'name' => 'Consultation',
            'timezone' => 'UTC',
            'is_bookable' => true,
            'google_calendar_id' => 'spa-calendar@example.com',
        ]);

        $contact = Contact::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'name' => 'Jane Doe',
            'phone' => '+254712345678',
        ]);

        $reservation = Reservation::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'source_id' => $source->id,
            'contact_id' => $contact->id,
            'appointment_staff_id' => $member->id,
            'start_date' => now()->addDay(),
            'end_date' => now()->addDay()->addMinutes(30),
            'status' => 1,
        ]);

        $result = app(GoogleCalendarService::class)->createEventForAppointmentStaff($member, $reservation, $source);

        $this->assertSame('google-event-123', $result['event_id']);
        $this->assertSame($linkedUser->id, $result['calendar_user_id']);
        $this->assertSame('spa-calendar@example.com', $result['google_calendar_id']);

        Http::assertSent(function ($request) {
            if (! str_contains($request->url(), '/events')) {
                return false;
            }

            $body = $request->data();

            return str_contains($request->url(), 'spa-calendar%40example.com')
                && str_contains($request->url(), 'sendUpdates=all')
                && ($body['attendees'][0]['email'] ?? null) === 'brenda@gmail.com';
        });
    }

    public function test_list_calendars_returns_writable_calendars(): void
    {
        Http::fake([
            'https://www.googleapis.com/calendar/v3/users/me/calendarList*' => Http::response([
                'items' => [
                    ['id' => 'primary', 'summary' => 'Main', 'primary' => true],
                    ['id' => 'spa@example.com', 'summary' => 'Spa bookings'],
                ],
            ], 200),
        ]);

        $user = User::factory()->create();
        $this->connectGoogleCalendar($user);

        $calendars = app(GoogleCalendarService::class)->listCalendars($user);

        $this->assertCount(2, $calendars);
        $this->assertSame('primary', $calendars[0]['id']);
        $this->assertSame('spa@example.com', $calendars[1]['id']);
    }

    public function test_booking_stores_calendar_sync_error_when_google_api_fails(): void
    {
        Http::fake([
            'https://www.googleapis.com/calendar/v3/calendars/*' => Http::response([
                'error' => ['message' => 'Insufficient permissions'],
            ], 403),
        ]);

        $company = Company::factory()->create();
        $linkedUser = User::factory()->create(['company_id' => $company->id]);
        $this->connectGoogleCalendar($linkedUser);

        $member = AppointmentStaff::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'user_id' => $linkedUser->id,
            'name' => 'Brenda',
            'email' => 'brenda@gmail.com',
            'is_active' => true,
        ]);

        $source = Source::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'name' => 'Consultation',
            'timezone' => 'UTC',
            'is_bookable' => true,
            'default_duration_minutes' => 30,
            'duration_options' => [30],
            'buffer_minutes' => 0,
            'min_notice_hours' => 0,
            'max_advance_days' => 30,
            'working_hours' => collect(['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'])
                ->mapWithKeys(fn ($day) => [$day => ['enabled' => true, 'start' => '00:00', 'end' => '23:59']])
                ->all(),
        ]);

        SourceStaff::create([
            'source_id' => $source->id,
            'appointment_staff_id' => $member->id,
            'is_active' => true,
        ]);

        $contact = Contact::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'name' => 'Jane Doe',
            'phone' => '+254712345678',
        ]);

        session(['company_id' => $company->id]);

        $reservation = app(ReservationBookingService::class)->book($company, [
            'phone' => $contact->phone,
            'name' => $contact->name,
            'source' => 'Consultation',
            'appointment_staff_id' => $member->id,
            'start_date' => now('UTC')->addDay()->setTime(10, 0)->toDateTimeString(),
            'end_date' => now('UTC')->addDay()->setTime(10, 30)->toDateTimeString(),
            'duration_minutes' => 30,
        ]);

        $reservation->refresh();

        $this->assertNull($reservation->google_event_id);
        $this->assertSame('Insufficient permissions', $reservation->google_calendar_sync_error);
    }

    public function test_reschedule_patches_google_calendar_event(): void
    {
        Http::fake([
            'https://www.googleapis.com/calendar/v3/calendars/*' => Http::response(['id' => 'google-event-123'], 200),
        ]);

        [$company, $linkedUser, $member, $source] = $this->bookableSetup();

        $reservation = app(ReservationBookingService::class)->book($company, [
            'phone' => '+254712345678',
            'name' => 'Jane Doe',
            'source' => 'Consultation',
            'appointment_staff_id' => $member->id,
            'start_date' => now('UTC')->addDay()->setTime(10, 0)->toDateTimeString(),
            'end_date' => now('UTC')->addDay()->setTime(10, 30)->toDateTimeString(),
            'duration_minutes' => 30,
        ]);

        $this->assertSame('google-event-123', $reservation->fresh()->google_event_id);

        $newStart = now('UTC')->addDays(2)->setTime(11, 0);
        $updated = app(ReservationBookingService::class)->reschedule($reservation, [
            'appointment_staff_id' => $member->id,
            'start_date' => $newStart->toDateTimeString(),
            'end_date' => $newStart->copy()->addMinutes(30)->toDateTimeString(),
            'duration_minutes' => 30,
        ]);

        $this->assertSame(1, (int) $updated->reschedule_count);
        $this->assertNotNull($updated->previous_start_date);

        Http::assertSent(function ($request) {
            return $request->method() === 'PATCH'
                && str_contains($request->url(), 'google-event-123');
        });
    }

    public function test_cancel_deletes_google_calendar_event(): void
    {
        Http::fake([
            'https://www.googleapis.com/calendar/v3/calendars/*' => function ($request) {
                if ($request->method() === 'DELETE') {
                    return Http::response(null, 204);
                }

                return Http::response(['id' => 'google-event-999'], 200);
            },
        ]);

        [$company, $linkedUser, $member, $source] = $this->bookableSetup();

        $reservation = app(ReservationBookingService::class)->book($company, [
            'phone' => '+254712345678',
            'name' => 'Jane Doe',
            'source' => 'Consultation',
            'appointment_staff_id' => $member->id,
            'start_date' => now('UTC')->addDay()->setTime(10, 0)->toDateTimeString(),
            'end_date' => now('UTC')->addDay()->setTime(10, 30)->toDateTimeString(),
            'duration_minutes' => 30,
        ]);

        $this->assertSame('google-event-999', $reservation->fresh()->google_event_id);

        app(ReservationBookingService::class)->cancel($reservation->fresh(), 'admin');

        Http::assertSent(function ($request) {
            return $request->method() === 'DELETE'
                && str_contains($request->url(), 'google-event-999');
        });

        $reservation->refresh();
        $this->assertSame(2, (int) $reservation->status);
        $this->assertSame('admin', $reservation->cancellation_source);
    }

    public function test_reschedule_records_sync_error_when_patch_and_create_fail(): void
    {
        $calls = 0;
        Http::fake([
            'https://www.googleapis.com/calendar/v3/calendars/*' => function () use (&$calls) {
                $calls++;

                if ($calls === 1) {
                    return Http::response(['id' => 'google-event-123'], 200);
                }

                if ($calls === 2) {
                    return Http::response(['error' => ['message' => 'Patch failed']], 500);
                }

                return Http::response(['error' => ['message' => 'Create replacement failed']], 403);
            },
        ]);

        [$company, $linkedUser, $member, $source] = $this->bookableSetup();

        $reservation = app(ReservationBookingService::class)->book($company, [
            'phone' => '+254712345678',
            'name' => 'Jane Doe',
            'source' => 'Consultation',
            'appointment_staff_id' => $member->id,
            'start_date' => now('UTC')->addDay()->setTime(10, 0)->toDateTimeString(),
            'end_date' => now('UTC')->addDay()->setTime(10, 30)->toDateTimeString(),
            'duration_minutes' => 30,
        ]);

        $newStart = now('UTC')->addDays(2)->setTime(11, 0);
        app(ReservationBookingService::class)->reschedule($reservation, [
            'appointment_staff_id' => $member->id,
            'start_date' => $newStart->toDateTimeString(),
            'end_date' => $newStart->copy()->addMinutes(30)->toDateTimeString(),
            'duration_minutes' => 30,
        ]);

        $reservation->refresh();
        $this->assertNotNull($reservation->google_calendar_sync_error);
        $this->assertStringContainsString('Create replacement failed', (string) $reservation->google_calendar_sync_error);
    }

    /**
     * @return array{0: Company, 1: User, 2: AppointmentStaff, 3: Source}
     */
    private function bookableSetup(): array
    {
        $company = Company::factory()->create();
        $linkedUser = User::factory()->create(['company_id' => $company->id]);
        $this->connectGoogleCalendar($linkedUser);
        $company->update(['user_id' => $linkedUser->id]);

        $member = AppointmentStaff::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'user_id' => $linkedUser->id,
            'name' => 'Brenda',
            'email' => 'brenda@gmail.com',
            'is_active' => true,
        ]);

        $source = Source::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'name' => 'Consultation',
            'timezone' => 'UTC',
            'is_bookable' => true,
            'default_duration_minutes' => 30,
            'duration_options' => [30],
            'buffer_minutes' => 0,
            'min_notice_hours' => 0,
            'max_advance_days' => 30,
            'working_hours' => collect(['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'])
                ->mapWithKeys(fn ($day) => [$day => ['enabled' => true, 'start' => '00:00', 'end' => '23:59']])
                ->all(),
        ]);

        SourceStaff::create([
            'source_id' => $source->id,
            'appointment_staff_id' => $member->id,
            'is_active' => true,
        ]);

        session(['company_id' => $company->id]);

        return [$company, $linkedUser, $member, $source];
    }
}
