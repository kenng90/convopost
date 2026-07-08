<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class HostPinnacleAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_platform_admin_can_view_convoconnect_index(): void
    {
        Role::firstOrCreate(['name' => 'admin']);

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        config(['hostpinnacle.enabled' => true]);

        $this->actingAs($admin)
            ->get(route('admin.convoconnect.index'))
            ->assertOk()
            ->assertSee('ConvoConnect SMS')
            ->assertDontSee('HostPinnacle');
    }

    public function test_non_admin_cannot_access_convoconnect_admin(): void
    {
        Role::firstOrCreate(['name' => 'owner']);

        $owner = User::factory()->create();
        $owner->assignRole('owner');

        config(['hostpinnacle.enabled' => true]);

        $this->actingAs($owner)
            ->get(route('admin.convoconnect.index'))
            ->assertForbidden();
    }

    public function test_platform_admin_can_view_company_convoconnect_details(): void
    {
        Role::firstOrCreate(['name' => 'admin']);

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $company = Company::factory()->create();
        $company->setMultipleConfig([
            'HOSTPINNACLE_USER_ID' => 'tenant1',
            'HOSTPINNACLE_API_KEY' => 'secret-api-key-123456',
            'HOSTPINNACLE_SENDER_ID' => 'ACME',
            'HOSTPINNACLE_SENDER_STATUS' => 'pending_approval',
            'HOSTPINNACLE_SUB_LOGIN' => 'tenant1',
        ]);

        config(['hostpinnacle.enabled' => true]);

        $response = $this->actingAs($admin)
            ->get(route('admin.convoconnect.show', $company));

        $response->assertOk()
            ->assertSee('ACME')
            ->assertSee('secr')
            ->assertDontSee('HostPinnacle');

        $this->assertStringContainsString('secret-plain d-none', $response->getContent());
        $this->assertStringContainsString('secret-api-key-123456', $response->getContent());
    }

    public function test_platform_admin_can_mark_sender_id_approved(): void
    {
        Role::firstOrCreate(['name' => 'admin']);

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $company = Company::factory()->create();
        $company->setConfig('HOSTPINNACLE_SENDER_STATUS', 'pending_approval');

        $this->actingAs($admin)
            ->post(route('admin.convoconnect.approve-sender', $company))
            ->assertRedirect(route('admin.convoconnect.show', $company));

        $this->assertSame('approved', $company->fresh()->getConfig('HOSTPINNACLE_SENDER_STATUS'));
    }

    public function test_platform_admin_can_save_convoconnect_credentials_manually(): void
    {
        Role::firstOrCreate(['name' => 'admin']);

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $company = Company::factory()->create();
        config(['hostpinnacle.enabled' => true]);

        $this->actingAs($admin)
            ->post(route('admin.convoconnect.store-credentials', $company), [
                'sub_login' => 'cc1acmeco',
                'user_id' => 'cc1acmeco',
                'api_key' => 'manual-api-key',
                'password' => 'manual-pass',
                'sender_id' => 'ACME',
                'sender_status' => 'approved',
            ])
            ->assertRedirect(route('admin.convoconnect.show', $company));

        $company->refresh();
        $this->assertSame('cc1acmeco', $company->getConfig('HOSTPINNACLE_SUB_LOGIN'));
        $this->assertSame('manual-api-key', $company->getConfig('HOSTPINNACLE_API_KEY'));
        $this->assertSame('ACME', $company->getConfig('HOSTPINNACLE_SENDER_ID'));
        $this->assertSame('approved', $company->getConfig('HOSTPINNACLE_SENDER_STATUS'));
    }

    public function test_platform_admin_can_update_credentials_without_reentering_secrets(): void
    {
        Role::firstOrCreate(['name' => 'admin']);

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $company = Company::factory()->create();
        $company->setMultipleConfig([
            'HOSTPINNACLE_SUB_LOGIN' => 'cc1old',
            'HOSTPINNACLE_USER_ID' => 'cc1old',
            'HOSTPINNACLE_API_KEY' => 'existing-key',
            'HOSTPINNACLE_PASSWORD' => 'existing-pass',
            'HOSTPINNACLE_SENDER_ID' => 'OLD',
            'HOSTPINNACLE_SENDER_STATUS' => 'pending_approval',
        ]);

        config(['hostpinnacle.enabled' => true]);

        $this->actingAs($admin)
            ->post(route('admin.convoconnect.store-credentials', $company), [
                'sub_login' => 'cc1newlogin',
                'sender_id' => 'NEWCO',
                'sender_status' => 'approved',
            ])
            ->assertRedirect(route('admin.convoconnect.show', $company));

        $company->refresh();
        $this->assertSame('cc1newlogin', $company->getConfig('HOSTPINNACLE_SUB_LOGIN'));
        $this->assertSame('existing-key', $company->getConfig('HOSTPINNACLE_API_KEY'));
        $this->assertSame('existing-pass', $company->getConfig('HOSTPINNACLE_PASSWORD'));
        $this->assertSame('NEWCO', $company->getConfig('HOSTPINNACLE_SENDER_ID'));
    }

    public function test_non_admin_cannot_save_convoconnect_credentials(): void
    {
        Role::firstOrCreate(['name' => 'owner']);

        $owner = User::factory()->create();
        $owner->assignRole('owner');

        $company = Company::factory()->create();
        config(['hostpinnacle.enabled' => true]);

        $this->actingAs($owner)
            ->post(route('admin.convoconnect.store-credentials', $company), [
                'sub_login' => 'cc1acmeco',
                'api_key' => 'manual-api-key',
                'sender_id' => 'ACME',
                'sender_status' => 'approved',
            ])
            ->assertForbidden();
    }

    public function test_platform_admin_can_resume_convoconnect_provisioning(): void
    {
        Role::firstOrCreate(['name' => 'admin']);

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $company = Company::factory()->create(['subdomain' => 'acmeco']);
        $company->setConfig('HOSTPINNACLE_SUB_LOGIN', 'cc001acmeco');

        config([
            'hostpinnacle.enabled' => true,
            'hostpinnacle.reseller_user_id' => 'reseller',
            'hostpinnacle.reseller_api_key' => 'reseller-key',
        ]);

        Http::fake([
            'smsportal.hostpinnacle.co.ke/SMSApi/reseller/resetuserpassword' => Http::response([
                'response' => ['status' => 'success'],
            ], 200),
            'smsportal.hostpinnacle.co.ke/SMSApi/apikey/create' => Http::response([
                'response' => ['status' => 'success'],
            ], 200),
            'smsportal.hostpinnacle.co.ke/SMSApi/apikey/read' => Http::response([
                'response' => [
                    'status' => 'success',
                    'apikeyList' => ['apikey' => 'admin-resumed-key'],
                ],
            ], 200),
            'smsportal.hostpinnacle.co.ke/SMSApi/senderid/create' => Http::response([
                'response' => ['status' => 'success'],
            ], 200),
        ]);

        $this->actingAs($admin)
            ->post(route('admin.convoconnect.resume-provision', $company))
            ->assertRedirect(route('admin.convoconnect.show', $company));

        $this->assertSame('admin-resumed-key', $company->fresh()->getConfig('HOSTPINNACLE_API_KEY'));
    }
}
