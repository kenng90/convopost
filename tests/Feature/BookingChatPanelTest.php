<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Reminders\Models\AppointmentStaff;
use Modules\Reminders\Models\Event;
use Modules\Reminders\Models\EventOccurrence;
use Modules\Reminders\Models\Source;
use Modules\Reminders\Models\SourceStaff;
use Modules\Reminders\Services\AvailabilityService;
use Modules\Reminders\Services\EventRegistrationService;
use Modules\Reminders\Services\ReservationBookingService;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BookingChatPanelTest extends TestCase
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

    public function test_contact_bookings_api_returns_appointments_and_events_for_chat_panel(): void
    {
        $date = now('UTC')->addDay()->toDateString();
        $slots = app(AvailabilityService::class)->slotsForDate($this->source, $date, 30);

        $reservation = app(ReservationBookingService::class)->book($this->company, [
            'phone' => '+254712345678',
            'name' => 'Jane Client',
            'source' => 'Consultation',
            'slot_id' => $slots[0]['id'],
            'external_id' => 'APT-001',
        ]);

        $event = Event::withoutGlobalScopes()->create([
            'company_id' => $this->company->id,
            'title' => 'Launch webinar',
            'description' => 'Product launch',
            'timezone' => 'UTC',
            'is_published' => true,
        ]);

        $occurrence = EventOccurrence::withoutGlobalScopes()->create([
            'company_id' => $this->company->id,
            'event_id' => $event->id,
            'starts_at' => now('UTC')->addDays(3),
            'ends_at' => now('UTC')->addDays(3)->addHours(2),
            'capacity' => 50,
            'status' => EventOccurrence::STATUS_PUBLISHED,
        ]);

        $registration = app(EventRegistrationService::class)->register($this->company, [
            'occurrence_id' => $occurrence->id,
            'phone' => '+254712345678',
            'name' => 'Jane Client',
            'external_id' => 'EVT-001',
        ]);

        $response = $this->actingAs($this->owner)->postJson('/api/reminders/get-contact-bookings', [
            'contact_id' => $reservation->contact_id,
            'token' => '_',
        ]);

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('events_enabled', true)
            ->assertJsonCount(1, 'appointments')
            ->assertJsonCount(1, 'event_registrations')
            ->assertJsonPath('appointments.0.service_name', 'Consultation')
            ->assertJsonPath('appointments.0.team_member_name', 'Dr. Smith')
            ->assertJsonPath('appointments.0.status_label', __('Upcoming'))
            ->assertJsonPath('event_registrations.0.event_title', 'Launch webinar')
            ->assertJsonPath('event_registrations.0.external_id', 'EVT-001');

        $this->assertStringContainsString(
            '/reminders/reservations/'.$reservation->id,
            $response->json('appointments.0.show_url')
        );
        $this->assertStringContainsString(
            '/reminders/event-registrations/'.$registration->id,
            $response->json('event_registrations.0.show_url')
        );
        $this->assertStringNotContainsString('/edit', $response->json('appointments.0.show_url'));
    }
}
