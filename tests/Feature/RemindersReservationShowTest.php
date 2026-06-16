<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Reminders\Models\AppointmentStaff;
use Modules\Reminders\Models\Source;
use Modules\Reminders\Models\SourceStaff;
use Modules\Reminders\Services\AvailabilityService;
use Modules\Reminders\Services\ReservationBookingService;
use Modules\Wpbox\Models\Message;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RemindersReservationShowTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private User $owner;

    private Source $source;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'owner']);
        $this->owner = User::factory()->create();
        $this->owner->assignRole('owner');

        $this->company = Company::factory()->create([
            'user_id' => $this->owner->id,
            'subdomain' => 'demo-clinic',
        ]);
        $this->owner->update(['company_id' => $this->company->id]);
        session(['company_id' => $this->company->id]);

        $staffUser = User::factory()->create(['company_id' => $this->company->id]);

        $appointmentStaff = AppointmentStaff::withoutGlobalScopes()->create([
            'company_id' => $this->company->id,
            'user_id' => $staffUser->id,
            'name' => 'Dr. Smith',
            'email' => $staffUser->email,
            'is_active' => true,
        ]);

        $this->source = Source::withoutGlobalScopes()->create([
            'company_id' => $this->company->id,
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

        SourceStaff::create([
            'source_id' => $this->source->id,
            'appointment_staff_id' => $appointmentStaff->id,
            'is_active' => true,
        ]);
    }

    private function bookReservation(): \Modules\Reminders\Models\Reservation
    {
        $date = now('UTC')->addDay()->toDateString();
        $slots = app(AvailabilityService::class)->slotsForDate($this->source, $date, 30);

        return app(ReservationBookingService::class)->book($this->company, [
            'phone' => '+254712345678',
            'name' => 'Jane Client',
            'source' => 'Consultation',
            'slot_id' => $slots[0]['id'],
            'external_id' => 'BOOK-001',
        ]);
    }

    public function test_reservation_show_page_displays_booking_details(): void
    {
        $reservation = $this->bookReservation();
        $reservation->update([
            'notes' => 'First visit',
            'google_event_id' => 'evt_123',
            'duration_minutes' => 30,
        ]);

        Message::create([
            'company_id' => $this->company->id,
            'contact_id' => $reservation->contact_id,
            'value' => 'Reminder for your appointment',
            'buttons' => '',
            'components' => '',
            'status' => 0,
            'extra' => (string) $reservation->id,
        ]);

        $response = $this->actingAs($this->owner)->get(route('reminders.reservations.show', ['reservation' => $reservation->id]));

        $response->assertOk();
        $response->assertSee('Jane Client');
        $response->assertSee('+254712345678');
        $response->assertSee('Dr. Smith');
        $response->assertSee('Consultation');
        $response->assertSee('BOOK-001');
        $response->assertSee('First visit');
        $response->assertSee('Reminder for your appointment');
        $response->assertSee(__('Upcoming'));
    }

    public function test_reservation_index_lists_status_and_team_member(): void
    {
        $reservation = $this->bookReservation();
        $reservation->update(['duration_minutes' => 45]);

        $response = $this->actingAs($this->owner)->get(route('reminders.reservations.index'));

        $response->assertOk();
        $response->assertSee('Dr. Smith');
        $response->assertSee('45 min');
        $response->assertSee('+254712345678');
        $response->assertSee(__('Upcoming'));
    }

    public function test_reservation_create_page_loads(): void
    {
        $response = $this->actingAs($this->owner)->get(route('reminders.reservations.create'));

        $response->assertOk();
        $response->assertSee(__('Insert'));
    }

    public function test_message_customer_from_appointment_opens_chat(): void
    {
        $reservation = $this->bookReservation();

        $response = $this->actingAs($this->owner)->get(
            route('reminders.reservations.open-chat', ['reservation' => $reservation->id])
        );

        $response->assertRedirect(route('chat.index', ['contact' => $reservation->contact_id]));
    }

    public function test_message_customer_works_when_contact_is_soft_deleted(): void
    {
        $reservation = $this->bookReservation();
        $reservation->contact->delete();

        $response = $this->actingAs($this->owner)->get(
            route('reminders.reservations.open-chat', ['reservation' => $reservation->id])
        );

        $response->assertRedirect(route('chat.index', ['contact' => $reservation->contact_id]));
    }
}
