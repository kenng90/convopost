<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Plans;
use App\Models\User;
use App\Support\Offering;
use Database\Seeders\PlanEntitlementsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class OfferingWhatsappUiBlockTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'owner']);
        $this->seed(PlanEntitlementsSeeder::class);

        config([
            'offering.mode' => Offering::MODE_SOCIAL_COMMERCE,
            'settings.forceUserToPay' => false,
        ]);
    }

    protected function makeOwner(): User
    {
        $starter = Plans::query()->where('name', 'Starter')->firstOrFail();
        config(['settings.free_pricing_id' => $starter->id]);

        $owner = User::factory()->create(['plan_id' => $starter->id]);
        $owner->assignRole('owner');
        $company = Company::factory()->create([
            'user_id' => $owner->id,
            'active' => 1,
        ]);
        $owner->update(['company_id' => $company->id]);
        session(['company_id' => $company->id]);

        return $owner;
    }

    public function test_chat_index_redirects_when_whatsapp_dormant(): void
    {
        $owner = $this->makeOwner();

        $this->actingAs($owner)
            ->get(route('chat.index'))
            ->assertRedirect(route('social.home'));
    }

    public function test_campaigns_index_redirects_when_whatsapp_dormant(): void
    {
        $owner = $this->makeOwner();

        $this->actingAs($owner)
            ->get(route('campaigns.index'))
            ->assertRedirect(route('social.home'));
    }

    public function test_whatsapp_setup_redirects_when_whatsapp_dormant(): void
    {
        $owner = $this->makeOwner();

        $this->actingAs($owner)
            ->get(route('whatsapp.setup'))
            ->assertRedirect(route('social.home'));
    }

    public function test_whatsapp_webhook_remains_reachable_when_dormant(): void
    {
        $response = $this->get('/webhook/wpbox/receive/test-token-xyz');

        $this->assertNotEquals(302, $response->status());
        $this->assertFalse(
            $response->isRedirect(route('social.home')),
            'Webhook should not be redirected by offering middleware'
        );
    }

    public function test_chat_is_reachable_when_offering_is_full(): void
    {
        config(['offering.mode' => Offering::MODE_FULL]);

        $owner = $this->makeOwner();

        $response = $this->actingAs($owner)->get(route('chat.index'));

        $this->assertFalse($response->isRedirect(route('social.home')));
    }

    public function test_dashboard_redirects_owners_to_social_home_when_dormant(): void
    {
        $owner = $this->makeOwner();

        $this->actingAs($owner)
            ->get(route('dashboard'))
            ->assertRedirect(route('social.home'));
    }
}
