<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Reminders\Models\Event;
use Modules\Reminders\Models\EventOccurrence;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EventsOccurrenceAdminTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'owner']);

        $this->owner = User::factory()->create();
        $this->owner->assignRole('owner');

        $this->company = Company::factory()->create(['user_id' => $this->owner->id]);
        $this->owner->update(['company_id' => $this->company->id]);

        session(['company_id' => $this->company->id]);
    }

    public function test_create_event_requires_first_session_date_and_time(): void
    {
        $startsAt = now('UTC')->addDays(5)->format('Y-m-d\TH:i');
        $endsAt = now('UTC')->addDays(5)->addHours(2)->format('Y-m-d\TH:i');

        $response = $this->actingAs($this->owner)->post(route('reminders.events.store'), [
            'title' => 'Launch day',
            'timezone' => 'UTC',
            'is_published' => '1',
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'capacity' => 30,
            'status' => 'published',
        ]);

        $event = Event::withoutGlobalScopes()->first();
        $this->assertNotNull($event);
        $response->assertRedirect(route('reminders.events.edit', ['event' => $event->id]));

        $this->assertDatabaseHas('rem_event_occurrences', [
            'event_id' => $event->id,
            'capacity' => 30,
            'status' => EventOccurrence::STATUS_PUBLISHED,
        ]);
    }

    public function test_edit_event_can_add_additional_session(): void
    {
        $event = Event::withoutGlobalScopes()->create([
            'company_id' => $this->company->id,
            'title' => 'Workshop',
            'timezone' => 'UTC',
            'is_published' => true,
        ]);

        $startsAt = now('UTC')->addDays(7)->format('Y-m-d\TH:i');
        $endsAt = now('UTC')->addDays(7)->addHours(2)->format('Y-m-d\TH:i');

        $response = $this->actingAs($this->owner)->post(route('reminders.events.occurrences.store', ['event' => $event->id]), [
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'capacity' => 20,
            'status' => 'published',
        ]);

        $response->assertRedirect(route('reminders.events.edit', ['event' => $event->id]));

        $this->assertSame(1, EventOccurrence::withoutGlobalScopes()->where('event_id', $event->id)->count());
    }
}
