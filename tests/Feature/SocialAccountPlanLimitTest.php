<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Plans;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Social\Models\SocialAccount;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SocialAccountPlanLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_facebook_connect_redirect_blocks_when_account_limit_reached(): void
    {
        Role::firstOrCreate(['name' => 'owner']);

        config([
            'social.providers.facebook.oauth.client_id' => 'fb-client-id',
            'social.providers.facebook.oauth.client_secret' => 'fb-client-secret',
        ]);

        $plan = Plans::create([
            'name' => 'Starter',
            'limit_items' => 0,
            'limit_orders' => 0,
            'limit_views' => 0,
            'price' => 29,
            'period' => 1,
            'description' => 'Starter',
            'features' => 'Starter',
        ]);
        $plan->setConfig('limit_social_accounts', '1');
        $plan->setConfig('plugins', json_encode(['social']));

        $owner = User::factory()->create(['plan_id' => $plan->id]);
        $owner->assignRole('owner');
        $company = Company::factory()->create(['user_id' => $owner->id, 'active' => 1]);
        $owner->update(['company_id' => $company->id]);

        SocialAccount::factory()->create([
            'company_id' => $company->id,
            'provider' => 'facebook',
        ]);

        $response = $this->actingAs($owner)
            ->withSession(['company_id' => $company->id])
            ->get(route('social.accounts.connect.facebook'));

        $response->assertRedirect(route('social.accounts.index'));
        $response->assertSessionHas('error');
    }
}
