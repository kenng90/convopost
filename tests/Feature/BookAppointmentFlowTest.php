<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Modules\Flowmaker\Models\Contact;
use Modules\Flowmaker\Models\Flow;
use Modules\Flowmaker\Models\Nodes\BookAppointment;
use Modules\Reminders\Models\AppointmentStaff;
use Modules\Reminders\Models\Source;
use Modules\Reminders\Models\SourceStaff;
use Modules\Reminders\Services\AvailabilityService;
use Modules\Reminders\Services\ReservationBookingService;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BookAppointmentFlowTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private User $owner;

    private Source $source;

    private Contact $contact;

    private Flow $flow;

    private BookAppointment $node;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'owner']);
        Role::firstOrCreate(['name' => 'staff']);

        $this->owner = User::factory()->create();
        $this->owner->assignRole('owner');

        $this->company = Company::factory()->create([
            'user_id' => $this->owner->id,
            'subdomain' => 'book-flow',
        ]);
        $this->owner->update(['company_id' => $this->company->id]);
        $this->company->setConfig('plain_token', 'test-token');

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

        $this->contact = Contact::withoutGlobalScopes()->create([
            'name' => 'Jane Doe',
            'phone' => '+254712345678',
            'company_id' => $this->company->id,
            'has_chat' => true,
            'enabled_ai_bot' => true,
        ]);

        $this->flow = Flow::withoutGlobalScopes()->create([
            'company_id' => $this->company->id,
            'name' => 'Booking Flow',
            'flow_data' => json_encode(['nodes' => [], 'edges' => []]),
        ]);

        $this->node = new BookAppointment([
            'id' => 'book-1',
            'type' => 'book_appointment',
            'data' => [
                'settings' => [
                    'source_name' => 'Consultation',
                    'duration_minutes' => 30,
                    'success_message' => 'Booked!',
                ],
            ],
        ], []);
        $this->node->flow_id = $this->flow->id;
    }

    public function test_booking_services_api_lists_company_services(): void
    {
        $this->actingAs($this->owner);

        $response = $this->getJson(route('flowmaker.booking.services'));

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonFragment(['name' => 'Consultation']);
    }

    public function test_book_appointment_node_completes_free_booking_wizard(): void
    {
        Http::fake();

        $date = now('UTC')->addDay()->toDateString();
        $slots = app(AvailabilityService::class)->slotsForDate($this->source, $date, 30);
        $slotId = $slots[0]['id'];

        $messageData = $this->messageData();

        $this->node->process('', $messageData);

        Http::assertSent(fn ($request) => str_contains($request->url(), '/api/wpbox/sendlistmessage'));

        $this->node->isStartNode = true;
        $this->simulateListReply('date', $date, $messageData);
        $this->simulateListReply('slot', $slotId, $messageData);

        $this->assertDatabaseHas('rem_reservations', [
            'source_id' => $this->source->id,
            'contact_id' => $this->contact->id,
            'status' => 1,
        ]);

        $this->assertSame(
            'Consultation',
            $this->contact->getContactStateValue($this->flow->id, 'booking_service')
        );
    }

    public function test_dynamic_booking_paginates_services_without_exceeding_whatsapp_limit(): void
    {
        Http::fake();

        foreach (range(1, 10) as $index) {
            Source::withoutGlobalScopes()->create([
                'company_id' => $this->company->id,
                'name' => sprintf('Service %02d', $index),
                'is_bookable' => true,
                'default_duration_minutes' => 30,
                'duration_options' => [30],
                'timezone' => 'UTC',
                'min_notice_hours' => 0,
                'max_advance_days' => 30,
            ]);
        }

        $this->node = new BookAppointment([
            'id' => 'book-1',
            'type' => 'book_appointment',
            'data' => [
                'settings' => [
                    'source_name' => '',
                    'duration_minutes' => '',
                ],
            ],
        ], []);
        $this->node->flow_id = $this->flow->id;

        $messageData = $this->messageData();
        $this->node->process('', $messageData);

        Http::assertSent(function ($request) {
            $rows = $request['action']['sections'][0]['rows'] ?? [];

            return count($rows) === 10
                && ($rows[9]['title'] ?? '') === 'More…';
        });

        $this->node->isStartNode = true;
        $this->simulateListReply('more', 'service', $messageData);

        Http::assertSent(function ($request) {
            $rows = $request['action']['sections'][0]['rows'] ?? [];

            return count($rows) === 2
                && collect($rows)->contains(fn (array $row) => $row['title'] === 'Service 10');
        });

        $this->simulateListReply('service', 'Service 10', $messageData);

        $this->assertSame(
            'Service 10',
            $this->contact->getContactStateValue($this->flow->id, 'ba_book-1_source_name')
        );
    }

    public function test_book_appointment_list_truncates_footer_to_whatsapp_limit(): void
    {
        Http::fake();

        $longFooter = 'Paid services will request the configured deposit before confirmation.';
        $this->assertSame(70, mb_strlen($longFooter));

        $this->node = new BookAppointment([
            'id' => 'book-1',
            'type' => 'book_appointment',
            'data' => [
                'settings' => [
                    'source_name' => '',
                    'duration_minutes' => '',
                    'footer' => $longFooter,
                ],
            ],
        ], []);
        $this->node->flow_id = $this->flow->id;

        $this->node->process('', $this->messageData());

        Http::assertSent(function ($request) {
            return ($request['footer'] ?? null) === mb_substr(
                'Paid services will request the configured deposit before confirmation.',
                0,
                60
            );
        });
    }

    public function test_flow_resume_booking_payment_success_stores_variables(): void
    {
        Http::fake();

        $date = now('UTC')->addDay()->toDateString();
        $slots = app(AvailabilityService::class)->slotsForDate($this->source, $date, 30);

        $reservation = app(ReservationBookingService::class)->book($this->company, [
            'phone' => $this->contact->phone,
            'name' => $this->contact->name,
            'source' => 'Consultation',
            'slot_id' => $slots[0]['id'],
        ]);

        $this->flow->update([
            'flow_data' => json_encode([
                'nodes' => [
                    [
                        'id' => 'book-1',
                        'type' => 'book_appointment',
                        'data' => ['settings' => []],
                    ],
                ],
                'edges' => [],
            ]),
        ]);

        $this->flow->resumeBookingPaymentSuccess($this->contact, 'book-1', $reservation->id);

        $this->assertSame(
            (string) $reservation->id,
            $this->contact->getContactStateValue($this->flow->id, 'booking_reference')
        );
        $this->assertSame('', $this->contact->fresh()->getContactStateValue($this->flow->id, 'current_node'));
    }

    private function simulateListReply(string $step, string $value, \stdClass $messageData): void
    {
        $messageData->extra = $this->listItemId($step, $value);
        $this->node->isStartNode = true;
        $this->node->listenForReply('', $messageData);
    }

    private function messageData(): \stdClass
    {
        $data = new \stdClass();
        $data->contact_id = $this->contact->id;
        $data->company_id = $this->company->id;
        $data->value = '';
        $data->extra = '';

        return $data;
    }

    private function listItemId(string $step, string $value): string
    {
        $encoded = rtrim(strtr(base64_encode($value), '+/', '-_'), '=');

        return 'ba-'.$step.'-'.$encoded.'_id'.$this->node->id.'_flow'.$this->flow->id;
    }
}
