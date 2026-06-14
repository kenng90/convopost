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

    public function test_bookings_menu_is_grouped_under_automations(): void
    {
        Role::firstOrCreate(['name' => 'owner']);

        $owner = User::factory()->create();
        $owner->assignRole('owner');

        $company = Company::factory()->create(['user_id' => $owner->id]);
        $owner->update(['company_id' => $company->id]);

        $sections = app(OwnerNavigationBuilder::class)->build($owner);
        $automations = collect($sections)->firstWhere('label', __('Automations & commerce'));

        $this->assertNotNull($automations);

        $bookingsMenu = collect($automations['menus'])->firstWhere('id', 'remindersMenu');

        $this->assertNotNull($bookingsMenu);
        $this->assertSame('Bookings', $bookingsMenu['name']);
    }
}
