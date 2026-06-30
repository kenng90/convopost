<?php

namespace Tests\Unit;

use App\Services\Flowmaker\FlowHealthValidator;
use Tests\TestCase;

class FlowTemplatesHealthTest extends TestCase
{
    /** @var array<int, string> */
    private array $templateKeys = [
        'spa_wellness_booking',
        'whatsapp_shop_checkout',
        'lead_intake_routing',
        'support_ai_escalation',
        'healthcare_clinic_bot',
        'real_estate_agency_bot',
        'microfinance_banking_bot',
        'hotel_tour_concierge_bot',
        'ai_faq_minimal',
        'services_listing_booking',
        'catalog_listings_showcase',
        'whatsapp_voice_ai_agent',
    ];

    public function test_all_templates_pass_health_validator_without_errors(): void
    {
        $validator = new FlowHealthValidator;

        foreach ($this->templateKeys as $key) {
            $flowData = config("flow-templates.{$key}.flow_data");
            $this->assertNotEmpty($flowData, "Missing template: {$key}");

            $options = [];
            if (! empty(config("flow-templates.{$key}.form_bundle"))) {
                $options['pending_form_bundle'] = true;
            }

            $result = $validator->validate($flowData, $options);
            $this->assertTrue(
                $result['valid'],
                "Template {$key} failed health check: ".implode('; ', $result['errors'])
            );
        }
    }

    public function test_all_llm_nodes_have_explicit_auto_send_flag(): void
    {
        foreach ($this->templateKeys as $key) {
            foreach (config("flow-templates.{$key}.flow_data.nodes") as $node) {
                if (($node['type'] ?? '') !== 'openai') {
                    continue;
                }

                $llm = $node['data']['settings']['llm'] ?? $node['data']['settings']['openai'] ?? [];
                $this->assertArrayHasKey(
                    'autoSendMessage',
                    $llm,
                    "LLM node in {$key} missing autoSendMessage"
                );
            }
        }
    }
}
