<?php

namespace Tests\Unit;

use App\Models\Company;
use App\Models\User;
use App\Services\OwnerNavigationBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class OwnerNavigationBookingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_appointment_menus_are_under_automations_and_setup_is_under_setup(): void
    {
        Role::firstOrCreate(['name' => 'owner']);

        $owner = User::factory()->create();
        $owner->assignRole('owner');

        $company = Company::factory()->create(['user_id' => $owner->id]);
        $owner->update(['company_id' => $company->id]);

        $sections = app(OwnerNavigationBuilder::class)->build($owner);
        $automations = collect($sections)->firstWhere('label', __('Automations & commerce'));

        $this->assertNotNull($automations);

        $appointmentsMenu = collect($automations['menus'])->firstWhere('id', 'appointmentsMenu');
        $eventsMenu = collect($automations['menus'])->firstWhere('id', 'eventsMenu');

        $this->assertNotNull($appointmentsMenu);
        $this->assertSame('Appointments', $appointmentsMenu['name']);
        $this->assertNotNull($eventsMenu);
        $this->assertSame('Events', $eventsMenu['name']);
        $this->assertNull(collect($automations['menus'])->firstWhere('id', 'bookingSetupMenu'));

        $setup = collect($sections)->firstWhere('label', __('Setup'));
        $this->assertNotNull($setup);

        $bookingSetupMenu = collect($setup['menus'])->firstWhere('id', 'bookingSetupMenu');
        $this->assertNotNull($bookingSetupMenu);
        $this->assertSame('Booking setup', $bookingSetupMenu['name']);
    }

    public function test_events_menu_is_hidden_when_feature_disabled(): void
    {
        Role::firstOrCreate(['name' => 'owner']);

        $owner = User::factory()->create();
        $owner->assignRole('owner');

        $company = Company::factory()->create(['user_id' => $owner->id]);
        $company->setConfig('ENABLE_EVENTS_BOOKING', 'false');
        $owner->update(['company_id' => $company->id]);

        $sections = app(OwnerNavigationBuilder::class)->build($owner);
        $automations = collect($sections)->firstWhere('label', __('Automations & commerce'));

        $this->assertNotNull($automations);
        $this->assertNull(collect($automations['menus'])->firstWhere('id', 'eventsMenu'));
        $this->assertNotNull(collect($automations['menus'])->firstWhere('id', 'appointmentsMenu'));
    }
}
