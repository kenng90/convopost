<?php

namespace Tests\Unit;

use App\Models\Plans;
use App\Models\User;
use App\Services\DefaultPlanService;
use Database\Seeders\PlanEntitlementsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DefaultPlanServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_assign_to_user_sets_configured_default_plan(): void
    {
        $this->seed(PlanEntitlementsSeeder::class);

        $starter = Plans::query()->where('name', 'Starter')->firstOrFail();
        config(['settings.free_pricing_id' => $starter->id]);

        $user = User::factory()->create([
            'plan_id' => null,
            'plan_status' => 'active',
        ]);

        app(DefaultPlanService::class)->assignToUser($user, refreshCredits: false);

        $user->refresh();

        $this->assertSame($starter->id, $user->plan_id);
        $this->assertNull($user->plan_status);
    }

    public function test_falls_back_to_starter_plan_when_configured_id_is_missing(): void
    {
        $this->seed(PlanEntitlementsSeeder::class);

        $starter = Plans::query()->where('name', 'Starter')->firstOrFail();
        config(['settings.free_pricing_id' => 99999]);

        $user = User::factory()->create(['plan_id' => null]);

        app(DefaultPlanService::class)->assignToUser($user, refreshCredits: false);

        $user->refresh();

        $this->assertSame($starter->id, $user->plan_id);
    }

    public function test_stripe_subscription_deleted_downgrades_user_to_default_plan(): void
    {
        $this->seed(PlanEntitlementsSeeder::class);

        $starter = Plans::query()->where('name', 'Starter')->firstOrFail();
        $growth = Plans::query()->where('name', 'Growth')->firstOrFail();
        config(['settings.free_pricing_id' => $starter->id]);

        $user = User::factory()->create([
            'stripe_id' => 'cus_test_123',
            'plan_id' => $growth->id,
            'plan_status' => 'active',
        ]);

        app(DefaultPlanService::class)->handleStripeSubscriptionChange([
            'customer' => 'cus_test_123',
            'status' => 'canceled',
            'items' => [
                'data' => [
                    ['price' => ['id' => $growth->stripe_id ?? 'price_growth']],
                ],
            ],
        ]);

        $user->refresh();

        $this->assertSame($starter->id, $user->plan_id);
        $this->assertNull($user->plan_status);
    }

    public function test_stripe_subscription_pause_downgrades_user_to_default_plan(): void
    {
        $this->seed(PlanEntitlementsSeeder::class);

        $starter = Plans::query()->where('name', 'Starter')->firstOrFail();
        $growth = Plans::query()->where('name', 'Growth')->firstOrFail();
        config(['settings.free_pricing_id' => $starter->id]);

        $user = User::factory()->create([
            'stripe_id' => 'cus_test_pause',
            'plan_id' => $growth->id,
            'plan_status' => 'active',
        ]);

        app(DefaultPlanService::class)->handleStripeSubscriptionChange([
            'customer' => 'cus_test_pause',
            'status' => 'active',
            'pause_collection' => ['behavior' => 'void'],
            'items' => [
                'data' => [
                    ['price' => ['id' => 'price_growth']],
                ],
            ],
        ]);

        $user->refresh();

        $this->assertSame($starter->id, $user->plan_id);
    }

    public function test_stripe_subscription_resume_restores_paid_plan(): void
    {
        $starter = Plans::query()->create([
            'name' => 'Starter',
            'price' => 29,
            'period' => 1,
            'description' => 'Starter',
            'features' => 'Starter',
            'limit_items' => 0,
            'limit_views' => 1000,
            'limit_orders' => 500,
            'stripe_id' => 'price_starter',
        ]);

        $growth = Plans::query()->create([
            'name' => 'Growth',
            'price' => 79,
            'period' => 1,
            'description' => 'Growth',
            'features' => 'Growth',
            'limit_items' => 10,
            'limit_views' => 5000,
            'limit_orders' => 5000,
            'stripe_id' => 'price_growth',
        ]);

        config(['settings.free_pricing_id' => $starter->id]);

        $user = User::factory()->create([
            'stripe_id' => 'cus_test_resume',
            'plan_id' => $starter->id,
            'plan_status' => null,
        ]);

        app(DefaultPlanService::class)->handleStripeSubscriptionChange([
            'customer' => 'cus_test_resume',
            'status' => 'active',
            'pause_collection' => null,
            'items' => [
                'data' => [
                    ['price' => ['id' => 'price_growth']],
                ],
            ],
        ]);

        $user->refresh();

        $this->assertSame($growth->id, $user->plan_id);
        $this->assertSame('active', $user->plan_status);
    }
}
