<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BookingsNavigationSectionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_bookings_menu_renders_collapsible_nav_sections(): void
    {
        Role::firstOrCreate(['name' => 'owner']);

        $owner = User::factory()->create();
        $owner->assignRole('owner');

        $company = Company::factory()->create(['user_id' => $owner->id]);
        $owner->update(['company_id' => $company->id]);

        $bookingsMenu = collect($owner->collectOwnerModuleMenus())->firstWhere('id', 'bookingsMenu');

        $this->assertNotNull($bookingsMenu);
        $this->assertTrue($bookingsMenu['navSectionsCollapsible'] ?? false);

        $html = view('admin.navbars.menus._nav-menu-item', ['menu' => $bookingsMenu])->render();

        $this->assertStringContainsString('navbar-bookingsMenu-appointments', $html);
        $this->assertStringContainsString('navbar-bookingsMenu-shared-setup', $html);
        $this->assertStringContainsString('navbar-bookingsMenu-settings', $html);
        $this->assertStringContainsString('navbar-bookingsMenu-events', $html);
        $this->assertStringContainsString(__('Overview'), $html);
        $this->assertStringContainsString(__('Calendar'), $html);
        $this->assertStringContainsString(__('Appointments'), $html);
    }

    public function test_active_bookings_section_is_expanded_in_nav(): void
    {
        Role::firstOrCreate(['name' => 'owner']);

        $owner = User::factory()->create();
        $owner->assignRole('owner');

        $company = Company::factory()->create(['user_id' => $owner->id]);
        $owner->update(['company_id' => $company->id]);

        session(['company_id' => $company->id]);

        $this->actingAs($owner)->get(route('reminders.sources.index'))->assertOk();

        $bookingsMenu = collect($owner->collectOwnerModuleMenus())->firstWhere('id', 'bookingsMenu');
        $html = view('admin.navbars.menus._nav-menu-item', ['menu' => $bookingsMenu])->render();

        $this->assertMatchesRegularExpression(
            '/class="collapse show\s*" id="navbar-bookingsMenu-appointments"/',
            preg_replace('/\s+/', ' ', $html)
        );
    }

    public function test_events_nav_section_is_omitted_when_events_disabled(): void
    {
        Role::firstOrCreate(['name' => 'owner']);

        $owner = User::factory()->create();
        $owner->assignRole('owner');

        $company = Company::factory()->create(['user_id' => $owner->id]);
        $company->setConfig('ENABLE_EVENTS_BOOKING', 'false');
        $owner->update(['company_id' => $company->id]);

        $bookingsMenu = collect($owner->collectOwnerModuleMenus())->firstWhere('id', 'bookingsMenu');
        $html = view('admin.navbars.menus._nav-menu-item', ['menu' => $bookingsMenu])->render();

        $this->assertStringNotContainsString('navbar-bookingsMenu-events', $html);
        $this->assertStringContainsString('navbar-bookingsMenu-appointments', $html);
    }

    public function test_non_collapsible_group_menus_are_not_duplicated(): void
    {
        Role::firstOrCreate(['name' => 'owner']);

        $owner = User::factory()->create();
        $owner->assignRole('owner');

        $company = Company::factory()->create(['user_id' => $owner->id]);
        $owner->update(['company_id' => $company->id]);

        $contactsMenu = collect($owner->collectOwnerModuleMenus())->firstWhere('id', 'contactMenu');

        $this->assertNotNull($contactsMenu);
        $this->assertFalse($contactsMenu['navSectionsCollapsible'] ?? false);

        $html = view('admin.navbars.menus._nav-menu-item', ['menu' => $contactsMenu])->render();

        $this->assertSame(1, substr_count($html, __('Contact list')));
        $this->assertSame(1, substr_count($html, __('Fields')));
        $this->assertSame(1, substr_count($html, __('Groups')));
        $this->assertSame(1, substr_count($html, __('Import')));
    }
}
