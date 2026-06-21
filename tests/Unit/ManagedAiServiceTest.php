<?php

namespace Tests\Unit;

use App\Models\Company;
use App\Models\Plans;
use App\Models\User;
use App\Services\Platform\ManagedAiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ManagedAiServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_monthly_allowance_uses_owner_plan_not_company_plan_accessor(): void
    {
        $plan = Plans::create([
            'name' => 'Pro Monthly',
            'limit_items' => 0,
            'limit_views' => 0,
            'limit_orders' => 0,
            'price' => 149,
            'period' => 1,
            'description' => 'Pro',
            'features' => 'Pro features',
        ]);

        $owner = User::factory()->create(['plan_id' => $plan->id]);
        $company = Company::factory()->create(['user_id' => $owner->id]);

        $status = app(ManagedAiService::class)->status($company);

        $this->assertSame(1000, $status['monthly_allowance']);
        $this->assertSame(1000, $status['remaining']);
        $this->assertIsArray($company->plan);
    }
}
