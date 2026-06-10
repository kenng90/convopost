<?php

namespace Tests\Unit;

use App\Models\Plans;
use App\Services\PlanEntitlementResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlanEntitlementResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_null_capabilities_means_all_features_allowed(): void
    {
        $plan = Plans::create([
            'name' => 'Agency',
            'limit_items' => 0,
            'limit_orders' => 0,
            'limit_views' => 0,
            'price' => 299,
            'period' => 1,
            'description' => 'Agency',
            'features' => 'Agency',
        ]);

        $resolver = new PlanEntitlementResolver;

        $this->assertTrue($resolver->hasCapability($plan, 'campaigns'));
        $this->assertTrue($resolver->hasCapability($plan, 'api_access'));
    }

    public function test_starter_plan_blocks_campaigns_capability(): void
    {
        $plan = Plans::create([
            'name' => 'Starter',
            'limit_items' => 0,
            'limit_orders' => 500,
            'limit_views' => 1000,
            'price' => 29,
            'period' => 1,
            'description' => 'Starter',
            'features' => 'Starter',
        ]);

        $plan->setConfig('capabilities', json_encode(['inbox', 'contacts', 'flows']));

        $resolver = new PlanEntitlementResolver;

        $this->assertFalse($resolver->hasCapability($plan, 'campaigns'));
        $this->assertFalse($resolver->hasCapability($plan, 'api_access'));
        $this->assertTrue($resolver->hasCapability($plan, 'flows'));
    }
}
