<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Reminders\Models\AppointmentStaff;
use Modules\Reminders\Models\Source;
use Modules\Reminders\Models\SourceStaff;
use Modules\Reminders\Services\AvailabilityService;
use Modules\Reminders\Services\BookingCatalogService;
use Modules\Reminders\Services\ReservationBookingService;
use Modules\Reminders\Support\SlotIdentifier;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RemindersBookingTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private User $owner;

    private AppointmentStaff $appointmentStaff;

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
            'subdomain' => 'demo-clinic',
        ]);
        $this->owner->update(['company_id' => $this->company->id]);

        session(['company_id' => $this->company->id]);

        $staffUser = User::factory()->create(['company_id' => $this->company->id]);
        $staffUser->assignRole('staff');

        $this->appointmentStaff = AppointmentStaff::withoutGlobalScopes()->create([
            'company_id' => $this->company->id,
            'user_id' => $staffUser->id,
            'name' => $staffUser->name,
            'email' => $staffUser->email,
            'whatsapp_phone' => '+254712345670',
            'is_active' => true,
        ]);

        $this->source = Source::withoutGlobalScopes()->create([
            'company_id' => $this->company->id,
            'name' => 'Consultation',
            'is_bookable' => true,
            'default_duration_minutes' => 30,
            'duration_options' => [30, 60],
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
            'appointment_staff_id' => $this->appointmentStaff->id,
            'is_active' => true,
        ]);
    }

    public function test_availability_returns_slots_for_assigned_staff(): void
    {
        $date = now('UTC')->addDay()->toDateString();

        $slots = app(AvailabilityService::class)->slotsForDate($this->source, $date, 30);

        $this->assertNotEmpty($slots);
        $this->assertSame($this->appointmentStaff->id, $slots[0]['appointment_staff_id']);
    }

    public function test_services_api_lists_bookable_sources(): void
    {
        $token = $this->owner->createToken('booking-test')->plainTextToken;

        $response = $this->getJson('/api/reminders/services?'.http_build_query([
            'token' => $token,
        ]));

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonFragment(['name' => 'Consultation']);
    }

    public function test_api_availability_endpoint_returns_slots(): void
    {
        $token = $this->owner->createToken('booking-test')->plainTextToken;
        $date = now('UTC')->addDay()->toDateString();

        $response = $this->getJson('/api/reminders/availability?'.http_build_query([
            'token' => $token,
            'source' => 'Consultation',
            'date' => $date,
            'duration_minutes' => 30,
        ]));

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonStructure(['slots', 'available_slots']);
    }

    public function test_booking_via_slot_id_creates_reservation(): void
    {
        $date = now('UTC')->addDay()->toDateString();
        $slots = app(AvailabilityService::class)->slotsForDate($this->source, $date, 30);
        $slotId = $slots[0]['id'];

        $booking = app(ReservationBookingService::class)->book($this->company, [
            'phone' => '+254712345678',
            'name' => 'Jane Doe',
            'source' => 'Consultation',
            'slot_id' => $slotId,
        ]);

        $this->assertDatabaseHas('rem_reservations', [
            'id' => $booking->id,
            'appointment_staff_id' => $this->appointmentStaff->id,
            'source_id' => $this->source->id,
            'status' => 1,
        ]);
    }

    public function test_cancel_marks_reservation_inactive(): void
    {
        $date = now('UTC')->addDay()->toDateString();
        $slots = app(AvailabilityService::class)->slotsForDate($this->source, $date, 30);

        $reservation = app(ReservationBookingService::class)->book($this->company, [
            'phone' => '+254712345678',
            'name' => 'Jane Doe',
            'source' => 'Consultation',
            'slot_id' => $slots[0]['id'],
        ]);

        app(ReservationBookingService::class)->cancel($reservation);

        $reservation->refresh();
        $this->assertSame(2, (int) $reservation->status);
        $this->assertNotNull($reservation->cancelled_at);
    }

    public function test_reschedule_moves_reservation_to_new_slot(): void
    {
        $date = now('UTC')->addDay()->toDateString();
        $slots = app(AvailabilityService::class)->slotsForDate($this->source, $date, 30);

        $reservation = app(ReservationBookingService::class)->book($this->company, [
            'phone' => '+254712345678',
            'name' => 'Jane Doe',
            'source' => 'Consultation',
            'slot_id' => $slots[0]['id'],
        ]);

        $newSlot = $slots[1]['id'];

        $updated = app(ReservationBookingService::class)->reschedule($reservation, [
            'slot_id' => $newSlot,
        ]);

        $decoded = SlotIdentifier::decode($newSlot);
        $this->assertSame(
            $decoded['start']->toDateTimeString(),
            $updated->start_date->toDateTimeString()
        );
    }

    public function test_public_booking_catalog_page_loads(): void
    {
        $response = $this->get(route('reminders.booking.catalog', [
            'subdomain' => $this->company->subdomain,
        ]));

        $response->assertOk();
        $response->assertSee('Consultation');
    }

    public function test_public_booking_widget_page_loads(): void
    {
        $response = $this->get(route('reminders.booking.widget', [
            'subdomain' => $this->company->subdomain,
            'source' => 'Consultation',
        ]));

        $response->assertOk();
        $response->assertSee('Consultation');
    }

    public function test_booking_catalog_service_returns_services(): void
    {
        $services = app(BookingCatalogService::class)->bookableServicesForCompany($this->company);

        $this->assertCount(1, $services);
        $this->assertSame('Consultation', $services[0]['name']);
    }
}
