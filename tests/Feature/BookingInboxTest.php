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
use Modules\Wpbox\Models\Contact;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BookingInboxTest extends TestCase
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
            'name' => $staffUser->name,
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

    private function bookWithPhone(string $phone): \Modules\Reminders\Models\Reservation
    {
        $date = now('UTC')->addDay()->toDateString();
        $slots = app(AvailabilityService::class)->slotsForDate($this->source, $date, 30);

        return app(ReservationBookingService::class)->book($this->company, [
            'phone' => $phone,
            'name' => 'Jane Client',
            'source' => 'Consultation',
            'slot_id' => $slots[0]['id'],
        ]);
    }

    public function test_booking_creates_contact_outside_inbox_by_default(): void
    {
        $reservation = $this->bookWithPhone('+254712345601');

        $contact = Contact::withoutGlobalScopes()->find($reservation->contact_id);

        $this->assertNotNull($contact);
        $this->assertEquals(0, (int) $contact->has_chat);
        $this->assertNull($contact->last_reply_at);
    }

    public function test_booking_creates_contact_in_inbox_when_company_setting_enabled(): void
    {
        $this->company->setConfig('BOOKING_CONTACTS_IN_INBOX', 'true');

        $reservation = $this->bookWithPhone('+254712345602');

        $contact = Contact::withoutGlobalScopes()->find($reservation->contact_id);

        $this->assertEquals(1, (int) $contact->has_chat);
        $this->assertNotNull($contact->last_reply_at);
    }

    public function test_message_customer_from_booking_promotes_contact_to_inbox(): void
    {
        $reservation = $this->bookWithPhone('+254712345603');

        $contact = Contact::withoutGlobalScopes()->find($reservation->contact_id);
        $this->assertEquals(0, (int) $contact->has_chat);

        $response = $this->actingAs($this->owner)->get(
            route('reminders.reservations.open-chat', ['reservation' => $reservation->id])
        );

        $response->assertRedirect(route('chat.index', ['contact' => $contact->id]));

        $contact->refresh();

        $this->assertEquals(1, (int) $contact->has_chat);
        $this->assertEquals(0, (int) $contact->resolved_chat);
    }
}
