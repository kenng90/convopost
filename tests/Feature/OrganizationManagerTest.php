<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsurePlanPlugin;
use App\Models\Company;
use App\Models\CompanyMembership;
use App\Models\CompanyMembershipModule;
use App\Models\User;
use App\Services\CompanyMembershipService;
use App\Services\OrgAuthorization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class OrganizationManagerTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;

    protected Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'owner']);
        Role::firstOrCreate(['name' => 'staff']);
        Role::firstOrCreate(['name' => 'org_manager']);

        app(CompanyMembershipService::class)->seedSystemTemplates();

        $this->owner = User::factory()->create();
        $this->owner->assignRole('owner');

        $this->company = Company::factory()->create([
            'user_id' => $this->owner->id,
            'active' => 1,
        ]);

        $this->owner->update(['company_id' => $this->company->id]);

        OrgAuthorization::clearRouteModuleMapCache();
    }

    public function test_owner_can_create_manager_with_module_grants(): void
    {
        $response = $this->actingAs($this->owner)
            ->withSession(['company_id' => $this->company->id])
            ->post(route('orgmanager.store'), [
                'name' => 'Ops Manager',
                'email' => 'manager@example.com',
                'password' => 'secret123',
                'modules' => ['wpbox'],
                'permissions' => ['wpbox' => 'manage'],
            ]);

        $response->assertRedirect(route('orgmanager.index'));

        $membership = CompanyMembership::query()
            ->where('company_id', $this->company->id)
            ->where('role', CompanyMembership::ROLE_MANAGER)
            ->first();

        $this->assertNotNull($membership);
        $this->assertTrue($membership->user->hasRole('org_manager'));
        $this->assertTrue($membership->hasModule('wpbox', 'manage'));
    }

    public function test_manager_cannot_access_billing_routes(): void
    {
        $manager = $this->createManager(['wpbox']);

        $this->actingAs($manager)
            ->withSession(['company_id' => $this->company->id])
            ->get(route('plans.current'))
            ->assertForbidden();
    }

    public function test_manager_can_access_granted_module_routes(): void
    {
        $manager = $this->createManager(['wpbox']);

        $this->actingAs($manager)
            ->withSession(['company_id' => $this->company->id])
            ->get(route('dashboard'))
            ->assertOk();
    }

    public function test_manager_with_agents_manage_can_create_agent(): void
    {
        $manager = $this->createManager(['agents']);

        $this->actingAs($manager)
            ->withSession(['company_id' => $this->company->id])
            ->post(route('agent.store'), [
                'name' => 'New Agent',
                'email' => 'newagent@example.com',
                'password' => 'secret123',
            ])
            ->assertRedirect(route('agent.index'));

        $this->assertDatabaseHas('users', [
            'email' => 'newagent@example.com',
            'company_id' => $this->company->id,
        ]);
    }

    public function test_manager_with_agents_view_only_cannot_create_agent(): void
    {
        $membership = app(CompanyMembershipService::class)->createManager(
            $this->company,
            $this->owner,
            'Viewer',
            'viewer-agents@example.com',
            'secret123',
            [['module_alias' => 'agents', 'permission' => 'view']],
        );

        $this->actingAs($membership->user)
            ->withSession(['company_id' => $this->company->id])
            ->get(route('agent.create'))
            ->assertForbidden();
    }

    public function test_manager_with_agents_module_can_access_agent_list(): void
    {
        $manager = $this->createManager(['agents']);

        $this->actingAs($manager)
            ->withSession(['company_id' => $this->company->id])
            ->get(route('agent.index'))
            ->assertOk();
    }

    public function test_manager_without_agents_module_cannot_access_agent_list(): void
    {
        $manager = $this->createManager(['wpbox']);

        $this->actingAs($manager)
            ->withSession(['company_id' => $this->company->id])
            ->get(route('agent.index'))
            ->assertForbidden();
    }

    public function test_manager_cannot_access_ungranted_module_routes(): void
    {
        $manager = $this->createManager(['reports']);
        $orgAuth = app(\App\Services\OrgAuthorization::class);

        $this->assertFalse($orgAuth->canAccessModule($manager, 'wpbox'));
        $this->assertTrue($orgAuth->canAccessModule($manager, 'reports'));
        $this->assertFalse($orgAuth->canAccessRoute($manager, 'campaigns.index'));
    }

    public function test_manager_cannot_access_manager_admin_routes(): void
    {
        $manager = $this->createManager(['wpbox', 'managers']);

        $this->actingAs($manager)
            ->withSession(['company_id' => $this->company->id])
            ->get(route('orgmanager.index'))
            ->assertForbidden();
    }

    public function test_staff_migration_creates_agent_membership(): void
    {
        $staff = User::factory()->create(['company_id' => $this->company->id]);
        $staff->assignRole('staff');

        $this->artisan('org:migrate-staff-memberships')->assertSuccessful();

        $this->assertDatabaseHas('company_memberships', [
            'user_id' => $staff->id,
            'company_id' => $this->company->id,
            'role' => CompanyMembership::ROLE_AGENT,
            'status' => CompanyMembership::STATUS_ACTIVE,
        ]);
    }

    public function test_manager_with_view_permission_cannot_manage(): void
    {
        $membership = app(CompanyMembershipService::class)->createManager(
            $this->company,
            $this->owner,
            'Viewer',
            'viewer@example.com',
            'secret123',
            [['module_alias' => 'reports', 'permission' => 'view']],
        );

        $this->assertTrue($membership->hasModule('reports', 'view'));
        $this->assertFalse($membership->hasModule('reports', 'manage'));
    }

    public function test_manager_can_switch_between_assigned_organizations(): void
    {
        $companyB = Company::factory()->create(['user_id' => $this->owner->id, 'active' => 1]);

        $managerUser = User::factory()->create();
        $managerUser->assignRole('org_manager');

        CompanyMembership::create([
            'user_id' => $managerUser->id,
            'company_id' => $this->company->id,
            'role' => CompanyMembership::ROLE_MANAGER,
            'status' => CompanyMembership::STATUS_ACTIVE,
            'accepted_at' => now(),
        ]);

        CompanyMembership::create([
            'user_id' => $managerUser->id,
            'company_id' => $companyB->id,
            'role' => CompanyMembership::ROLE_MANAGER,
            'status' => CompanyMembership::STATUS_ACTIVE,
            'accepted_at' => now(),
        ]);

        CompanyMembershipModule::create([
            'company_membership_id' => CompanyMembership::where('user_id', $managerUser->id)->where('company_id', $companyB->id)->value('id'),
            'module_alias' => 'wpbox',
            'permission' => 'manage',
        ]);

        $this->actingAs($managerUser)
            ->withSession(['company_id' => $this->company->id])
            ->get(route('admin.companies.switch', $companyB->id))
            ->assertRedirect(route('home'));

        $this->assertEquals($companyB->id, session('company_id'));
    }

    public function test_manager_cannot_access_activation_routes(): void
    {
        $manager = $this->createManager(['wpbox']);
        $this->connectWhatsapp($this->company);

        $this->actingAs($manager)
            ->withSession(['company_id' => $this->company->id])
            ->get(route('activation.index'))
            ->assertForbidden();
    }

    public function test_manager_with_wpbox_can_access_health_alerts_api(): void
    {
        $manager = $this->createManager(['wpbox']);
        $this->connectWhatsapp($this->company);

        $this->actingAs($manager)
            ->withSession(['company_id' => $this->company->id])
            ->getJson(route('health-alerts.index'))
            ->assertOk()
            ->assertJsonStructure(['alerts', 'count']);
    }

    public function test_manager_with_flowmaker_can_access_flow_templates(): void
    {
        $this->withoutMiddleware(EnsurePlanPlugin::class);

        $manager = $this->createManager(['flowmaker']);

        $this->actingAs($manager)
            ->withSession(['company_id' => $this->company->id])
            ->get(route('flow-templates.index'))
            ->assertOk()
            ->assertSee('Flow templates library');
    }

    public function test_manager_is_not_redirected_to_activation_from_chat(): void
    {
        $this->withoutMiddleware(EnsurePlanPlugin::class);

        $manager = $this->createManager(['wpbox']);
        $this->connectWhatsapp($this->company);

        $this->actingAs($manager)
            ->withSession(['company_id' => $this->company->id])
            ->get(route('chat.index'))
            ->assertOk();
    }

    public function test_manager_without_wpbox_cannot_access_health_alerts_api(): void
    {
        $manager = $this->createManager(['reports']);

        $orgAuth = app(OrgAuthorization::class);
        $this->assertFalse($orgAuth->canAccessRoute($manager, 'health-alerts.index'));
    }

    private function connectWhatsapp(Company $company): void
    {
        $company->setMultipleConfig([
            'whatsapp_webhook_verified' => 'yes',
            'whatsapp_settings_done' => 'yes',
        ]);
    }

    private function createManager(array $modules): User
    {
        $grants = array_map(fn (string $alias) => [
            'module_alias' => $alias,
            'permission' => 'manage',
        ], $modules);

        $membership = app(CompanyMembershipService::class)->createManager(
            $this->company,
            $this->owner,
            'Test Manager',
            fake()->unique()->safeEmail(),
            'secret123',
            $grants,
        );

        return $membership->user;
    }
}
