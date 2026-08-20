<?php

namespace Tests\Feature;

use App\Http\Middleware\Activation;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CompanyAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(Activation::class);
        Role::firstOrCreate(['name' => 'owner']);
        Role::firstOrCreate(['name' => 'admin']);
    }

    public function test_owner_cannot_update_another_company(): void
    {
        $ownerA = User::factory()->create();
        $ownerA->assignRole('owner');
        $companyA = Company::factory()->create(['user_id' => $ownerA->id, 'name' => 'Alpha Co']);
        $ownerA->update(['company_id' => $companyA->id]);

        $ownerB = User::factory()->create();
        $ownerB->assignRole('owner');
        $companyB = Company::factory()->create(['user_id' => $ownerB->id, 'name' => 'Beta Co']);

        $response = $this->actingAs($ownerA)->put(route('admin.companies.update', $companyB->id), [
            'name' => 'Hacked',
            'address' => 'Somewhere',
            'phone' => '123',
            'description' => 'nope',
            'currency' => 'USD',
            'do_covertion' => 'false',
        ]);

        $response->assertForbidden();
        $this->assertSame('Beta Co', $companyB->fresh()->name);
    }

    public function test_owner_cannot_activate_another_company(): void
    {
        $ownerA = User::factory()->create();
        $ownerA->assignRole('owner');
        $companyA = Company::factory()->create(['user_id' => $ownerA->id]);
        $ownerA->update(['company_id' => $companyA->id]);

        $ownerB = User::factory()->create();
        $ownerB->assignRole('owner');
        $companyB = Company::factory()->create(['user_id' => $ownerB->id, 'active' => 0]);

        $response = $this->actingAs($ownerA)->get(route('admin.company.activate', $companyB->id));

        $response->assertForbidden();
        $this->assertSame(0, (int) $companyB->fresh()->active);
    }

    public function test_guest_cannot_trigger_company_notify(): void
    {
        $company = Company::factory()->create();

        $this->get(route('company.notify', ['type' => 'test', 'id' => $company->id, 'message' => 'hello']))
            ->assertRedirect();
    }
}
