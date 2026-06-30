<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Modules\Flowmaker\Models\Contact;
use Modules\Flowmaker\Models\Flow;
use Modules\Flowmaker\Models\Nodes\BookingEventRegister;
use Modules\Flowmaker\Models\Nodes\BookingEventsList;
use Modules\Reminders\Models\Event;
use Modules\Reminders\Models\EventOccurrence;
use Modules\Reminders\Services\EventCatalogService;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BookingEventsFlowTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private User $owner;

    private EventOccurrence $occurrence;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'owner']);

        $this->owner = User::factory()->create();
        $this->owner->assignRole('owner');

        $this->company = Company::factory()->create([
            'user_id' => $this->owner->id,
            'subdomain' => 'event-flow',
        ]);
        $this->owner->update(['company_id' => $this->company->id]);
        $this->company->setConfig('plain_token', 'test-token');
        $this->company->setConfig('ENABLE_EVENTS_BOOKING', 'true');

        session(['company_id' => $this->company->id]);

        $event = Event::withoutGlobalScopes()->create([
            'company_id' => $this->company->id,
            'title' => 'Workshop',
            'timezone' => 'UTC',
            'is_published' => true,
        ]);

        $this->occurrence = EventOccurrence::withoutGlobalScopes()->create([
            'company_id' => $this->company->id,
            'event_id' => $event->id,
            'starts_at' => now('UTC')->addDays(3)->setTime(14, 0),
            'ends_at' => now('UTC')->addDays(3)->setTime(16, 0),
            'capacity' => 20,
            'status' => EventOccurrence::STATUS_PUBLISHED,
        ]);
    }

    public function test_booking_events_api_lists_date_and_time_labels(): void
    {
        $this->actingAs($this->owner);

        $response = $this->getJson(route('flowmaker.booking.events'));

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('events_enabled', true)
            ->assertJsonStructure([
                'occurrences' => [[
                    'id',
                    'title',
                    'date_label',
                    'time_label',
                    'description',
                ]],
            ]);

        $occurrence = $response->json('occurrences.0');
        $this->assertSame('Workshop', $occurrence['title']);
        $this->assertNotEmpty($occurrence['date_label']);
        $this->assertNotEmpty($occurrence['time_label']);
        $this->assertStringContainsString($occurrence['date_label'], $occurrence['description']);
    }

    public function test_event_catalog_service_formats_occurrence_date_and_time(): void
    {
        $events = app(EventCatalogService::class)->publishedEventsForCompany($this->company);
        $occurrence = $events[0]['occurrences'][0];

        $this->assertArrayHasKey('date_label', $occurrence);
        $this->assertArrayHasKey('time_label', $occurrence);
        $this->assertMatchesRegularExpression('/\d{1,2}:\d{2}/', $occurrence['time_label']);
    }

    public function test_register_node_completes_free_event_registration(): void
    {
        Http::fake();

        $contact = Contact::withoutGlobalScopes()->create([
            'name' => 'Jane Doe',
            'phone' => '+254712345678',
            'company_id' => $this->company->id,
            'has_chat' => true,
        ]);

        $flow = Flow::withoutGlobalScopes()->create([
            'company_id' => $this->company->id,
            'name' => 'Event Flow',
            'flow_data' => json_encode(['nodes' => [], 'edges' => []]),
        ]);

        $contact->setContactState($flow->id, 'selected_occurrence_id', (string) $this->occurrence->id);

        $node = new BookingEventRegister([
            'id' => 'register-1',
            'type' => 'booking_event_register',
            'data' => [
                'settings' => [
                    'party_size' => '1',
                    'success_message' => 'Registered!',
                ],
            ],
        ], []);
        $node->flow_id = $flow->id;

        $messageData = new \stdClass();
        $messageData->contact_id = $contact->id;
        $messageData->company_id = $this->company->id;

        $node->process('', $messageData);

        $this->assertDatabaseHas('rem_event_registrations', [
            'event_occurrence_id' => $this->occurrence->id,
            'contact_id' => $contact->id,
            'status' => 'confirmed',
        ]);

        $this->assertSame('Workshop', $contact->getContactStateValue($flow->id, 'booking_event_title'));
        $this->assertNotSame('', $contact->getContactStateValue($flow->id, 'booking_event_date'));
        $this->assertNotSame('', $contact->getContactStateValue($flow->id, 'booking_event_time'));
    }

    public function test_events_list_node_sends_whatsapp_list_with_date_time_description(): void
    {
        Http::fake();

        $contact = Contact::withoutGlobalScopes()->create([
            'name' => 'Jane Doe',
            'phone' => '+254712345678',
            'company_id' => $this->company->id,
            'has_chat' => true,
        ]);

        $flow = Flow::withoutGlobalScopes()->create([
            'company_id' => $this->company->id,
            'name' => 'Event Flow',
            'flow_data' => json_encode(['nodes' => [], 'edges' => []]),
        ]);

        $node = new BookingEventsList([
            'id' => 'events-1',
            'type' => 'booking_events_list',
            'data' => ['settings' => []],
        ], []);
        $node->flow_id = $flow->id;

        $messageData = new \stdClass();
        $messageData->contact_id = $contact->id;
        $messageData->company_id = $this->company->id;

        $node->process('', $messageData);

        Http::assertSent(function ($request) {
            if (! str_contains($request->url(), '/api/wpbox/sendlistmessage')) {
                return false;
            }

            $action = $request['action'] ?? [];
            $row = $action['sections'][0]['rows'][0] ?? null;

            return $row
                && $row['title'] === 'Workshop'
                && str_contains((string) $row['description'], '·');
        });
    }
}
