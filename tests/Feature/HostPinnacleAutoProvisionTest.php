<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class HostPinnacleAutoProvisionTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_create_does_not_auto_provision_by_default(): void
    {
        config([
            'hostpinnacle.enabled' => true,
            'hostpinnacle.auto_provision_on_company_create' => false,
        ]);

        Http::fake();

        Company::factory()->create();

        Http::assertNothingSent();
    }

    public function test_company_create_auto_provisions_when_explicitly_enabled(): void
    {
        Role::firstOrCreate(['name' => 'owner']);

        config([
            'hostpinnacle.enabled' => true,
            'hostpinnacle.auto_provision_on_company_create' => true,
            'hostpinnacle.reseller_user_id' => 'reseller',
            'hostpinnacle.reseller_api_key' => 'reseller-key',
        ]);

        Http::fake([
            'smsportal.hostpinnacle.co.ke/*' => Http::response([
                'response' => ['status' => 'success'],
            ], 200),
        ]);

        $owner = User::factory()->create();
        $owner->assignRole('owner');

        Company::factory()->create([
            'user_id' => $owner->id,
            'subdomain' => 'acmeco',
            'phone' => '0712345678',
        ]);

        Http::assertSent(fn ($request) => str_contains($request->url(), '/SMSApi/reseller/createuser'));
    }
}
