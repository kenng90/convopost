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
use Modules\Wpbox\Models\Contact;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BookingPhoneNormalizationTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'owner']);

        $owner = User::factory()->create();
        $owner->assignRole('owner');

        $this->company = Company::factory()->create([
            'user_id' => $owner->id,
            'subdomain' => 'phone-demo',
        ]);
        $owner->update(['company_id' => $this->company->id]);

        session(['company_id' => $this->company->id]);
    }

    public function test_appointment_booking_with_local_number_reuses_whatsapp_contact(): void
    {
        $existing = Contact::withoutGlobalScopes()->create([
            'company_id' => $this->company->id,
            'name' => 'Jane Client',
            'phone' => '254716212345',
            'has_chat' => true,
        ]);

        $staffUser = User::factory()->create(['company_id' => $this->company->id]);
        $appointmentStaff = AppointmentStaff::withoutGlobalScopes()->create([
            'company_id' => $this->company->id,
            'user_id' => $staffUser->id,
            'name' => $staffUser->name,
            'email' => $staffUser->email,
            'is_active' => true,
        ]);

        $source = Source::withoutGlobalScopes()->create([
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
            'source_id' => $source->id,
            'appointment_staff_id' => $appointmentStaff->id,
            'is_active' => true,
        ]);

        $date = now('UTC')->addDay()->toDateString();
        $slots = app(AvailabilityService::class)->slotsForDate($source, $date, 30);
        $slot = $slots[0] ?? null;
        $this->assertNotNull($slot);

        $reservation = app(ReservationBookingService::class)->book($this->company, [
            'phone' => '0716212345',
            'name' => 'Jane Client',
            'source' => $source->name,
            'slot_id' => $slot['id'],
        ]);

        $this->assertSame($existing->id, $reservation->contact_id);
        $this->assertSame('254716212345', $reservation->contact->phone);
    }

    public function test_event_registration_with_local_number_reuses_existing_contact(): void
    {
        $existing = Contact::withoutGlobalScopes()->create([
            'company_id' => $this->company->id,
            'name' => 'Event Guest',
            'phone' => '254722233344',
            'has_chat' => true,
        ]);

        $event = Event::withoutGlobalScopes()->create([
            'company_id' => $this->company->id,
            'title' => 'Workshop',
            'timezone' => 'UTC',
            'is_published' => true,
        ]);

        $occurrence = EventOccurrence::withoutGlobalScopes()->create([
            'company_id' => $this->company->id,
            'event_id' => $event->id,
            'starts_at' => now()->addDays(2),
            'ends_at' => now()->addDays(2)->addHours(2),
            'capacity' => 20,
            'status' => 'published',
        ]);

        $registration = app(EventRegistrationService::class)->register($this->company, [
            'occurrence_id' => $occurrence->id,
            'phone' => '0722233344',
            'name' => 'Event Guest',
            'party_size' => 1,
        ]);

        $this->assertSame($existing->id, $registration->contact_id);
        $this->assertSame('254722233344', $registration->contact->phone);
    }
}
