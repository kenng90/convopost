<?php

namespace Tests\Unit;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Flowmaker\Models\Contact;
use Modules\Flowmaker\Models\Flow;
use Modules\Reminders\Models\AppointmentStaff;
use Modules\Reminders\Models\Source;
use Modules\Reminders\Models\SourceStaff;
use Modules\Reminders\Services\AvailabilityService;
use Modules\Reminders\Services\BookingFormBridgeService;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BookingFormBridgeServiceTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private Source $source;

    private Contact $contact;

    private Flow $flow;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'owner']);
        Role::firstOrCreate(['name' => 'staff']);

        $owner = User::factory()->create();
        $owner->assignRole('owner');

        $this->company = Company::factory()->create([
            'user_id' => $owner->id,
            'subdomain' => 'bridge-unit',
        ]);
        $owner->update(['company_id' => $this->company->id]);
        session(['company_id' => $this->company->id]);

        $staffUser = User::factory()->create(['company_id' => $this->company->id]);
        $staffUser->assignRole('staff');

        $appointmentStaff = AppointmentStaff::withoutGlobalScopes()->create([
            'company_id' => $this->company->id,
            'user_id' => $staffUser->id,
            'name' => $staffUser->name,
            'email' => $staffUser->email,
            'whatsapp_phone' => '+254712345670',
            'is_active' => true,
        ]);

        $this->source = Source::withoutGlobalScopes()->create([
            'company_id' => $this->company->id,
            'name' => 'General Practice',
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

        $this->contact = Contact::withoutGlobalScopes()->create([
            'name' => 'Patient',
            'phone' => '+254700000001',
            'company_id' => $this->company->id,
            'has_chat' => true,
        ]);

        $this->flow = Flow::withoutGlobalScopes()->create([
            'company_id' => $this->company->id,
            'name' => 'Bridge Flow',
            'flow_data' => json_encode(['nodes' => [], 'edges' => []]),
        ]);
    }

    public function test_resolve_ready_when_single_slot_on_preferred_date(): void
    {
        $date = now('UTC')->addDay()->toDateString();
        $slots = app(AvailabilityService::class)->slotsForDate($this->source, $date, 30);
        $this->assertNotEmpty($slots);

        // Force a single-slot scenario by using explicit slot field instead
        $this->contact->setContactState($this->flow->id, 'form_select_3', 'general');
        $this->contact->setContactState($this->flow->id, 'form_date_4', $date);
        $this->contact->setContactState($this->flow->id, 'form_slot', $slots[0]['id']);

        $result = app(BookingFormBridgeService::class)->resolve($this->contact, $this->company, $this->flow->id, [
            'formFieldMap' => [
                'serviceField' => 'select_3',
                'dateField' => 'date_4',
                'slotField' => 'slot',
            ],
            'serviceOptionMap' => ['general' => 'General Practice'],
        ]);

        $this->assertSame('ready', $result['status']);
        $this->assertSame($slots[0]['id'], $result['slot_id']);
        $this->assertSame($this->source->id, $result['source']->id);
    }

    public function test_resolve_needs_slot_pick_when_multiple_slots(): void
    {
        $date = now('UTC')->addDay()->toDateString();
        $slots = app(AvailabilityService::class)->slotsForDate($this->source, $date, 30);
        $this->assertGreaterThan(1, count($slots));

        $this->contact->setContactState($this->flow->id, 'form_select_3', 'General Practice');
        $this->contact->setContactState($this->flow->id, 'form_date_4', $date);

        $result = app(BookingFormBridgeService::class)->resolve($this->contact, $this->company, $this->flow->id, [
            'formFieldMap' => [
                'serviceField' => 'select_3',
                'dateField' => 'date_4',
            ],
        ]);

        $this->assertSame('needs_slot_pick', $result['status']);
        $this->assertNotEmpty($result['slots']);
        $this->assertSame($date, $result['date']);
    }

    public function test_resolve_unavailable_when_no_slots_in_window(): void
    {
        $this->source->update([
            'working_hours' => collect(['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'])
                ->mapWithKeys(fn ($day) => [$day => ['enabled' => false, 'start' => '09:00', 'end' => '17:00']])
                ->all(),
        ]);

        $date = now('UTC')->addDay()->toDateString();
        $this->contact->setContactState($this->flow->id, 'form_select_3', 'General Practice');
        $this->contact->setContactState($this->flow->id, 'form_date_4', $date);

        $result = app(BookingFormBridgeService::class)->resolve($this->contact, $this->company, $this->flow->id, [
            'source_name' => 'General Practice',
            'formFieldMap' => [
                'serviceField' => 'select_3',
                'dateField' => 'date_4',
            ],
        ]);

        $this->assertSame('unavailable', $result['status']);
    }

    public function test_service_option_map_resolves_source(): void
    {
        $date = now('UTC')->addDay()->toDateString();
        $slots = app(AvailabilityService::class)->slotsForDate($this->source, $date, 30);

        $this->contact->setContactState($this->flow->id, 'form_department', 'dental');
        $this->contact->setContactState($this->flow->id, 'form_preferred_date', $date);
        $this->contact->setContactState($this->flow->id, 'form_slot', $slots[0]['id']);

        $result = app(BookingFormBridgeService::class)->resolve($this->contact, $this->company, $this->flow->id, [
            'formFieldMap' => [
                'serviceField' => 'department',
                'dateField' => 'preferred_date',
                'slotField' => 'slot',
            ],
            'serviceOptionMap' => ['dental' => 'General Practice'],
        ]);

        $this->assertSame('ready', $result['status']);
        $this->assertSame($this->source->id, $result['source']->id);
    }
}
