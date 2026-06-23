<?php

namespace Tests\Unit;

use App\Services\Flowmaker\AiFlowAssistantService;
use Tests\TestCase;

class PlatformServicesTest extends TestCase
{
    public function test_ai_flow_assistant_builds_mpesa_flow_for_payment_description(): void
    {
        $draft = app(AiFlowAssistantService::class)->generate('When customer says pay, collect M-Pesa payment');

        $this->assertNotEmpty($draft['nodes']);
        $this->assertTrue(
            collect($draft['nodes'])->contains(fn (array $node) => $node['type'] === 'mpesa_stk_push')
        );
    }

    public function test_flow_templates_config_has_core_use_cases(): void
    {
        $templates = config('flow-templates');

        $this->assertArrayHasKey('lead_capture', $templates);
        $this->assertArrayHasKey('payment_collection', $templates);
        $this->assertArrayHasKey('spa_wellness_booking', $templates);
        $this->assertArrayHasKey('whatsapp_shop_checkout', $templates);
        $this->assertArrayHasKey('lead_intake_routing', $templates);
        $this->assertArrayHasKey('support_ai_escalation', $templates);
    }

    public function test_managed_ai_config_defines_tiers(): void
    {
        $this->assertArrayHasKey('pro', config('managed-ai.tiers'));
        $this->assertGreaterThan(0, config('managed-ai.tiers.pro.monthly_credits'));
    }
}
