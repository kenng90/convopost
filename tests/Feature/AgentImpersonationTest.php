<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use App\Services\CompanyMembershipService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AgentImpersonationTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;

    protected Company $company;

    protected User $agent;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'owner']);
        Role::firstOrCreate(['name' => 'staff']);

        $this->owner = User::factory()->create();
        $this->owner->assignRole('owner');

        $this->company = Company::factory()->create([
            'user_id' => $this->owner->id,
            'active' => 1,
        ]);

        $this->owner->update(['company_id' => $this->company->id]);

        $this->agent = User::factory()->create(['company_id' => $this->company->id]);
        $this->agent->assignRole('staff');

        app(CompanyMembershipService::class)->ensureAgentMembership(
            $this->agent,
            $this->company,
            $this->owner
        );
    }

    public function test_owner_can_login_as_agent_and_return_home(): void
    {
        $this->actingAs($this->owner)
            ->withSession(['company_id' => $this->company->id])
            ->get(route('agent.loginas', $this->agent->id))
            ->assertRedirect(route('home'));

        $this->assertEquals($this->agent->id, session('impersonate'));
        $this->assertEquals($this->owner->id, session('impersonator_id'));

        $homeWhileImpersonating = $this->actingAs($this->owner)
            ->withSession([
                'company_id' => $this->company->id,
                'impersonate' => $this->agent->id,
                'impersonator_id' => $this->owner->id,
            ])
            ->get(route('home'));

        $this->assertNotEquals(403, $homeWhileImpersonating->status());

        $this->actingAs($this->owner)
            ->withSession([
                'company_id' => $this->company->id,
                'impersonate' => $this->agent->id,
                'impersonator_id' => $this->owner->id,
            ])
            ->get(route('admin.companies.stopImpersonate'))
            ->assertRedirect(route('home'));

        $this->assertNull(session('impersonate'));
        $this->assertNull(session('impersonator_id'));

        $homeAfterStop = $this->actingAs($this->owner)
            ->withSession(['company_id' => $this->company->id])
            ->get(route('home'));

        $this->assertNotEquals(403, $homeAfterStop->status());
    }

    public function test_owner_session_is_preserved_during_agent_impersonation(): void
    {
        $this->actingAs($this->owner)
            ->withSession(['company_id' => $this->company->id])
            ->get(route('agent.loginas', $this->agent->id));

        $this->assertAuthenticatedAs($this->owner);
    }
}
