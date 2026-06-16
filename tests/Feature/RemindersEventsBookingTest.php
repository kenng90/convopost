<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Reminders\Models\Event;
use Modules\Reminders\Models\EventOccurrence;
use Modules\Reminders\Models\EventRegistration;
use Modules\Reminders\Services\EventCatalogService;
use Modules\Reminders\Services\EventRegistrationService;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RemindersEventsBookingTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private User $owner;

    private Event $event;

    private EventOccurrence $occurrence;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'owner']);

        $this->owner = User::factory()->create();
        $this->owner->assignRole('owner');

        $this->company = Company::factory()->create([
            'user_id' => $this->owner->id,
            'subdomain' => 'demo-events',
        ]);
        $this->owner->update(['company_id' => $this->company->id]);

        session(['company_id' => $this->company->id]);

        $this->event = Event::withoutGlobalScopes()->create([
            'company_id' => $this->company->id,
            'title' => 'Launch webinar',
            'description' => 'Product launch',
            'timezone' => 'UTC',
            'is_published' => true,
        ]);

        $this->occurrence = EventOccurrence::withoutGlobalScopes()->create([
            'company_id' => $this->company->id,
            'event_id' => $this->event->id,
            'starts_at' => now('UTC')->addDays(3),
            'ends_at' => now('UTC')->addDays(3)->addHours(2),
            'capacity' => 2,
            'status' => EventOccurrence::STATUS_PUBLISHED,
        ]);
    }

    public function test_events_api_lists_published_occurrences(): void
    {
        $token = $this->owner->createToken('events-test')->plainTextToken;

        $response = $this->getJson('/api/reminders/events?'.http_build_query([
            'token' => $token,
        ]));

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonFragment(['title' => 'Launch webinar']);
    }

    public function test_register_creates_registration_and_respects_capacity(): void
    {
        $service = app(EventRegistrationService::class);

        $first = $service->register($this->company, [
            'occurrence_id' => $this->occurrence->id,
            'phone' => '+254711111111',
            'name' => 'Alice',
            'party_size' => 2,
        ]);

        $this->assertDatabaseHas('rem_event_registrations', [
            'id' => $first->id,
            'status' => EventRegistration::STATUS_CONFIRMED,
            'party_size' => 2,
        ]);

        $this->expectException(\RuntimeException::class);
        $service->register($this->company, [
            'occurrence_id' => $this->occurrence->id,
            'phone' => '+254722222222',
            'name' => 'Bob',
            'party_size' => 1,
        ]);
    }

    public function test_cancel_frees_seats(): void
    {
        $service = app(EventRegistrationService::class);

        $registration = $service->register($this->company, [
            'occurrence_id' => $this->occurrence->id,
            'phone' => '+254711111111',
            'name' => 'Alice',
            'party_size' => 2,
        ]);

        $service->cancel($registration);

        $registration->refresh();
        $this->assertSame(EventRegistration::STATUS_CANCELLED, $registration->status);

        $this->occurrence->refresh();
        $this->assertSame(2, $this->occurrence->seatsRemaining());

        $second = $service->register($this->company, [
            'occurrence_id' => $this->occurrence->id,
            'phone' => '+254722222222',
            'name' => 'Bob',
            'party_size' => 1,
        ]);

        $this->assertNotNull($second->id);
    }

    public function test_double_register_same_contact_is_rejected(): void
    {
        $service = app(EventRegistrationService::class);

        $service->register($this->company, [
            'occurrence_id' => $this->occurrence->id,
            'phone' => '+254711111111',
            'name' => 'Alice',
        ]);

        $this->expectException(\RuntimeException::class);
        $service->register($this->company, [
            'occurrence_id' => $this->occurrence->id,
            'phone' => '+254711111111',
            'name' => 'Alice Again',
        ]);
    }

    public function test_public_events_catalog_loads(): void
    {
        $response = $this->get(route('reminders.booking.events', [
            'subdomain' => $this->company->subdomain,
        ]));

        $response->assertOk();
        $response->assertSee('Launch webinar');
    }

    public function test_public_event_register_page_loads(): void
    {
        $response = $this->get(route('reminders.booking.event', [
            'subdomain' => $this->company->subdomain,
            'occurrence' => $this->occurrence->id,
        ]));

        $response->assertOk();
        $response->assertSee('Confirm registration');
    }

    public function test_api_register_endpoint(): void
    {
        $token = $this->owner->createToken('events-test')->plainTextToken;

        $response = $this->postJson('/api/reminders/events/register', [
            'token' => $token,
            'occurrence_id' => $this->occurrence->id,
            'phone' => '+254733333333',
            'name' => 'Carol',
            'party_size' => 1,
        ]);

        $response->assertCreated()
            ->assertJsonPath('status', 'success');
    }

    public function test_events_disabled_returns_empty_catalog(): void
    {
        $this->company->setConfig('ENABLE_EVENTS_BOOKING', 'false');

        $events = app(EventCatalogService::class)->publishedEventsForCompany($this->company);

        $this->assertSame([], $events);
    }

    public function test_admin_events_index_loads(): void
    {
        $response = $this->actingAs($this->owner)->get(route('reminders.events.index'));

        $response->assertOk();
        $response->assertSee('Launch webinar');
    }

    public function test_admin_events_create_page_loads(): void
    {
        $response = $this->actingAs($this->owner)->get(route('reminders.events.create'));

        $response->assertOk();
        $response->assertSee(__('Insert'));
    }

    public function test_message_guest_from_event_registration_opens_chat(): void
    {
        $registration = app(EventRegistrationService::class)->register($this->company, [
            'occurrence_id' => $this->occurrence->id,
            'phone' => '+254744444444',
            'name' => 'Event Guest',
        ]);

        $response = $this->actingAs($this->owner)->get(
            route('reminders.event-registrations.open-chat', ['eventRegistration' => $registration->id])
        );

        $response->assertRedirect(route('chat.index', ['contact' => $registration->contact_id]));
    }
}
