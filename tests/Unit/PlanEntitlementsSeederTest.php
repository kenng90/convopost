<?php

namespace Tests\Unit;

use Database\Seeders\PlanEntitlementsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlanEntitlementsSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_creates_four_tier_plans_with_capabilities(): void
    {
        $this->seed(PlanEntitlementsSeeder::class);

        $this->assertDatabaseHas('plan', ['name' => 'Starter', 'limit_views' => 1000, 'limit_agents' => 3, 'limit_companies' => 1]);
        $this->assertDatabaseHas('plan', ['name' => 'Growth', 'limit_items' => 10, 'limit_integrations' => 1]);
        $this->assertDatabaseHas('plan', ['name' => 'Pro', 'price' => 149]);
        $this->assertDatabaseHas('plan', ['name' => 'Agency', 'price' => 299]);
    }
}
