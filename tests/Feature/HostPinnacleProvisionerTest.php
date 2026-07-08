<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use App\Services\HostPinnacle\HostPinnacleProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class HostPinnacleProvisionerTest extends TestCase
{
    use RefreshDatabase;

    public function test_provisioner_creates_sub_account_and_sender_id(): void
    {
        Role::firstOrCreate(['name' => 'owner']);

        config([
            'hostpinnacle.enabled' => true,
            'hostpinnacle.reseller_user_id' => 'reseller',
            'hostpinnacle.reseller_api_key' => 'reseller-key',
        ]);

        Http::fake([
            'smsportal.hostpinnacle.co.ke/SMSApi/reseller/createuser' => Http::response([
                'response' => ['status' => 'success', 'msg' => 'created'],
            ], 200),
            'smsportal.hostpinnacle.co.ke/SMSApi/reseller/resetuserpassword' => Http::response([
                'response' => ['status' => 'success', 'msg' => 'Password changed successfully.'],
            ], 200),
            'smsportal.hostpinnacle.co.ke/SMSApi/apikey/create' => Http::response([
                'response' => ['status' => 'success', 'msg' => 'ApiKey Created successfully.'],
            ], 200),
            'smsportal.hostpinnacle.co.ke/SMSApi/apikey/read' => Http::response([
                'response' => [
                    'status' => 'success',
                    'apikeyList' => ['apikey' => 'tenant-api-key'],
                ],
            ], 200),
            'smsportal.hostpinnacle.co.ke/SMSApi/senderid/create' => Http::response([
                'response' => ['status' => 'success', 'msg' => 'requested'],
            ], 200),
        ]);

        $owner = User::factory()->create();
        $owner->assignRole('owner');
        $company = Company::factory()->create([
            'user_id' => $owner->id,
            'subdomain' => 'acmeco',
            'phone' => '0712345678',
        ]);

        $this->assertTrue(app(HostPinnacleProvisioner::class)->provision($company));

        Http::assertSent(function ($request) {
            if (! str_contains($request->url(), '/SMSApi/reseller/createuser')) {
                return false;
            }

            $body = $request->body();

            return str_contains($body, 'cc001acmeco')
                && str_contains($body, 'customer')
                && str_contains($body, '254712345678')
                && str_contains($body, 'Kenya')
                && str_contains($body, 'Nairobi');
        });

        $this->assertSame('tenant-api-key', $company->fresh()->getConfig('HOSTPINNACLE_API_KEY'));
        $this->assertSame('pending_approval', $company->fresh()->getConfig('HOSTPINNACLE_SENDER_STATUS'));
    }

    public function test_sub_user_login_name_is_at_least_five_characters(): void
    {
        $company = Company::factory()->make([
            'id' => 1,
            'subdomain' => '',
        ]);

        $login = app(HostPinnacleProvisioner::class)->subUserLoginName($company);

        $this->assertSame('cc001', $login);
        $this->assertGreaterThanOrEqual(5, strlen($login));
    }

    public function test_sub_user_login_name_respects_fifteen_character_limit(): void
    {
        $company = Company::factory()->make([
            'id' => 1,
            'subdomain' => 'verylongsubdomainname',
        ]);

        $login = app(HostPinnacleProvisioner::class)->subUserLoginName($company);

        $this->assertSame('cc001verylongsu', $login);
        $this->assertLessThanOrEqual(15, strlen($login));
    }

    public function test_gateway_full_name_strips_non_alphanumeric_characters(): void
    {
        $company = Company::factory()->make([
            'id' => 1,
            'name' => 'Acme & Co. (Ltd.)',
        ]);

        $fullName = app(HostPinnacleProvisioner::class)->gatewayFullName($company);

        $this->assertSame('Acme Co Ltd', $fullName);
    }

    public function test_build_create_user_payload_matches_gateway_requirements(): void
    {
        $owner = User::factory()->make(['email' => 'owner@example.com']);
        $company = Company::factory()->make([
            'id' => 12,
            'subdomain' => 'acmeco',
            'name' => 'Acme Company',
            'address' => "Line 1\nLine 2\nLine 3\nLine 4\nLine 5",
            'phone' => '+254 712 345 678',
        ]);
        $company->setRelation('user', $owner);

        $provisioner = app(HostPinnacleProvisioner::class);
        $payload = $provisioner->buildCreateUserPayload($company);

        $this->assertNull($provisioner->validateCreateUserPayload($payload));
        $this->assertSame('cc012acmeco', $payload['userloginname']);
        $this->assertSame('customer', $payload['usertype']);
        $this->assertSame('owner@example.com', $payload['email']);
        $this->assertSame('254712345678', $payload['mobileno']);
        $this->assertSame("Line 1\nLine 2\nLine 3\nLine 4", $payload['address']);
        $this->assertSame('Nairobi', $payload['city']);
        $this->assertSame('Kenya', $payload['country']);
    }

    public function test_provisioner_reads_api_key_when_create_response_omits_it(): void
    {
        Role::firstOrCreate(['name' => 'owner']);

        config([
            'hostpinnacle.enabled' => true,
            'hostpinnacle.reseller_user_id' => 'reseller',
            'hostpinnacle.reseller_api_key' => 'reseller-key',
        ]);

        Http::fake([
            'smsportal.hostpinnacle.co.ke/SMSApi/reseller/createuser' => Http::response([
                'response' => ['status' => 'success'],
            ], 200),
            'smsportal.hostpinnacle.co.ke/SMSApi/reseller/resetuserpassword' => Http::response([
                'response' => ['status' => 'success'],
            ], 200),
            'smsportal.hostpinnacle.co.ke/SMSApi/apikey/create' => Http::response([
                'response' => ['status' => 'success', 'msg' => 'ApiKey Created successfully.'],
            ], 200),
            'smsportal.hostpinnacle.co.ke/SMSApi/apikey/read' => Http::response([
                'response' => [
                    'status' => 'success',
                    'apikeyList' => ['apikey' => 'read-api-key'],
                ],
            ], 200),
            'smsportal.hostpinnacle.co.ke/SMSApi/senderid/create' => Http::response([
                'response' => ['status' => 'success'],
            ], 200),
        ]);

        $owner = User::factory()->create();
        $owner->assignRole('owner');
        $company = Company::factory()->create(['user_id' => $owner->id]);

        $this->assertTrue(app(HostPinnacleProvisioner::class)->provision($company));
        $this->assertSame('read-api-key', $company->fresh()->getConfig('HOSTPINNACLE_API_KEY'));
    }

    public function test_provisioner_resume_skips_create_user(): void
    {
        Role::firstOrCreate(['name' => 'owner']);

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
                    'apikeyList' => ['apikey' => 'resumed-api-key'],
                ],
            ], 200),
            'smsportal.hostpinnacle.co.ke/SMSApi/senderid/create' => Http::response([
                'response' => ['status' => 'success'],
            ], 200),
        ]);

        $owner = User::factory()->create();
        $owner->assignRole('owner');
        $company = Company::factory()->create([
            'user_id' => $owner->id,
            'subdomain' => 'acmeco',
        ]);
        $company->setConfig('HOSTPINNACLE_SUB_LOGIN', 'cc001acmeco');

        $this->assertTrue(app(HostPinnacleProvisioner::class)->resume($company));

        Http::assertNotSent(function ($request) {
            return str_contains($request->url(), '/SMSApi/reseller/createuser');
        });

        $this->assertSame('resumed-api-key', $company->fresh()->getConfig('HOSTPINNACLE_API_KEY'));
        $this->assertSame('cc001acmeco', $company->fresh()->getConfig('HOSTPINNACLE_SUB_LOGIN'));
    }

    public function test_provisioner_falls_back_when_sub_user_already_exists(): void
    {
        Role::firstOrCreate(['name' => 'owner']);

        config([
            'hostpinnacle.enabled' => true,
            'hostpinnacle.reseller_user_id' => 'reseller',
            'hostpinnacle.reseller_api_key' => 'reseller-key',
        ]);

        Http::fake([
            'smsportal.hostpinnacle.co.ke/SMSApi/reseller/createuser' => Http::response([
                'response' => ['status' => 'error', 'msg' => 'Userloginname already exist'],
            ], 200),
            'smsportal.hostpinnacle.co.ke/SMSApi/reseller/resetuserpassword' => Http::response([
                'response' => ['status' => 'success'],
            ], 200),
            'smsportal.hostpinnacle.co.ke/SMSApi/apikey/create' => Http::response([
                'response' => ['status' => 'success'],
            ], 200),
            'smsportal.hostpinnacle.co.ke/SMSApi/apikey/read' => Http::response([
                'response' => [
                    'status' => 'success',
                    'apikeyList' => ['apikey' => 'linked-api-key'],
                ],
            ], 200),
            'smsportal.hostpinnacle.co.ke/SMSApi/senderid/create' => Http::response([
                'response' => ['status' => 'success'],
            ], 200),
        ]);

        $owner = User::factory()->create();
        $owner->assignRole('owner');
        $company = Company::factory()->create([
            'user_id' => $owner->id,
            'subdomain' => 'acmeco',
        ]);

        $this->assertTrue(app(HostPinnacleProvisioner::class)->provision($company));
        $this->assertSame('linked-api-key', $company->fresh()->getConfig('HOSTPINNACLE_API_KEY'));
    }
}
