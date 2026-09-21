<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use App\Support\Offering;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class OfferingNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_does_not_see_whatsapp_nav_when_social_commerce(): void
    {
        config(['offering.mode' => Offering::MODE_SOCIAL_COMMERCE]);

        Role::firstOrCreate(['name' => 'owner']);

        $owner = User::factory()->create();
        $owner->assignRole('owner');
        $company = Company::factory()->create(['user_id' => $owner->id]);
        $owner->update(['company_id' => $company->id]);
        session(['company_id' => $company->id]);

        $menus = collect($owner->collectOwnerModuleMenus());
        $routes = $menus->pluck('route')->filter()->values()->all();
        $names = $menus->pluck('name')->filter()->values()->all();

        $this->assertNotContains('chat.index', $routes);
        $this->assertNotContains('campaigns.index', $routes);
        $this->assertNotContains('whatsapp.setup', $routes);
        $this->assertNotContains('Chat', $names);
        $this->assertNotContains('Campaigns', $names);
        $this->assertContains('social.home', $routes);
        $this->assertContains('Social', $names);
    }

    public function test_owner_sees_whatsapp_nav_when_offering_is_full(): void
    {
        config(['offering.mode' => Offering::MODE_FULL]);

        Role::firstOrCreate(['name' => 'owner']);

        $owner = User::factory()->create();
        $owner->assignRole('owner');
        $company = Company::factory()->create(['user_id' => $owner->id]);
        $owner->update(['company_id' => $company->id]);
        session(['company_id' => $company->id]);

        $routes = collect($owner->collectOwnerModuleMenus())->pluck('route')->filter()->values()->all();

        $this->assertContains('chat.index', $routes);
        $this->assertContains('campaigns.index', $routes);
    }
}
