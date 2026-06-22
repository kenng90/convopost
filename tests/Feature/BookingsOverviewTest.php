<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BookingsOverviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_view_bookings_overview(): void
    {
        Role::firstOrCreate(['name' => 'owner']);

        $owner = User::factory()->create();
        $owner->assignRole('owner');

        $company = Company::factory()->create([
            'user_id' => $owner->id,
            'subdomain' => 'demo-bookings',
        ]);
        $owner->update(['company_id' => $company->id]);

        session(['company_id' => $company->id]);

        $response = $this->actingAs($owner)->get(route('reminders.overview.index'));

        $response->assertOk();
        $response->assertSee(__('Bookings'));
        $response->assertSee(__('One-to-one appointments'));
        $response->assertSee(__('Shared setup'));
        $response->assertSee(route('reminders.booking.catalog', ['subdomain' => 'demo-bookings']), false);
    }

    public function test_overview_hides_events_section_when_disabled(): void
    {
        Role::firstOrCreate(['name' => 'owner']);

        $owner = User::factory()->create();
        $owner->assignRole('owner');

        $company = Company::factory()->create([
            'user_id' => $owner->id,
            'subdomain' => 'no-events',
        ]);
        $company->setConfig('ENABLE_EVENTS_BOOKING', 'false');
        $owner->update(['company_id' => $company->id]);

        session(['company_id' => $company->id]);

        $response = $this->actingAs($owner)->get(route('reminders.overview.index'));

        $response->assertOk();
        $response->assertDontSee(__('Events with limited seats'));
    }
}
