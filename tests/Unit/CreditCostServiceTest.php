<?php

namespace Tests\Unit;

use App\Services\Billing\CreditCostService;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class CreditCostServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        Cache::put('credit_action_costs', [], 300);
        config(['settings.enable_credits' => true]);
    }

    public function test_falls_back_to_registry_default_when_not_stored(): void
    {
        $service = app(CreditCostService::class);

        $this->assertSame(0.0, $service->getActionCost('send_service_window_reply'));
        $this->assertSame(1.0, $service->getActionCost('send_outside_window_reply'));
        $this->assertSame(3.0, $service->getActionCost('send_campaign_marketing'));
        $this->assertSame(5.0, $service->getActionCost('mpesa_stk_push'));
    }

    public function test_free_actions_return_zero_when_credits_disabled(): void
    {
        config(['settings.enable_credits' => false]);

        $service = app(CreditCostService::class);

        $this->assertSame(0.0, $service->getActionCost('send_campaign_marketing'));
    }
}
