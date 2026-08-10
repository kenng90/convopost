<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Flowmaker\Models\Contact;
use Modules\Reminders\Models\AppointmentStaff;
use Modules\Reminders\Models\Event;
use Modules\Reminders\Models\EventOccurrence;
use Modules\Reminders\Models\EventRegistration;
use Modules\Reminders\Models\Reservation;
use Modules\Reminders\Models\Source;
use Modules\Reminders\Models\SourceStaff;
use Modules\Reminders\Services\AvailabilityService;
use Modules\Reminders\Services\BookingManageTokenService;
use Modules\Reminders\Services\EventRegistrationService;
use Modules\Reminders\Services\ReservationBookingService;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BookingManagePageTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private Source $source;

    private Contact $contact;

    private BookingManageTokenService $tokens;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'owner']);
        Role::firstOrCreate(['name' => 'staff']);

        $owner = User::factory()->create();
        $owner->assignRole('owner');

        $this->company = Company::factory()->create([
            'user_id' => $owner->id,
            'subdomain' => 'manage-page-demo',
        ]);
        $owner->update(['company_id' => $this->company->id]);
        session(['company_id' => $this->company->id]);

        $staffUser = User::factory()->create(['company_id' => $this->company->id]);
        $staffUser->assignRole('staff');

        $staff = AppointmentStaff::withoutGlobalScopes()->create([
            'company_id' => $this->company->id,
            'user_id' => $staffUser->id,
            'name' => $staffUser->name,
            'email' => $staffUser->email,
            'whatsapp_phone' => '+254712345670',
            'is_active' => true,
        ]);

        $this->source = Source::withoutGlobalScopes()->create([
            'company_id' => $this->company->id,
            'name' => 'Spa Massage',
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

        SourceStaff::create([
            'source_id' => $this->source->id,
            'appointment_staff_id' => $staff->id,
            'is_active' => true,
        ]);

        $this->contact = Contact::withoutGlobalScopes()->create([
            'name' => 'Jane Doe',
            'phone' => '+254712345678',
            'company_id' => $this->company->id,
            'has_chat' => true,
            'enabled_ai_bot' => true,
        ]);

        $this->tokens = app(BookingManageTokenService::class);
    }

    public function test_manage_landing_page_loads(): void
    {
        $response = $this->get(route('reminders.booking.manage', [
            'subdomain' => $this->company->subdomain,
        ]));

        $response->assertOk()
            ->assertSee('Manage booking', false)
            ->assertSee('Find booking', false);
    }

    public function test_lookup_by_reference_and_name_redirects_to_signed_url(): void
    {
        $reservation = $this->createReservation();

        $response = $this->from(route('reminders.booking.manage', ['subdomain' => $this->company->subdomain]))
            ->postJson(route('reminders.booking.manage.lookup', ['subdomain' => $this->company->subdomain]), [
                'type' => 'appointments',
                'reference' => (string) $reservation->id,
                'verify' => 'Jane',
            ]);

        $response->assertOk()
            ->assertJsonPath('status', 'success');

        $redirect = $response->json('redirect');
        $this->assertIsString($redirect);
        $this->assertStringContainsString('/book/manage-page-demo/manage/r/', $redirect);
    }

    public function test_lookup_by_last_four_phone_digits_works(): void
    {
        $reservation = $this->createReservation();

        $response = $this->postJson(route('reminders.booking.manage.lookup', ['subdomain' => $this->company->subdomain]), [
            'type' => 'appointments',
            'reference' => '#'.$reservation->id,
            'verify' => '5678',
        ]);

        $response->assertOk()->assertJsonPath('status', 'success');
    }

    public function test_lookup_rejects_wrong_verify(): void
    {
        $reservation = $this->createReservation();

        $response = $this->postJson(route('reminders.booking.manage.lookup', ['subdomain' => $this->company->subdomain]), [
            'type' => 'appointments',
            'reference' => (string) $reservation->id,
            'verify' => 'Wrong Name',
        ]);

        $response->assertNotFound()->assertJsonPath('status', 'error');
    }

    public function test_signed_reservation_page_shows_booking(): void
    {
        $reservation = $this->createReservation();
        $url = $this->tokens->makeReservationUrl($this->company, $reservation);

        $response = $this->get($url);

        $response->assertOk()
            ->assertSee('Spa Massage', false)
            ->assertSee('Jane Doe', false);
    }

    public function test_cancel_reservation_via_signed_token(): void
    {
        $reservation = $this->createReservation();
        $url = $this->tokens->makeReservationUrl($this->company, $reservation);
        $token = basename(parse_url($url, PHP_URL_PATH));

        $response = $this->postJson(route('reminders.booking.manage.reservation.cancel', [
            'subdomain' => $this->company->subdomain,
            'token' => $token,
        ]));

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('record.status', 'cancelled');

        $reservation->refresh();
        $this->assertSame(2, (int) $reservation->status);
        $this->assertNotNull($reservation->cancelled_at);
        $this->assertSame('public_manage', $reservation->cancellation_source);
    }

    public function test_reschedule_reservation_via_signed_token(): void
    {
        $reservation = $this->createReservation(now('UTC')->addDay()->toDateString());
        $originalStart = $reservation->start_date?->copy();
        $url = $this->tokens->makeReservationUrl($this->company, $reservation);
        $token = basename(parse_url($url, PHP_URL_PATH));

        $laterDate = now('UTC')->addDays(2)->toDateString();
        $slot = app(AvailabilityService::class)->slotsForDate($this->source, $laterDate, 30)[0];

        $response = $this->postJson(route('reminders.booking.manage.reservation.reschedule', [
            'subdomain' => $this->company->subdomain,
            'token' => $token,
        ]), [
            'slot_id' => $slot['id'],
            'duration_minutes' => 30,
        ]);

        $response->assertOk()->assertJsonPath('status', 'success');

        $reservation->refresh();
        $this->assertTrue($reservation->start_date?->ne($originalStart));
        $this->assertSame(1, (int) $reservation->reschedule_count);
    }

    public function test_cancel_event_registration_via_signed_token(): void
    {
        $registration = $this->createEventRegistration();
        $url = $this->tokens->makeRegistrationUrl($this->company, $registration);
        $token = basename(parse_url($url, PHP_URL_PATH));

        $response = $this->postJson(route('reminders.booking.manage.registration.cancel', [
            'subdomain' => $this->company->subdomain,
            'token' => $token,
        ]));

        $response->assertOk()->assertJsonPath('status', 'success');

        $registration->refresh();
        $this->assertSame(EventRegistration::STATUS_CANCELLED, $registration->status);
        $this->assertNotNull($registration->cancelled_at);
    }

    public function test_expired_or_invalid_token_returns_404(): void
    {
        $this->get(route('reminders.booking.manage.reservation', [
            'subdomain' => $this->company->subdomain,
            'token' => 'not-a-valid-token',
        ]))->assertNotFound();
    }

    private function createReservation(?string $date = null): Reservation
    {
        $date = $date ?: now('UTC')->addDay()->toDateString();
        $slot = app(AvailabilityService::class)->slotsForDate($this->source, $date, 30)[0];

        return app(ReservationBookingService::class)->book($this->company, [
            'phone' => $this->contact->phone,
            'name' => $this->contact->name,
            'source' => $this->source->name,
            'slot_id' => $slot['id'],
            'duration_minutes' => 30,
        ]);
    }

    private function createEventRegistration(): EventRegistration
    {
        $event = Event::withoutGlobalScopes()->create([
            'company_id' => $this->company->id,
            'title' => 'Yoga class',
            'description' => 'Morning yoga',
            'timezone' => 'UTC',
            'is_published' => true,
        ]);

        $occurrence = EventOccurrence::withoutGlobalScopes()->create([
            'company_id' => $this->company->id,
            'event_id' => $event->id,
            'starts_at' => now('UTC')->addDays(3),
            'ends_at' => now('UTC')->addDays(3)->addHours(1),
            'capacity' => 10,
            'status' => EventOccurrence::STATUS_PUBLISHED,
        ]);

        return app(EventRegistrationService::class)->register($this->company, [
            'occurrence_id' => $occurrence->id,
            'phone' => $this->contact->phone,
            'name' => $this->contact->name,
            'party_size' => 1,
        ]);
    }
}
