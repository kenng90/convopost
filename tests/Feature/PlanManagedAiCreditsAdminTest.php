<?php

namespace Tests\Feature;

use App\Models\Plans;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PlanManagedAiCreditsAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_update_ai_credits_per_billing_period_on_plan(): void
    {
        Role::firstOrCreate(['name' => 'admin']);

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $plan = Plans::create([
            'name' => 'Pro',
            'price' => 149,
            'period' => 1,
            'description' => 'Pro plan',
            'features' => 'Pro features',
            'limit_items' => 0,
            'limit_views' => 0,
            'limit_orders' => 0,
            'limit_catalog_items' => 0,
            'limit_agents' => 0,
            'limit_companies' => 0,
            'limit_integrations' => 0,
            'enable_ordering' => 1,
        ]);

        $response = $this->actingAs($admin)->put(route('plans.update', $plan), [
            'name' => 'Pro',
            'price' => 149,
            'description' => 'Pro plan',
            'features' => 'Pro features',
            'period' => 'monthly',
            'ordering' => 'enabled',
            'limit_items' => 0,
            'limit_views' => 0,
            'limit_orders' => 0,
            'limit_catalog_items' => 0,
            'limit_agents' => 0,
            'limit_companies' => 0,
            'limit_integrations' => 0,
            'included_agent_seats' => 0,
            'agent_seat_price' => 0,
            'included_companies' => 0,
            'company_seat_price' => 0,
            'managed_ai_monthly_credits' => 750,
        ]);

        $response->assertRedirect(route('plans.index'));
        $this->assertSame('750', $plan->fresh()->getConfig('managed_ai_monthly_credits'));
    }
}
