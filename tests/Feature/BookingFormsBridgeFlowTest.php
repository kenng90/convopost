<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use App\Models\WhatsappFlow;
use App\Services\Flowmaker\WhatsappFormAutomationFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Modules\Flowmaker\Models\Contact;
use Modules\Flowmaker\Models\Flow;
use Modules\Flowmaker\Models\Nodes\BookAppointment;
use Modules\Flowmaker\Models\Nodes\BookingEventRegister;
use Modules\Flowmaker\Models\Nodes\ManageEventRegistration;
use Modules\Reminders\Models\AppointmentStaff;
use Modules\Reminders\Models\Event;
use Modules\Reminders\Models\EventOccurrence;
use Modules\Reminders\Models\EventRegistration;
use Modules\Reminders\Models\Source;
use Modules\Reminders\Models\SourceStaff;
use Modules\Reminders\Services\AvailabilityService;
use Modules\Reminders\Services\EventRegistrationService;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BookingFormsBridgeFlowTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private User $owner;

    private Source $source;

    private Contact $contact;

    private Flow $flow;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'owner']);
        Role::firstOrCreate(['name' => 'staff']);

        $this->owner = User::factory()->create();
        $this->owner->assignRole('owner');

        $this->company = Company::factory()->create([
            'user_id' => $this->owner->id,
            'subdomain' => 'forms-bridge',
        ]);
        $this->owner->update(['company_id' => $this->company->id]);
        $this->company->setConfig('plain_token', 'test-token');
        $this->company->setConfig('ENABLE_EVENTS_BOOKING', 'true');
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
            'name' => 'Forms Bridge Flow',
            'flow_data' => json_encode(['nodes' => [], 'edges' => []]),
        ]);
    }

    public function test_book_appointment_form_intake_books_with_explicit_slot(): void
    {
        Http::fake();

        $date = now('UTC')->addDay()->toDateString();
        $slots = app(AvailabilityService::class)->slotsForDate($this->source, $date, 30);
        $slotId = $slots[0]['id'];

        $this->contact->setContactState($this->flow->id, 'form_select_3', 'Consultation');
        $this->contact->setContactState($this->flow->id, 'form_date_4', $date);
        $this->contact->setContactState($this->flow->id, 'form_slot', $slotId);

        $node = new BookAppointment([
            'id' => 'book-form-1',
            'type' => 'book_appointment',
            'data' => [
                'settings' => [
                    'intake_mode' => 'form',
                    'formFieldMap' => [
                        'serviceField' => 'select_3',
                        'dateField' => 'date_4',
                        'slotField' => 'slot',
                    ],
                    'success_message' => 'Booked via form!',
                ],
            ],
        ], []);
        $node->flow_id = $this->flow->id;

        $data = new \stdClass();
        $data->contact_id = $this->contact->id;
        $data->company_id = $this->company->id;
        $data->value = '';
        $data->extra = '';

        $node->process('', $data);

        $this->assertDatabaseHas('rem_reservations', [
            'source_id' => $this->source->id,
            'contact_id' => $this->contact->id,
            'status' => 1,
            'booking_source' => 'whatsapp_form',
        ]);
    }

    public function test_healthcare_template_wires_form_to_book_appointment(): void
    {
        $template = config('flow-templates.healthcare_clinic_bot');
        $this->assertIsArray($template);

        $nodes = collect($template['flow_data']['nodes'] ?? []);
        $edges = collect($template['flow_data']['edges'] ?? []);

        $this->assertTrue($nodes->contains(fn ($n) => ($n['type'] ?? '') === 'book_appointment'));
        $book = $nodes->firstWhere('id', 'book_appointment-1');
        $this->assertSame('form', $book['data']['settings']['intake_mode'] ?? null);

        $this->assertTrue($edges->contains(fn ($e) => ($e['source'] ?? '') === 'whatsapp_flow-1'
            && ($e['target'] ?? '') === 'book_appointment-1'
            && ($e['sourceHandle'] ?? '') === 'onFlowCompleted'));
    }

    public function test_factory_book_recipe_sets_intake_mode_form(): void
    {
        $form = WhatsappFlow::create([
            'company_id' => $this->company->id,
            'name' => 'Live Appt Form',
            'category' => 'APPOINTMENT_BOOKING',
            'flow_json' => ['screens' => []],
            'status' => 'published',
            'meta_flow_id' => 'meta-123',
        ]);

        $flow = app(WhatsappFormAutomationFactory::class)->createFromForm($form, 'book', $this->company->id);
        $data = json_decode($flow->flow_data, true);
        $book = collect($data['nodes'])->firstWhere('id', 'book_appointment-1');

        $this->assertSame('form', $book['data']['settings']['intake_mode'] ?? null);
        $this->assertSame('select_3', $book['data']['settings']['formFieldMap']['serviceField'] ?? null);
    }

    public function test_factory_book_live_recipe_maps_live_slot_fields(): void
    {
        $form = WhatsappFlow::create([
            'company_id' => $this->company->id,
            'name' => 'Live Slot Form',
            'category' => 'APPOINTMENT_BOOKING',
            'flow_json' => ['screens' => []],
            'status' => 'published',
            'meta_flow_id' => 'meta-456',
        ]);

        $flow = app(WhatsappFormAutomationFactory::class)->createFromForm($form, 'book_live', $this->company->id);
        $data = json_decode($flow->flow_data, true);
        $book = collect($data['nodes'])->firstWhere('id', 'book_appointment-1');

        $this->assertSame('form', $book['data']['settings']['intake_mode'] ?? null);
        $this->assertSame('service', $book['data']['settings']['formFieldMap']['serviceField'] ?? null);
        $this->assertSame('slot', $book['data']['settings']['formFieldMap']['slotField'] ?? null);
    }

    public function test_appointment_booking_form_template_exists(): void
    {
        $template = config('whatsapp-form-templates.appointment_booking');
        $this->assertIsArray($template);
        $this->assertSame('booking_catalog', $template['screens'][0]['endpoint_template'] ?? null);
        $this->assertSame('booking_slots', $template['screens'][1]['endpoint_template'] ?? null);
        $this->assertSame('service', $template['screens'][0]['fields'][1]['name'] ?? null);
        $this->assertSame('slot', $template['screens'][1]['fields'][1]['name'] ?? null);
    }

    public function test_live_appointment_field_definitions_use_answer_keys(): void
    {
        $form = WhatsappFlow::create([
            'company_id' => $this->company->id,
            'name' => 'Live Appt',
            'category' => 'APPOINTMENT_BOOKING',
            'flow_json' => ['screens' => config('whatsapp-form-templates.appointment_booking.screens')],
            'status' => 'draft',
            'form_bundle_key' => 'appointment_booking',
        ]);

        $keys = collect(app(\App\Services\WhatsappFlowResponseService::class)->getInputFieldDefinitions($form))
            ->pluck('key')
            ->all();

        $this->assertContains('service', $keys);
        $this->assertContains('preferred_date', $keys);
        $this->assertContains('slot', $keys);
        $this->assertNotContains('service_options', $keys);
        $this->assertNotContains('slot_options', $keys);
    }

    public function test_booking_catalog_init_returns_bookable_services(): void
    {
        $payload = app(\Modules\Reminders\Services\BookingFlowDataExchangeService::class)
            ->initPayload($this->company, 'booking_catalog');

        $this->assertNotEmpty($payload['service_options']);
        $this->assertSame('Consultation', $payload['service_options'][0]['id']);
        $this->assertSame('Consultation', $payload['service_options'][0]['title']);
    }

    public function test_booking_catalog_exchange_advances_to_slots_when_service_and_date_present(): void
    {
        $form = WhatsappFlow::create([
            'company_id' => $this->company->id,
            'name' => 'Live Appt Exchange',
            'category' => 'APPOINTMENT_BOOKING',
            'flow_json' => ['screens' => config('whatsapp-form-templates.appointment_booking.screens')],
            'status' => 'draft',
        ]);

        $tomorrow = now('UTC')->addDay()->toDateString();
        $response = app(\Modules\Reminders\Services\BookingFlowDataExchangeService::class)->resolve(
            $form,
            'PICK_SERVICE',
            'booking_catalog',
            [
                'service' => 'Consultation',
                'preferred_date' => $tomorrow,
            ]
        );

        $this->assertSame('PICK_SLOT', $response['screen']);
        $slots = (array) $response['data']->slot_options;
        $this->assertNotEmpty($slots);
        $this->assertArrayHasKey('id', $slots[0]);
        $this->assertArrayHasKey('title', $slots[0]);
    }

    public function test_event_register_reads_form_party_size_and_occurrence(): void
    {
        $event = Event::withoutGlobalScopes()->create([
            'company_id' => $this->company->id,
            'title' => 'Workshop',
            'timezone' => 'UTC',
            'is_published' => true,
        ]);

        $occurrence = EventOccurrence::withoutGlobalScopes()->create([
            'company_id' => $this->company->id,
            'event_id' => $event->id,
            'starts_at' => now('UTC')->addDays(3)->setTime(14, 0),
            'ends_at' => now('UTC')->addDays(3)->setTime(16, 0),
            'capacity' => 20,
            'status' => EventOccurrence::STATUS_PUBLISHED,
        ]);

        $this->contact->setContactState($this->flow->id, 'form_occurrence_id', (string) $occurrence->id);
        $this->contact->setContactState($this->flow->id, 'form_party_size', '2');

        $node = new BookingEventRegister([
            'id' => 'ber-1',
            'type' => 'booking_event_register',
            'data' => [
                'settings' => [
                    'intake_mode' => 'form',
                    'formFieldMap' => [
                        'occurrenceField' => 'occurrence_id',
                        'partySizeField' => 'party_size',
                    ],
                    'success_message' => 'Registered!',
                ],
            ],
        ], []);
        $node->flow_id = $this->flow->id;

        $data = new \stdClass();
        $data->contact_id = $this->contact->id;
        $data->company_id = $this->company->id;

        $node->process('', $data);

        $this->assertDatabaseHas('rem_event_registrations', [
            'event_occurrence_id' => $occurrence->id,
            'contact_id' => $this->contact->id,
            'party_size' => 2,
            'booking_source' => 'whatsapp_form',
        ]);
    }

    public function test_manage_event_registration_cancels(): void
    {
        $event = Event::withoutGlobalScopes()->create([
            'company_id' => $this->company->id,
            'title' => 'Meetup',
            'timezone' => 'UTC',
            'is_published' => true,
        ]);

        $occurrence = EventOccurrence::withoutGlobalScopes()->create([
            'company_id' => $this->company->id,
            'event_id' => $event->id,
            'starts_at' => now('UTC')->addDays(5)->setTime(10, 0),
            'ends_at' => now('UTC')->addDays(5)->setTime(11, 0),
            'capacity' => 10,
            'status' => EventOccurrence::STATUS_PUBLISHED,
        ]);

        $registration = app(EventRegistrationService::class)->register($this->company, [
            'occurrence_id' => $occurrence->id,
            'phone' => $this->contact->phone,
            'name' => $this->contact->name,
            'party_size' => 1,
        ]);

        $this->contact->setContactState($this->flow->id, 'booking_event_reference', (string) $registration->id);

        $node = new ManageEventRegistration([
            'id' => 'mer-1',
            'type' => 'manage_event_registration',
            'data' => [
                'settings' => [
                    'default_action' => 'cancel',
                    'reference_variable' => 'booking_event_reference',
                ],
            ],
        ], []);
        $node->flow_id = $this->flow->id;

        $data = new \stdClass();
        $data->contact_id = $this->contact->id;

        $node->process('', $data);

        $this->assertSame(
            EventRegistration::STATUS_CANCELLED,
            $registration->fresh()->status
        );
    }
}
