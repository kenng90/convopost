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
        'automotive_dealer_bot',
        'microfinance_banking_bot',
        'hotel_tour_concierge_bot',
        'ai_faq_minimal',
        'services_listing_booking',
        'catalog_listings_showcase',
        'whatsapp_voice_ai_agent',
        'spa_wellness_booking_omni',
        'whatsapp_shop_checkout_omni',
        'lead_intake_routing_omni',
        'support_ai_escalation_omni',
        'healthcare_clinic_bot_omni',
        'real_estate_agency_bot_omni',
        'automotive_dealer_bot_omni',
        'microfinance_banking_bot_omni',
        'hotel_tour_concierge_bot_omni',
        'ai_faq_minimal_omni',
        'services_listing_booking_omni',
        'catalog_listings_showcase_omni',
        'whatsapp_voice_ai_agent_omni',
    ];

    public function test_all_templates_pass_health_validator_without_errors(): void
    {
        $validator = new FlowHealthValidator;

        foreach ($this->templateKeys as $key) {
            $flowData = config("flow-templates.{$key}.flow_data");
            $this->assertNotEmpty($flowData, "Missing template: {$key}");

            $options = ['template_mode' => true];
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

    public function test_spa_template_wires_booking_and_management_failure_outputs(): void
    {
        $result = (new FlowHealthValidator)->validate(
            config('flow-templates.spa_wellness_booking.flow_data'),
            ['template_mode' => true]
        );

        $warnings = implode(' ', $result['warnings']);

        $this->assertStringNotContainsString('Book appointment node [book_appointment-1] should wire', $warnings);
        $this->assertStringNotContainsString('Manage booking node [manage_booking-1] should wire', $warnings);
        $this->assertStringNotContainsString('Manage booking node [manage_booking-reschedule] should wire', $warnings);
    }
}
