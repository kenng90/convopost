<?php

namespace Tests\Unit;

use App\Models\Company;
use App\Models\Plans;
use App\Models\User;
use App\Services\PlanResourceLimit;
use App\Services\PlanSeatBillingService;
use App\Services\PlanUsageLimit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PlanSeatBillingServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'owner']);
        Role::create(['name' => 'staff']);

        config(['settings.enable_per_seat_billing' => true]);
    }

    public function test_billable_agent_seats_excludes_included_amount(): void
    {
        $plan = Plans::create([
            'name' => 'Growth',
            'included_agent_seats' => 3,
            'stripe_agent_seat_price_id' => 'price_agent_test',
            'limit_items' => 0,
            'limit_views' => 0,
            'limit_orders' => 0,
            'price' => 79,
            'period' => 1,
            'description' => 'Growth',
            'features' => 'Growth',
        ]);

        $owner = User::factory()->create(['plan_id' => $plan->id]);
        $owner->assignRole('owner');
        $company = Company::factory()->create(['user_id' => $owner->id]);

        foreach (range(1, 4) as $index) {
            $staff = User::factory()->create(['company_id' => $company->id]);
            $staff->assignRole('staff');
        }

        $service = new PlanSeatBillingService(new PlanResourceLimit(new PlanUsageLimit));

        $this->assertSame(1, $service->billableAgentSeats($owner, $plan));
    }

    public function test_billable_company_seats_excludes_included_amount(): void
    {
        $plan = Plans::create([
            'name' => 'Growth',
            'included_companies' => 1,
            'stripe_company_seat_price_id' => 'price_company_test',
            'limit_items' => 0,
            'limit_views' => 0,
            'limit_orders' => 0,
            'price' => 79,
            'period' => 1,
            'description' => 'Growth',
            'features' => 'Growth',
        ]);

        $owner = User::factory()->create(['plan_id' => $plan->id]);
        $owner->assignRole('owner');
        Company::factory()->count(3)->create(['user_id' => $owner->id]);

        $service = new PlanSeatBillingService(new PlanResourceLimit(new PlanUsageLimit));

        $this->assertSame(2, $service->billableCompanySeats($owner, $plan));
    }

    public function test_billing_summary_only_includes_configured_stripe_prices(): void
    {
        $plan = Plans::create([
            'name' => 'Growth',
            'included_agent_seats' => 3,
            'agent_seat_price' => 15,
            'stripe_agent_seat_price_id' => 'price_agent_test',
            'included_companies' => 1,
            'company_seat_price' => 25,
            'limit_items' => 0,
            'limit_views' => 0,
            'limit_orders' => 0,
            'price' => 79,
            'period' => 1,
            'description' => 'Growth',
            'features' => 'Growth',
        ]);

        $owner = User::factory()->create(['plan_id' => $plan->id]);
        Company::factory()->create(['user_id' => $owner->id]);

        $service = new PlanSeatBillingService(new PlanResourceLimit(new PlanUsageLimit));
        $summary = $service->getBillingSummary($owner);

        $this->assertCount(1, $summary);
        $this->assertSame('agents', $summary[0]['key']);
    }
}
