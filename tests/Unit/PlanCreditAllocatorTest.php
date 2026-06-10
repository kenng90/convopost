<?php

namespace Tests\Unit;

use App\Models\Credit;
use App\Models\Plans;
use App\Models\User;
use App\Services\PlanCreditAllocator;
use Carbon\Carbon;
use Mockery;
use Tests\TestCase;

class PlanCreditAllocatorTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_grants_plan_credits_once_for_a_period(): void
    {
        config(['settings.enable_credits' => true]);

        $plan = new Plans([
            'id' => 10,
            'period' => 1,
            'credit_amount' => 2500,
        ]);
        $plan->id = 10;
        $plan->period = 1;
        $plan->credit_amount = 2500;

        $user = Mockery::mock(User::class)->makePartial();
        $user->id = 5;
        $user->shouldReceive('addCredits')
            ->once()
            ->with(2500.0, 'plan:10:2026-06', Mockery::type(Carbon::class));

        $creditAlias = Mockery::mock('alias:'.Credit::class);
        $creditAlias->shouldReceive('query->where->where->exists')
            ->once()
            ->andReturn(false);

        $allocator = new PlanCreditAllocator;
        $issuedAt = Carbon::parse('2026-06-01 10:00:00');

        $this->assertTrue($allocator->grantForUser($user, $plan, $issuedAt));
    }

    public function test_skips_grant_when_period_already_has_credit_entry(): void
    {
        config(['settings.enable_credits' => true]);

        $plan = new Plans([
            'id' => 10,
            'period' => 1,
            'credit_amount' => 2500,
        ]);
        $plan->id = 10;
        $plan->period = 1;
        $plan->credit_amount = 2500;

        $user = Mockery::mock(User::class)->makePartial();
        $user->id = 5;
        $user->shouldNotReceive('addCredits');

        $creditAlias = Mockery::mock('alias:'.Credit::class);
        $creditAlias->shouldReceive('query->where->where->exists')
            ->once()
            ->andReturn(true);

        $allocator = new PlanCreditAllocator;

        $this->assertFalse($allocator->grantForUser($user, $plan, Carbon::parse('2026-06-10')));
    }
}
