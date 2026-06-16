<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Reminders\Models\AppointmentStaff;
use Modules\Reminders\Models\BookingClosure;
use Modules\Reminders\Models\Department;
use Modules\Reminders\Models\Reservation;
use Modules\Reminders\Models\Source;
use Modules\Reminders\Models\SourceStaff;
use Modules\Reminders\Services\AvailabilityService;
use Modules\Reminders\Services\ReservationBookingService;
use Modules\Reminders\Support\SlotIdentifier;
use Modules\Reminders\Support\WorkingHours;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RemindersBookingEnhancementsTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private User $owner;

    private Source $source;

    private AppointmentStaff $staffA;

    private AppointmentStaff $staffB;

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

        $this->staffA = $this->makeStaff('Alice Provider');
        $this->staffB = $this->makeStaff('Bob Provider');

        $this->source = $this->makeSource('Consultation');
        $this->assignStaff($this->source, $this->staffA);
        $this->assignStaff($this->source, $this->staffB);
    }

    public function test_department_working_hours_restrict_availability(): void
    {
        $department = Department::withoutGlobalScopes()->create([
            'company_id' => $this->company->id,
            'name' => 'Clinic',
            'working_hours' => WorkingHours::normalize([
                'monday' => ['enabled' => true, 'start' => '10:00', 'end' => '11:00'],
                'tuesday' => ['enabled' => true, 'start' => '10:00', 'end' => '11:00'],
                'wednesday' => ['enabled' => true, 'start' => '10:00', 'end' => '11:00'],
                'thursday' => ['enabled' => true, 'start' => '10:00', 'end' => '11:00'],
                'friday' => ['enabled' => true, 'start' => '10:00', 'end' => '11:00'],
                'saturday' => ['enabled' => true, 'start' => '10:00', 'end' => '11:00'],
                'sunday' => ['enabled' => true, 'start' => '10:00', 'end' => '11:00'],
            ]),
        ]);

        $this->source->update([
            'department_id' => $department->id,
            'working_hours' => null,
        ]);

        $this->staffA->update(['working_hours' => null]);
        $this->staffB->update(['working_hours' => null]);
        SourceStaff::query()->update(['working_hours' => null]);

        $date = now('UTC')->addDay()->toDateString();
        $slots = app(AvailabilityService::class)->slotsForDate($this->source->fresh(['department']), $date, 30);

        $this->assertNotEmpty($slots);
        foreach ($slots as $slot) {
            $this->assertMatchesRegularExpression('/^10:\d{2}/', $slot['title']);
        }
    }

    public function test_department_closure_blocks_booking_date(): void
    {
        $department = Department::withoutGlobalScopes()->create([
            'company_id' => $this->company->id,
            'name' => 'Clinic',
        ]);

        $this->source->update(['department_id' => $department->id]);

        $closedDate = now('UTC')->addDays(2)->toDateString();

        BookingClosure::withoutGlobalScopes()->create([
            'company_id' => $this->company->id,
            'department_id' => $department->id,
            'label' => 'Holiday',
            'starts_on' => $closedDate,
            'ends_on' => $closedDate,
        ]);

        $slots = app(AvailabilityService::class)->slotsForDate($this->source->fresh(['department']), $closedDate, 30);

        $this->assertSame([], $slots);
    }

    public function test_round_robin_mode_returns_anonymous_slots_and_assigns_staff_on_booking(): void
    {
        $this->source->update(['staff_assignment_mode' => Source::ASSIGNMENT_ROUND_ROBIN]);

        $date = now('UTC')->addDay()->toDateString();
        $slots = app(AvailabilityService::class)->slotsForDate($this->source->fresh(), $date, 30);

        $this->assertNotEmpty($slots);
        $this->assertTrue(SlotIdentifier::isAutoAssign($slots[0]['id']));
        $this->assertNull($slots[0]['appointment_staff_id']);
        $this->assertMatchesRegularExpression('/^\d{2}:\d{2}$/', $slots[0]['title']);

        $booking = app(ReservationBookingService::class)->book($this->company, [
            'phone' => '+254712345678',
            'name' => 'Jane Doe',
            'source' => 'Consultation',
            'slot_id' => $slots[0]['id'],
        ]);

        $this->assertContains($booking->appointment_staff_id, [$this->staffA->id, $this->staffB->id]);
    }

    public function test_least_busy_mode_prefers_staff_with_fewer_bookings(): void
    {
        $this->source->update(['staff_assignment_mode' => Source::ASSIGNMENT_LEAST_BUSY]);

        $date = now('UTC')->addDay()->toDateString();
        $slots = app(AvailabilityService::class)->slotsForDate($this->source->fresh(), $date, 30);
        $slotId = $slots[0]['id'];

        app(ReservationBookingService::class)->book($this->company, [
            'phone' => '+254712345601',
            'name' => 'Busy Client',
            'source' => 'Consultation',
            'slot_id' => $slotId,
        ]);

        $booking = app(ReservationBookingService::class)->book($this->company, [
            'phone' => '+254712345602',
            'name' => 'Second Client',
            'source' => 'Consultation',
            'slot_id' => $slots[1]['id'] ?? $slots[0]['id'],
        ]);

        $this->assertNotSame($booking->appointment_staff_id, Reservation::query()->first()->appointment_staff_id);
    }

    public function test_appointments_index_can_filter_by_status_and_team_member(): void
    {
        $upcoming = Reservation::withoutGlobalScopes()->create([
            'company_id' => $this->company->id,
            'contact_id' => $this->makeContact('+254711111111', 'Upcoming Client')->id,
            'source_id' => $this->source->id,
            'appointment_staff_id' => $this->staffA->id,
            'start_date' => now()->addDay(),
            'end_date' => now()->addDay()->addMinutes(30),
            'duration_minutes' => 30,
            'status' => 1,
        ]);

        Reservation::withoutGlobalScopes()->create([
            'company_id' => $this->company->id,
            'contact_id' => $this->makeContact('+254700000002', 'Cancelled Client')->id,
            'source_id' => $this->source->id,
            'appointment_staff_id' => $this->staffB->id,
            'start_date' => now()->subDays(2),
            'end_date' => now()->subDays(2)->addMinutes(30),
            'duration_minutes' => 30,
            'status' => 1,
            'cancelled_at' => now(),
        ]);

        $this->actingAs($this->owner)
            ->get(route('reminders.reservations.index', [
                'display_status' => 'upcoming',
                'appointment_staff_id' => $this->staffA->id,
            ]))
            ->assertOk()
            ->assertSee('Upcoming Client')
            ->assertDontSee('Cancelled Client');
    }

    public function test_appointments_export_returns_csv(): void
    {
        Reservation::withoutGlobalScopes()->create([
            'company_id' => $this->company->id,
            'contact_id' => $this->makeContact()->id,
            'source_id' => $this->source->id,
            'appointment_staff_id' => $this->staffA->id,
            'start_date' => now()->addDay(),
            'end_date' => now()->addDay()->addMinutes(30),
            'duration_minutes' => 30,
            'status' => 1,
            'external_id' => 'REF-123',
        ]);

        $response = $this->actingAs($this->owner)
            ->get(route('reminders.reservations.export'));

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=utf-8');
        $this->assertStringContainsString('REF-123', $response->streamedContent());
        $this->assertStringContainsString('Consultation', $response->streamedContent());
    }

    private function makeStaff(string $name): AppointmentStaff
    {
        $user = User::factory()->create(['company_id' => $this->company->id, 'name' => $name]);
        $user->assignRole('staff');

        return AppointmentStaff::withoutGlobalScopes()->create([
            'company_id' => $this->company->id,
            'user_id' => $user->id,
            'name' => $name,
            'email' => $user->email,
            'is_active' => true,
            'working_hours' => $this->openWorkingHours(),
        ]);
    }

    private function makeSource(string $name): Source
    {
        return Source::withoutGlobalScopes()->create([
            'company_id' => $this->company->id,
            'name' => $name,
            'is_bookable' => true,
            'default_duration_minutes' => 30,
            'duration_options' => [30],
            'buffer_minutes' => 0,
            'timezone' => 'UTC',
            'min_notice_hours' => 0,
            'max_advance_days' => 30,
            'working_hours' => $this->openWorkingHours(),
            'staff_assignment_mode' => Source::ASSIGNMENT_CUSTOMER_CHOICE,
        ]);
    }

    private function assignStaff(Source $source, AppointmentStaff $staff): void
    {
        SourceStaff::create([
            'source_id' => $source->id,
            'appointment_staff_id' => $staff->id,
            'is_active' => true,
        ]);
    }

    /**
     * @return array<string, array{enabled: bool, start: string, end: string}>
     */
    private function openWorkingHours(): array
    {
        return collect(['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'])
            ->mapWithKeys(fn ($day) => [$day => ['enabled' => true, 'start' => '00:00', 'end' => '23:59']])
            ->all();
    }

    private function makeContact(string $phone = '+254712345678', string $name = 'Test Client'): \Modules\Wpbox\Models\Contact
    {
        return \Modules\Wpbox\Models\Contact::withoutGlobalScopes()->create([
            'company_id' => $this->company->id,
            'name' => $name,
            'phone' => $phone,
        ]);
    }
}
