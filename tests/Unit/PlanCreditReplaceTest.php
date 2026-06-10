<?php

namespace Tests\Unit;

use App\Models\Company;
use App\Models\Credit;
use App\Models\Plans;
use App\Models\User;
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

        $owner = User::factory()->create();
        Company::factory()->create(['user_id' => $owner->id]);
        $issuedAt = Carbon::parse('2026-06-01 10:00:00');
        $allocator = new PlanCreditAllocator;

        $allocator->grantForUser($owner, $oldPlan, $issuedAt);
        $this->assertSame(1000, $owner->fresh()->getTotalRemainingCredits());

        $allocator->replacePlanCreditsForUser($owner, $newPlan, $issuedAt);

        $this->assertSame(5000, $owner->fresh()->getTotalRemainingCredits());
        $this->assertSame(1, Credit::where('user_id', $owner->id)->where('source', 'like', 'plan:%')->count());
    }
}
