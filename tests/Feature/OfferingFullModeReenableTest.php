<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Plans;
use App\Models\User;
use App\Services\PlanEntitlementResolver;
use App\Support\Offering;
use Database\Seeders\PlanEntitlementsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class OfferingFullModeReenableTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'owner']);
        $this->seed(PlanEntitlementsSeeder::class);

        config(['settings.forceUserToPay' => false]);
    }

    protected function ownerOnPlan(string $planName): User
    {
        $plan = Plans::query()->where('name', $planName)->firstOrFail();
        config(['settings.free_pricing_id' => $plan->id]);

        $owner = User::factory()->create(['plan_id' => $plan->id]);
        $owner->assignRole('owner');
        $company = Company::factory()->create([
            'user_id' => $owner->id,
            'active' => 1,
        ]);
        $owner->update(['company_id' => $company->id]);
        session(['company_id' => $company->id]);

        return $owner->fresh();
    }

    public function test_seeded_plans_include_inbox_and_campaign_capabilities_for_full_mode(): void
    {
        $resolver = app(PlanEntitlementResolver::class);

        $starter = Plans::query()->where('name', 'Starter')->firstOrFail();
        $growth = Plans::query()->where('name', 'Growth')->firstOrFail();
        $pro = Plans::query()->where('name', 'Pro')->firstOrFail();

        $this->assertTrue($resolver->hasCapability($starter, 'inbox'));
        $this->assertFalse($resolver->hasCapability($starter, 'campaigns'));

        $this->assertTrue($resolver->hasCapability($growth, 'inbox'));
        $this->assertTrue($resolver->hasCapability($growth, 'campaigns'));

        $this->assertTrue($resolver->hasCapability($pro, 'inbox'));
        $this->assertTrue($resolver->hasCapability($pro, 'campaigns'));
        $this->assertTrue($resolver->hasCapability($pro, 'whatsapp_flows'));
        $this->assertTrue($resolver->hasCapability($pro, 'inbox_instagram'));

        $proPlugins = json_decode((string) $pro->getConfig('plugins', '[]'), true);
        $this->assertContains('whatsappflows', $proPlugins);
        $this->assertContains('whatsappcall', $proPlugins);
        $this->assertContains('instagram', $proPlugins);
        $this->assertContains('messenger', $proPlugins);
    }

    public function test_full_mode_exposes_chat_and_campaign_menus_for_pro(): void
    {
        config(['offering.mode' => Offering::MODE_FULL]);

        $owner = $this->ownerOnPlan('Pro');
        $routes = collect($owner->collectOwnerModuleMenus())->pluck('route')->filter()->values()->all();

        $this->assertContains('chat.index', $routes);
        $this->assertContains('campaigns.index', $routes);
        $this->assertContains('whatsapp.setup', $routes);
    }

    public function test_full_mode_allows_campaigns_for_growth_plan(): void
    {
        config(['offering.mode' => Offering::MODE_FULL]);

        $owner = $this->ownerOnPlan('Growth');

        $response = $this->actingAs($owner)->get(route('campaigns.index'));

        $this->assertFalse(
            $response->isRedirect(route('social.calendar')),
            'Campaigns must not be blocked by offering middleware when mode is full'
        );
        $this->assertFalse(
            $response->isRedirect(route('plans.current')),
            'Growth plan must include campaigns capability after re-enable'
        );
    }

    public function test_social_commerce_still_blocks_chat_even_with_inbox_capability(): void
    {
        config(['offering.mode' => Offering::MODE_SOCIAL_COMMERCE]);

        $owner = $this->ownerOnPlan('Pro');

        $this->actingAs($owner)
            ->get(route('chat.index'))
            ->assertRedirect(route('social.calendar'));
    }
}
