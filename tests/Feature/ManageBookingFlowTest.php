<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Modules\Flowmaker\Models\Contact;
use Modules\Flowmaker\Models\Flow;
use Modules\Flowmaker\Models\Nodes\ManageBooking;
use Modules\Reminders\Models\AppointmentStaff;
use Modules\Reminders\Models\Reservation;
use Modules\Reminders\Models\Source;
use Modules\Reminders\Models\SourceStaff;
use Modules\Reminders\Services\AvailabilityService;
use Modules\Reminders\Services\ReservationBookingService;
use Modules\Reminders\Support\SlotIdentifier;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ManageBookingFlowTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private Source $source;

    private Contact $contact;

    private Flow $flow;

    private ManageBooking $node;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'owner']);
        Role::firstOrCreate(['name' => 'staff']);

        $owner = User::factory()->create();
        $owner->assignRole('owner');

        $this->company = Company::factory()->create([
            'user_id' => $owner->id,
            'subdomain' => 'manage-booking-flow',
        ]);
        $owner->update(['company_id' => $this->company->id]);
        $this->company->setConfig('plain_token', 'test-token');
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

        $this->flow = Flow::withoutGlobalScopes()->create([
            'company_id' => $this->company->id,
            'name' => 'Manage Booking Flow',
            'flow_data' => json_encode(['nodes' => [], 'edges' => []]),
        ]);

        $this->node = new ManageBooking([
            'id' => 'manage-1',
            'type' => 'manage_booking',
            'data' => [
                'settings' => [
                    'reference_variable' => 'booking_reference',
                    'default_action' => 'menu',
                    'allow_reschedule' => true,
                ],
            ],
        ], []);
        $this->node->flow_id = $this->flow->id;

        Http::fake();
    }

    public function test_manage_booking_menu_can_cancel_next_reservation(): void
    {
        $reservation = $this->createReservation(now('UTC')->addDay()->toDateString());
        $data = $this->messageData();

        $this->node->process('', $data);
        $data->extra = $this->listItemId('action', 'cancel');
        $this->node->listenForReply('', $data);

        $confirmRows = $this->lastListRows();
        $this->assertTrue(collect($confirmRows)->contains(fn (array $row) => $row['title'] === 'Yes, cancel'));

        $reservation->refresh();
        $this->assertSame(1, (int) $reservation->status);
        $this->assertNull($reservation->cancelled_at);

        $data->extra = $this->listItemId('action', 'confirm_cancel');
        $this->node->listenForReply('', $data);

        $reservation->refresh();

        $this->assertSame(2, (int) $reservation->status);
        $this->assertNotNull($reservation->cancelled_at);
        $this->assertSame('whatsapp_manage_booking', $reservation->cancellation_source);
        $this->assertSame('', $this->contact->fresh()->getContactStateValue($this->flow->id, 'current_node'));
    }

    public function test_manage_booking_abort_cancel_keeps_reservation(): void
    {
        $reservation = $this->createReservation(now('UTC')->addDay()->toDateString());
        $data = $this->messageData();

        $this->node->process('', $data);
        $data->extra = $this->listItemId('action', 'cancel');
        $this->node->listenForReply('', $data);

        $data->extra = $this->listItemId('action', 'abort_cancel');
        $this->node->listenForReply('', $data);

        $reservation->refresh();
        $this->assertSame(1, (int) $reservation->status);
        $this->assertNull($reservation->cancelled_at);
    }

    public function test_manage_booking_menu_can_reschedule_to_live_slot(): void
    {
        $reservation = $this->createReservation(now('UTC')->addDay()->toDateString());
        $originalStart = $reservation->start_date->copy();
        $newDate = now('UTC')->addDays(2)->toDateString();
        $newSlot = app(AvailabilityService::class)->slotsForDate($this->source, $newDate, 30)[0]['id'];
        $data = $this->messageData();

        $this->node->process('', $data);

        $data->extra = $this->listItemId('action', 'reschedule');
        $this->node->listenForReply('', $data);

        $data->extra = $this->listItemId('reschedule_date', $newDate);
        $this->node->listenForReply('', $data);

        $data->extra = $this->listItemId('reschedule_slot', $newSlot);
        $this->node->listenForReply('', $data);

        $reservation->refresh();
        $decodedSlot = SlotIdentifier::decode($newSlot);

        $this->assertNotEquals($originalStart->toDateTimeString(), $reservation->start_date->toDateTimeString());
        $this->assertSame($decodedSlot['start']->toDateTimeString(), $reservation->start_date->toDateTimeString());
        $this->assertSame(1, (int) $reservation->reschedule_count);
        $this->assertNotNull($reservation->rescheduled_at);
        $this->assertSame($originalStart->toDateTimeString(), $reservation->previous_start_date->toDateTimeString());
    }

    public function test_reschedule_date_lists_paginate_within_whatsapp_limit(): void
    {
        $this->createReservation(now('UTC')->addDay()->toDateString());
        $data = $this->messageData();

        $this->node->process('', $data);
        $data->extra = $this->listItemId('action', 'reschedule');
        $this->node->listenForReply('', $data);

        $firstPage = $this->lastListRows();
        $this->assertCount(10, $firstPage);
        $this->assertSame('More dates…', $firstPage[9]['title']);

        $data->extra = $this->listItemId('more', 'dates');
        $this->node->listenForReply('', $data);

        $secondPage = $this->lastListRows();
        $this->assertLessThanOrEqual(10, count($secondPage));
        $this->assertNotSame($firstPage[0]['id'], $secondPage[0]['id']);
    }

    public function test_abandoned_reschedule_pagination_resets_on_reentry(): void
    {
        $this->createReservation(now('UTC')->addDay()->toDateString());
        $data = $this->messageData();

        $this->node->process('', $data);
        $data->extra = $this->listItemId('action', 'reschedule');
        $this->node->listenForReply('', $data);

        $data->extra = $this->listItemId('more', 'dates');
        $this->node->listenForReply('', $data);

        $secondPageFirstId = $this->lastListRows()[0]['id'] ?? null;
        $this->assertNotNull($secondPageFirstId);

        // Re-enter manage booking — offsets must reset.
        $this->node->isStartNode = false;
        $this->node->process('', $this->messageData());
        $data = $this->messageData();
        $data->extra = $this->listItemId('action', 'reschedule');
        $this->node->listenForReply('', $data);

        $freshFirstId = $this->lastListRows()[0]['id'] ?? null;
        $this->assertNotNull($freshFirstId);
        $this->assertNotSame($secondPageFirstId, $freshFirstId);
    }

    public function test_multi_booking_contact_can_select_later_reservation(): void
    {
        $earlier = $this->createReservation(now('UTC')->addDay()->toDateString());
        $later = $this->createReservation(now('UTC')->addDays(3)->toDateString());
        $data = $this->messageData();

        $this->node->process('', $data);

        $pickerRows = $this->lastListRows();
        $this->assertCount(2, $pickerRows);
        $this->assertSame('manage-1', $this->contact->fresh()->getContactStateValue($this->flow->id, 'current_node'));

        $data->extra = $this->listItemId('select', (string) $later->id);
        $this->node->listenForReply('', $data);

        $this->assertSame('manage-1', $this->contact->fresh()->getContactStateValue($this->flow->id, 'current_node'));
        $menuRows = $this->lastListRows();
        $this->assertTrue(collect($menuRows)->contains(fn (array $row) => $row['title'] === 'Reschedule'));

        $data->extra = $this->listItemId('action', 'cancel');
        $this->node->listenForReply('', $data);
        $this->assertSame('manage-1', $this->contact->fresh()->getContactStateValue($this->flow->id, 'current_node'));

        $data->extra = $this->listItemId('action', 'confirm_cancel');
        $this->node->listenForReply('', $data);

        $earlier->refresh();
        $later->refresh();

        $this->assertSame(1, (int) $earlier->status);
        $this->assertSame(2, (int) $later->status);
    }

    public function test_reschedule_default_action_skips_menu_after_booking_select(): void
    {
        $this->node = new ManageBooking([
            'id' => 'manage-1',
            'type' => 'manage_booking',
            'data' => [
                'settings' => [
                    'reference_variable' => 'booking_reference',
                    'default_action' => 'reschedule',
                    'allow_reschedule' => true,
                ],
            ],
        ], []);
        $this->node->flow_id = $this->flow->id;

        $this->createReservation(now('UTC')->addDay()->toDateString());
        $later = $this->createReservation(now('UTC')->addDays(3)->toDateString());
        $data = $this->messageData();

        $this->node->process('', $data);
        $this->assertSame('Your bookings', $this->lastListHeader());

        $data->extra = $this->listItemId('select', (string) $later->id);
        $this->node->listenForReply('', $data);

        $this->assertSame('Select date', $this->lastListHeader());
        $this->assertSame('manage-1', $this->contact->fresh()->getContactStateValue($this->flow->id, 'current_node'));
        $this->assertFalse(
            collect($this->lastListRows())->contains(fn (array $row) => $row['title'] === 'Cancel booking')
        );
    }

    public function test_menu_reschedule_keeps_current_node_while_waiting_for_dates(): void
    {
        $this->createReservation(now('UTC')->addDay()->toDateString());
        $data = $this->messageData();

        $this->node->process('', $data);
        $this->assertSame('manage-1', $this->contact->fresh()->getContactStateValue($this->flow->id, 'current_node'));

        $data->extra = $this->listItemId('action', 'reschedule');
        $this->node->listenForReply('', $data);

        $this->assertSame('Select date', $this->lastListHeader());
        $this->assertSame('manage-1', $this->contact->fresh()->getContactStateValue($this->flow->id, 'current_node'));
    }

    public function test_cancel_confirmation_keeps_current_node_until_confirmed(): void
    {
        $this->createReservation(now('UTC')->addDay()->toDateString());
        $data = $this->messageData();

        $this->node->process('', $data);
        $data->extra = $this->listItemId('action', 'cancel');
        $this->node->listenForReply('', $data);

        $this->assertSame('Confirm cancellation', $this->lastListHeader());
        $this->assertSame('manage-1', $this->contact->fresh()->getContactStateValue($this->flow->id, 'current_node'));
    }

    public function test_manage_booking_returns_false_when_no_upcoming_reservation_exists(): void
    {
        $result = $this->node->process('', $this->messageData());

        $this->assertFalse($result['success']);
        $this->assertSame('', $this->contact->fresh()->getContactStateValue($this->flow->id, 'current_node'));
    }

    private function createReservation(string $date): Reservation
    {
        $slot = app(AvailabilityService::class)->slotsForDate($this->source, $date, 30)[0];

        return app(ReservationBookingService::class)->book($this->company, [
            'phone' => $this->contact->phone,
            'name' => $this->contact->name,
            'source' => $this->source->name,
            'slot_id' => $slot['id'],
            'duration_minutes' => 30,
        ]);
    }

    private function messageData(): \stdClass
    {
        $data = new \stdClass;
        $data->contact_id = $this->contact->id;
        $data->company_id = $this->company->id;
        $data->value = '';
        $data->extra = '';

        return $data;
    }

    private function listItemId(string $step, string $value): string
    {
        $encoded = rtrim(strtr(base64_encode($value), '+/', '-_'), '=');

        return 'mb-'.$step.'-'.$encoded.'_id'.$this->node->id.'_flow'.$this->flow->id;
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function lastListRows(): array
    {
        $request = collect(Http::recorded())
            ->map(fn (array $record) => $record[0])
            ->filter(fn ($request) => str_contains($request->url(), '/api/wpbox/sendlistmessage'))
            ->last();

        return $request['action']['sections'][0]['rows'] ?? [];
    }

    private function lastListHeader(): string
    {
        $request = collect(Http::recorded())
            ->map(fn (array $record) => $record[0])
            ->filter(fn ($request) => str_contains($request->url(), '/api/wpbox/sendlistmessage'))
            ->last();

        return (string) ($request['header'] ?? '');
    }
}
