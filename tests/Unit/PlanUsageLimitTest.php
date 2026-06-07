<?php

namespace Tests\Unit;

use App\Models\Company;
use App\Models\Plans;
use App\Models\User;
use App\Services\PlanUsageLimit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PlanUsageLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_yearly_plan_multiplies_numeric_limits_by_twelve(): void
    {
        $plan = Plans::create([
            'name' => 'Growth Annual',
            'limit_items' => 10,
            'limit_views' => 1000,
            'limit_orders' => 500,
            'price' => 790,
            'period' => 2,
            'description' => 'Annual',
            'features' => 'Annual growth',
        ]);

        $service = new PlanUsageLimit;
        $allowed = $service->getAllowedLimits($plan);

        $this->assertSame(120, $allowed['campaigns']);
        $this->assertSame(12000, $allowed['messages']);
        $this->assertSame(6000, $allowed['contacts']);
    }

    public function test_first_exceeded_limit_detects_contact_cap(): void
    {
        $plan = Plans::create([
            'name' => 'Starter',
            'limit_items' => 0,
            'limit_views' => 0,
            'limit_orders' => 2,
            'price' => 29,
            'period' => 1,
            'description' => 'Starter',
            'features' => 'Starter',
        ]);

        $user = User::factory()->create(['plan_id' => $plan->id]);
        $company = Company::factory()->create(['user_id' => $user->id]);
        $user->update(['company_id' => $company->id]);

        DB::table('contacts')->insert([
            ['company_id' => $company->id, 'name' => 'A', 'phone' => '1', 'created_at' => now(), 'updated_at' => now()],
            ['company_id' => $company->id, 'name' => 'B', 'phone' => '2', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $service = new PlanUsageLimit;

        $this->assertSame('contacts', $service->firstExceededLimit($company, $plan));
    }

    public function test_usage_summary_marks_unlimited_limits(): void
    {
        $plan = Plans::create([
            'name' => 'Pro',
            'limit_items' => 0,
            'limit_views' => 0,
            'limit_orders' => 0,
            'price' => 149,
            'period' => 1,
            'description' => 'Pro',
            'features' => 'Pro',
        ]);

        $user = User::factory()->create(['plan_id' => $plan->id]);
        $company = Company::factory()->create(['user_id' => $user->id]);
        $user->update(['company_id' => $company->id]);

        $summary = (new PlanUsageLimit)->getUsageSummary($company);

        $this->assertCount(6, $summary);
        $this->assertTrue(collect($summary)->take(3)->every(fn (array $row) => $row['unlimited'] === true));
    }
}
