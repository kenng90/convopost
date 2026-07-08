<?php

namespace Tests\Unit;

use App\Models\Company;
use App\Services\Telephony\Sms\SmsAvailability;
use App\Services\Telephony\Sms\SmsProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SmsAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_platform_host_pinnacle_takes_priority_over_twilio(): void
    {
        config(['hostpinnacle.enabled' => true]);

        $company = Company::factory()->create();
        $company->setMultipleConfig([
            'HOSTPINNACLE_USER_ID' => 'tenant1',
            'HOSTPINNACLE_API_KEY' => 'key',
            'HOSTPINNACLE_SENDER_ID' => 'ACME',
            'HOSTPINNACLE_SENDER_STATUS' => 'approved',
            'TWILIO_ACCOUNT_SID' => 'sid',
            'TWILIO_AUTH_TOKEN' => 'token',
            'TWILIO_FROM_NUMBER' => '+15551234567',
        ]);

        $availability = app(SmsAvailability::class);

        $this->assertSame(SmsProvider::HOSTPINNACLE, $availability->resolveProvider($company));
        $this->assertTrue($availability->isReady($company));
    }

    public function test_twilio_used_when_platform_sms_not_provisioned(): void
    {
        config(['hostpinnacle.enabled' => true]);

        $company = Company::factory()->create();
        $company->setMultipleConfig([
            'TWILIO_ACCOUNT_SID' => 'sid',
            'TWILIO_AUTH_TOKEN' => 'token',
            'TWILIO_FROM_NUMBER' => '+15551234567',
        ]);

        $availability = app(SmsAvailability::class);

        $this->assertSame(SmsProvider::TWILIO, $availability->resolveProvider($company));
        $this->assertTrue($availability->isReady($company));
    }

    public function test_pending_sender_id_blocks_platform_sms(): void
    {
        config([
            'hostpinnacle.enabled' => true,
            'hostpinnacle.require_approved_sender_id' => true,
        ]);

        $company = Company::factory()->create();
        $company->setMultipleConfig([
            'HOSTPINNACLE_USER_ID' => 'tenant1',
            'HOSTPINNACLE_API_KEY' => 'key',
            'HOSTPINNACLE_SENDER_ID' => 'ACME',
            'HOSTPINNACLE_SENDER_STATUS' => 'pending_approval',
        ]);

        $availability = app(SmsAvailability::class);

        $this->assertSame(SmsProvider::HOSTPINNACLE, $availability->resolveProvider($company));
        $this->assertFalse($availability->isReady($company));
        $this->assertStringContainsString('pending approval', $availability->statusMessage($company));
    }
}
