<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureOwnerIsOnPROPlan;
use App\Models\Company;
use App\Models\Plans;
use App\Models\User;
use Database\Seeders\PlanEntitlementsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EnsureOwnerIsOnProPlanTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'owner']);
        $this->seed(PlanEntitlementsSeeder::class);
    }

    public function test_owner_on_default_plan_passes_middleware_when_force_pay_is_disabled(): void
    {
        $starter = Plans::query()->where('name', 'Starter')->firstOrFail();
        config([
            'settings.free_pricing_id' => $starter->id,
            'settings.forceUserToPay' => false,
        ]);

        $owner = User::factory()->create([
            'plan_id' => $starter->id,
        ]);
        $owner->assignRole('owner');
        Company::factory()->create([
            'user_id' => $owner->id,
            'active' => 1,
        ]);

        $this->actingAs($owner);

        $response = app(EnsureOwnerIsOnPROPlan::class)->handle(
            Request::create('/chat', 'GET'),
            fn () => response('ok', 200)
        );

        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_owner_on_default_plan_is_blocked_when_force_pay_is_enabled(): void
    {
        $starter = Plans::query()->where('name', 'Starter')->firstOrFail();
        config([
            'settings.free_pricing_id' => $starter->id,
            'settings.forceUserToPay' => true,
        ]);

        $owner = User::factory()->create([
            'plan_id' => $starter->id,
        ]);
        $owner->assignRole('owner');
        Company::factory()->create([
            'user_id' => $owner->id,
            'active' => 1,
        ]);

        $this->actingAs($owner);

        $response = app(EnsureOwnerIsOnPROPlan::class)->handle(
            Request::create('/chat', 'GET'),
            fn () => response('ok', 200)
        );

        $this->assertTrue($response->isRedirect(route('plans.current')));
        $this->assertSame(__('You need to subscribe to a plan'), session('error'));
    }
}
