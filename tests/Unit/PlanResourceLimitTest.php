<?php

namespace Tests\Unit;

use App\Models\Company;
use App\Models\Plans;
use App\Models\User;
use App\Services\PlanResourceLimit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PlanResourceLimitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'admin']);
        Role::create(['name' => 'owner']);
        Role::create(['name' => 'staff']);
    }

    public function test_agent_limit_blocks_when_seats_are_full(): void
    {
        $plan = Plans::create([
            'name' => 'Starter',
            'limit_agents' => 1,
            'limit_companies' => 0,
            'limit_integrations' => 0,
            'limit_items' => 0,
            'limit_views' => 0,
            'limit_orders' => 0,
            'price' => 29,
            'period' => 1,
            'description' => 'Starter',
            'features' => 'Starter',
        ]);

        $owner = User::factory()->create(['plan_id' => $plan->id]);
        $owner->assignRole('owner');
        $company = Company::factory()->create(['user_id' => $owner->id]);
        $owner->update(['company_id' => $company->id]);

        $staff = User::factory()->create(['company_id' => $company->id]);
        $staff->assignRole('staff');

        $service = new PlanResourceLimit(new \App\Services\PlanUsageLimit);

        $this->assertFalse($service->canAddAgent($company));
        $this->assertStringContainsString('agent seat limit', strtolower($service->agentLimitExceededMessage($company)));
    }

    public function test_company_limit_blocks_additional_organizations(): void
    {
        $plan = Plans::create([
            'name' => 'Starter',
            'limit_agents' => 0,
            'limit_companies' => 1,
            'limit_integrations' => 0,
            'limit_items' => 0,
            'limit_views' => 0,
            'limit_orders' => 0,
            'price' => 29,
            'period' => 1,
            'description' => 'Starter',
            'features' => 'Starter',
        ]);

        $owner = User::factory()->create(['plan_id' => $plan->id]);
        $owner->assignRole('owner');
        Company::factory()->create(['user_id' => $owner->id]);

        $service = new PlanResourceLimit(new \App\Services\PlanUsageLimit);

        $this->assertFalse($service->canAddCompany($owner));
    }

    public function test_integration_limit_blocks_second_store_connection(): void
    {
        $plan = Plans::create([
            'name' => 'Growth',
            'limit_agents' => 0,
            'limit_companies' => 0,
            'limit_integrations' => 1,
            'limit_items' => 0,
            'limit_views' => 0,
            'limit_orders' => 0,
            'price' => 79,
            'period' => 1,
            'description' => 'Growth',
            'features' => 'Growth',
        ]);

        $owner = User::factory()->create(['plan_id' => $plan->id]);
        $company = Company::factory()->create(['user_id' => $owner->id]);
        $owner->update(['company_id' => $company->id]);

        $company->setMultipleConfig([
            'shopify_store_name' => 'demo-store',
            'shopify_access_token' => 'shpat_test',
        ]);

        $service = new PlanResourceLimit(new \App\Services\PlanUsageLimit);

        $error = $service->validateIntegrationConfigUpdate($company, [
            'woocommerce_store_url' => 'https://shop.test',
            'woocommerce_consumer_key' => 'ck_test',
        ]);

        $this->assertNotNull($error);
        $this->assertStringContainsString('integration limit', strtolower($error));
    }

    public function test_usage_summary_includes_resource_meters(): void
    {
        $plan = Plans::create([
            'name' => 'Growth',
            'limit_agents' => 5,
            'limit_companies' => 3,
            'limit_integrations' => 1,
            'limit_items' => 0,
            'limit_views' => 0,
            'limit_orders' => 0,
            'price' => 79,
            'period' => 1,
            'description' => 'Growth',
            'features' => 'Growth',
        ]);

        $owner = User::factory()->create(['plan_id' => $plan->id]);
        $company = Company::factory()->create(['user_id' => $owner->id]);
        $owner->update(['company_id' => $company->id]);

        $summary = (new PlanResourceLimit(new \App\Services\PlanUsageLimit))->getUsageSummary($company);

        $this->assertCount(3, $summary);
        $this->assertSame('agents', $summary[0]['key']);
        $this->assertSame(5, $summary[0]['limit']);
    }
}
