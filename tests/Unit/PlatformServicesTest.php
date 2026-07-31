<?php

namespace Tests\Unit;

use App\Services\Flowmaker\AiFlowAssistantService;
use Tests\TestCase;

class PlatformServicesTest extends TestCase
{
    public function test_ai_flow_assistant_builds_payment_flow_for_payment_description(): void
    {
        $draft = app(AiFlowAssistantService::class)->generateRuleBased('When customer says pay, collect M-Pesa payment');

        $this->assertNotEmpty($draft['nodes']);
        $this->assertTrue(
            collect($draft['nodes'])->contains(fn (array $node) => $node['type'] === 'request_payment')
        );
    }

    public function test_ai_flow_assistant_builds_shop_flow_for_catalog_description(): void
    {
        $draft = app(AiFlowAssistantService::class)->generateRuleBased('I want a WhatsApp shop catalog checkout flow');

        $this->assertTrue(
            collect($draft['nodes'])->contains(fn (array $node) => $node['type'] === 'whatsapp_catalog')
        );
        $this->assertTrue(
            collect($draft['nodes'])->contains(fn (array $node) => $node['type'] === 'request_payment')
        );
    }

    public function test_flow_templates_config_has_core_use_cases(): void
    {
        $templates = config('flow-templates');

        $this->assertArrayHasKey('spa_wellness_booking', $templates);
        $this->assertArrayHasKey('whatsapp_shop_checkout', $templates);
        $this->assertArrayHasKey('lead_intake_routing', $templates);
        $this->assertArrayHasKey('support_ai_escalation', $templates);
        $this->assertArrayHasKey('healthcare_clinic_bot', $templates);
        $this->assertArrayHasKey('real_estate_agency_bot', $templates);
        $this->assertArrayHasKey('automotive_dealer_bot', $templates);
        $this->assertArrayHasKey('microfinance_banking_bot', $templates);
        $this->assertArrayHasKey('hotel_tour_concierge_bot', $templates);
    }

    public function test_managed_ai_credit_actions_are_registered(): void
    {
        $actions = collect(config('credit-actions.actions'))->pluck('action');

        $this->assertTrue($actions->contains('ai_flow_generate'));
        $this->assertTrue($actions->contains('ai_llm_reply'));
        $this->assertTrue($actions->contains('ai_embedding'));
    }

    public function test_pro_tier_includes_ai_flow_assistant_capability(): void
    {
        $capabilities = config('plan-entitlements.tiers.pro.capabilities');

        $this->assertContains('ai_flow_assistant', $capabilities);
    }

    public function test_starter_tier_excludes_ai_flow_assistant_capability(): void
    {
        $capabilities = config('plan-entitlements.tiers.starter.capabilities');

        $this->assertNotContains('ai_flow_assistant', $capabilities);
    }

    public function test_usage_limit_labels_reference_billing_period(): void
    {
        $labels = config('plan-entitlements.limit_labels');

        $this->assertStringContainsString('billing period', $labels['campaigns']);
        $this->assertStringContainsString('billing period', $labels['messages']);
    }
}
