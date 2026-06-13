<?php

namespace Tests\Unit;

use App\Models\Company;
use App\Models\User;
use App\Services\Billing\CreditCharger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CreditChargerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'owner']);
        config(['settings.enable_credits' => true]);
    }

    public function test_service_window_replies_do_not_consume_credits(): void
    {
        $owner = User::factory()->create();
        $owner->assignRole('owner');
        $company = Company::factory()->create(['user_id' => $owner->id]);
        $owner->addCredits(10, 'test');

        $charger = app(CreditCharger::class);

        $this->assertTrue($charger->canCharge($company, 'send_service_window_reply'));
        $this->assertTrue($charger->charge($company, 'send_service_window_reply', $company->id));
        $this->assertSame(10, $owner->fresh()->getTotalRemainingCredits());
    }

    public function test_marketing_campaign_action_consumes_configured_credits(): void
    {
        $owner = User::factory()->create();
        $owner->assignRole('owner');
        $company = Company::factory()->create(['user_id' => $owner->id]);
        $owner->addCredits(10, 'test');

        $charger = app(CreditCharger::class);

        $this->assertTrue($charger->canCharge($company, 'send_campaign_marketing'));
        $this->assertTrue($charger->charge($company, 'send_campaign_marketing', $company->id));
        $this->assertSame(7, $owner->fresh()->getTotalRemainingCredits());
    }
}
