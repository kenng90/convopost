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

    public function test_bookings_menu_is_unified_under_automations(): void
    {
        Role::firstOrCreate(['name' => 'owner']);

        $owner = User::factory()->create();
        $owner->assignRole('owner');

        $company = Company::factory()->create(['user_id' => $owner->id]);
        $owner->update(['company_id' => $company->id]);

        $sections = app(OwnerNavigationBuilder::class)->build($owner);
        $automations = collect($sections)->firstWhere('label', __('Automations & commerce'));

        $this->assertNotNull($automations);

        $bookingsMenu = collect($automations['menus'])->firstWhere('id', 'bookingsMenu');

        $this->assertNotNull($bookingsMenu);
        $this->assertSame('Bookings', $bookingsMenu['name']);
        $this->assertSame('reminders.overview.index', $bookingsMenu['route']);
        $this->assertTrue($bookingsMenu['navSectionsCollapsible'] ?? false);

        $submenuRoutes = collect($bookingsMenu['menus'])->pluck('route')->all();

        $this->assertContains('reminders.reservations.index', $submenuRoutes);
        $this->assertContains('reminders.sources.index', $submenuRoutes);
        $this->assertContains('reminders.events.index', $submenuRoutes);
        $this->assertContains('reminders.appointment-staff.index', $submenuRoutes);
        $this->assertContains('reminders.departments.index', $submenuRoutes);
        $this->assertContains('reminders.reminders.index', $submenuRoutes);
        $this->assertContains('reminders.booking-settings.index', $submenuRoutes);

        $this->assertNull(collect($automations['menus'])->firstWhere('id', 'appointmentsMenu'));
        $this->assertNull(collect($automations['menus'])->firstWhere('id', 'eventsMenu'));
        $this->assertNull(collect($automations['menus'])->firstWhere('id', 'bookingSetupMenu'));

        $allMenuIds = collect($sections)
            ->flatMap(fn (array $section) => $section['menus'] ?? [])
            ->pluck('id')
            ->filter()
            ->all();

        $this->assertNotContains('bookingSetupMenu', $allMenuIds);
    }

    public function test_event_submenus_are_hidden_when_feature_disabled(): void
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

        $bookingsMenu = collect($automations['menus'])->firstWhere('id', 'bookingsMenu');
        $this->assertNotNull($bookingsMenu);

        $submenuRoutes = collect($bookingsMenu['menus'])->pluck('route')->all();

        $this->assertNotContains('reminders.events.index', $submenuRoutes);
        $this->assertNotContains('reminders.event-registrations.index', $submenuRoutes);
        $this->assertContains('reminders.reservations.index', $submenuRoutes);
        $this->assertContains('reminders.overview.index', $submenuRoutes);
    }
}
