<?php

namespace Tests\Unit;

use Tests\TestCase;

class FlowTemplatesConfigTest extends TestCase
{
    /** @var array<int, string> */
    private array $allowedNodeTypes = [
        'incomingMessage',
        'keyword_trigger',
        'whatsapp_flow',
        'message',
        'image',
        'pdf',
        'video',
        'template',
        'quick_replies',
        'list_message',
        'whatsapp_catalog',
        'branch',
        'counter',
        'check_pricing',
        'end',
        'openai',
        'question',
        'datastore',
        'http',
        'mpesa_stk_push',
        'assign_agent',
        'assign_group',
        'assign_journey_stage',
    ];

    public function test_comprehensive_business_templates_are_registered(): void
    {
        $templates = config('flow-templates');

        foreach ([
            'spa_wellness_booking',
            'whatsapp_shop_checkout',
            'lead_intake_routing',
            'support_ai_escalation',
            'healthcare_clinic_bot',
            'real_estate_agency_bot',
            'microfinance_banking_bot',
            'hotel_tour_concierge_bot',
        ] as $key) {
            $this->assertArrayHasKey($key, $templates, "Missing template: {$key}");
        }
    }

    public function test_comprehensive_templates_use_only_supported_node_types(): void
    {
        $keys = [
            'spa_wellness_booking',
            'whatsapp_shop_checkout',
            'lead_intake_routing',
            'support_ai_escalation',
            'healthcare_clinic_bot',
            'real_estate_agency_bot',
            'microfinance_banking_bot',
            'hotel_tour_concierge_bot',
        ];

        foreach ($keys as $key) {
            $nodes = config("flow-templates.{$key}.flow_data.nodes");
            $this->assertNotEmpty($nodes, "Template {$key} has no nodes");

            foreach ($nodes as $node) {
                $this->assertContains(
                    $node['type'],
                    $this->allowedNodeTypes,
                    "Unsupported node type [{$node['type']}] in template {$key}"
                );
            }
        }
    }

    public function test_comprehensive_templates_have_valid_graph_structure(): void
    {
        $keys = [
            'spa_wellness_booking',
            'whatsapp_shop_checkout',
            'lead_intake_routing',
            'support_ai_escalation',
            'healthcare_clinic_bot',
            'real_estate_agency_bot',
            'microfinance_banking_bot',
            'hotel_tour_concierge_bot',
        ];

        foreach ($keys as $key) {
            $flowData = config("flow-templates.{$key}.flow_data");
            $nodeIds = collect($flowData['nodes'])->pluck('id')->all();

            $this->assertNotEmpty($flowData['edges'], "Template {$key} has no edges");
            $this->assertContains('keyword_trigger-1', $nodeIds);
            $this->assertTrue(
                collect($nodeIds)->contains(fn (string $id) => str_starts_with($id, 'end-')),
                "Template {$key} is missing an end node"
            );

            foreach ($flowData['edges'] as $edge) {
                $this->assertContains($edge['source'], $nodeIds, "Invalid edge source in {$key}");
                $this->assertContains($edge['target'], $nodeIds, "Invalid edge target in {$key}");
            }
        }
    }
}
