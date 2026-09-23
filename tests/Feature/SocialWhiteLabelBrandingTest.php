<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SocialWhiteLabelBrandingTest extends TestCase
{
    use RefreshDatabase;

    public function test_social_home_shows_company_brand_on_custom_domain(): void
    {
        Role::firstOrCreate(['name' => 'owner']);
        config(['settings.forceUserToPay' => false]);

        $owner = User::factory()->create();
        $owner->assignRole('owner');
        $company = Company::factory()->create([
            'user_id' => $owner->id,
            'active' => 1,
            'name' => 'Lakeview Studio',
        ]);
        $company->setConfig('domain', 'app.lakeview.test');
        $owner->update(['company_id' => $company->id]);

        // HomeController redirects to calendar by default; hit the view via calendar
        // and assert accounts page copy uses company brand.
        $this->actingAs($owner)
            ->withSession(['company_id' => $company->id])
            ->get(route('social.accounts.index'))
            ->assertOk()
            ->assertSee('Lakeview Studio Social')
            ->assertDontSee('Unganisha Social');
    }

    public function test_onboarding_copy_uses_white_label_platform_name(): void
    {
        Role::firstOrCreate(['name' => 'owner']);
        config([
            'settings.forceUserToPay' => false,
            'settings.site_name' => 'BrightCo',
        ]);

        $owner = User::factory()->create();
        $owner->assignRole('owner');
        $company = Company::factory()->create(['user_id' => $owner->id, 'active' => 1]);
        $owner->update(['company_id' => $company->id]);

        $this->actingAs($owner)
            ->withSession(['company_id' => $company->id])
            ->get(route('social.calendar'))
            ->assertOk()
            ->assertSee('BrightCo')
            ->assertDontSee('publish from Unganisha.');
    }
}
