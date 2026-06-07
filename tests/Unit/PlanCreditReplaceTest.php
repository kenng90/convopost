<?php

namespace Tests\Unit;

use App\Models\Company;
use App\Models\Credit;
use App\Models\Plans;
use App\Services\PlanCreditAllocator;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlanCreditReplaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_replace_plan_credits_swaps_to_new_plan_allocation(): void
    {
        config(['settings.enable_credits' => true]);

        $oldPlan = Plans::create([
            'name' => 'Starter',
            'credit_amount' => 1000,
            'limit_items' => 0,
            'limit_views' => 0,
            'limit_orders' => 0,
            'price' => 29,
            'period' => 1,
            'description' => 'Starter',
            'features' => 'Starter',
        ]);

        $newPlan = Plans::create([
            'name' => 'Pro',
            'credit_amount' => 5000,
            'limit_items' => 0,
            'limit_views' => 0,
            'limit_orders' => 0,
            'price' => 149,
            'period' => 1,
            'description' => 'Pro',
            'features' => 'Pro',
        ]);

        $company = Company::factory()->create();
        $issuedAt = Carbon::parse('2026-06-01 10:00:00');
        $allocator = new PlanCreditAllocator;

        $allocator->grantForCompany($company, $oldPlan, $issuedAt);
        $this->assertSame(1000, $company->fresh()->getTotalRemainingCredits());

        $allocator->replacePlanCreditsForCompany($company, $newPlan, $issuedAt);

        $this->assertSame(5000, $company->fresh()->getTotalRemainingCredits());
        $this->assertSame(1, Credit::where('company_id', $company->id)->where('source', 'like', 'plan:%')->count());
    }
}
