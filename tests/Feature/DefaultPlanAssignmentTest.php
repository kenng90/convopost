<?php

namespace Tests\Feature;

use App\Actions\Fortify\CreateNewUser;
use App\Models\Plans;
use App\Models\User;
use Database\Seeders\PlanEntitlementsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DefaultPlanAssignmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'owner']);
        Role::firstOrCreate(['name' => 'client']);
        $this->seed(PlanEntitlementsSeeder::class);

        $starter = Plans::query()->where('name', 'Starter')->firstOrFail();
        config(['settings.free_pricing_id' => $starter->id]);
    }

    public function test_owner_signup_assigns_starter_default_plan(): void
    {
        $user = app(CreateNewUser::class)->create([
            'name' => 'Acme Owner',
            'email' => 'owner@example.com',
            'phone' => '712345678',
            'country_code' => '254',
            'password' => 'password',
            'password_confirmation' => 'password',
            'terms' => true,
        ]);

        $starter = Plans::query()->where('name', 'Starter')->firstOrFail();

        $this->assertTrue($user->hasRole('owner'));
        $this->assertSame($starter->id, $user->fresh()->plan_id);
        $this->assertNull($user->fresh()->plan_status);
    }

    public function test_client_signup_does_not_assign_owner_default_plan(): void
    {
        $owner = User::factory()->create();
        $owner->assignRole('owner');

        $companyId = DB::table('companies')->insertGetId([
            'name' => 'Client Company',
            'subdomain' => 'clientcompany',
            'user_id' => $owner->id,
            'created_at' => now(),
            'updated_at' => now(),
            'phone' => '+254700000000',
            'logo' => '/default/no_image.jpg',
        ]);

        session(['company_id' => $companyId]);

        $user = app(CreateNewUser::class)->create([
            'name' => 'Client User',
            'email' => 'client@example.com',
            'phone' => '798765432',
            'country_code' => '254',
            'password' => 'password',
            'password_confirmation' => 'password',
            'terms' => true,
            'company_id' => $companyId,
        ]);

        $this->assertTrue($user->hasRole('client'));
        $this->assertNull($user->fresh()->plan_id);
    }
}
