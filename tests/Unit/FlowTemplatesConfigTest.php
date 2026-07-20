<?php

namespace Tests\Unit;

use Tests\TestCase;

class FlowTemplatesConfigTest extends TestCase
{
    /** @var array<int, string> */
    private array $allowedNodeTypes = [
        'listing_inquiry',
        'book_appointment',
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
        'request_payment',
        'order_status',
        'catalog_search',
        'booking_events_list',
        'booking_event_register',
        'send_booking_link',
        'manage_booking',
        'manage_event_registration',
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
            'ai_faq_minimal',
            'services_listing_booking',
            'catalog_listings_showcase',
            'whatsapp_voice_ai_agent',
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

                if (isset($edge['sourceHandle']) && preg_match('/^kw\d+$/', (string) $edge['sourceHandle'])) {
                    $this->fail("Template {$key} uses legacy keyword handle [{$edge['sourceHandle']}]; expected keyword-{$edge['sourceHandle']}");
                }
            }
        }
    }

    public function test_payment_templates_use_request_payment_not_mpesa_stk_push(): void
    {
        foreach ([
            'whatsapp_shop_checkout',
            'microfinance_banking_bot',
            'hotel_tour_concierge_bot',
        ] as $key) {
            $nodes = collect(config("flow-templates.{$key}.flow_data.nodes"));
            $this->assertTrue(
                $nodes->contains(fn (array $node) => ($node['type'] ?? '') === 'request_payment'),
                "Template {$key} should include request_payment"
            );
            $this->assertFalse(
                $nodes->contains(fn (array $node) => ($node['type'] ?? '') === 'mpesa_stk_push'),
                "Template {$key} should not use mpesa_stk_push"
            );

            foreach (config("flow-templates.{$key}.flow_data.edges") as $edge) {
                $handle = (string) ($edge['sourceHandle'] ?? '');
                $this->assertNotContains($handle, ['mpesa-success', 'mpesa-failed'], "Template {$key} still uses legacy M-Pesa handles");
            }
        }
    }

    public function test_spa_template_uses_dynamic_booking_and_complete_recovery_paths(): void
    {
        $flowData = config('flow-templates.spa_wellness_booking.flow_data');
        $nodes = collect($flowData['nodes']);
        $edges = collect($flowData['edges']);

        $bookNode = $nodes->firstWhere('id', 'book_appointment-1');
        $this->assertNotNull($bookNode);
        $this->assertSame('', $bookNode['data']['settings']['source_name']);
        $this->assertSame('', $bookNode['data']['settings']['duration_minutes']);
        $this->assertTrue($bookNode['data']['settings']['allow_payment_retry']);
        $this->assertFalse($bookNode['data']['settings']['allow_pay_at_venue']);
        $this->assertLessThanOrEqual(60, mb_strlen((string) ($bookNode['data']['settings']['footer'] ?? '')));
        $this->assertTrue((bool) config('flow-templates.spa_wellness_booking.requires_setup_wizard'));

        $this->assertTrue($nodes->contains(fn (array $node) => ($node['type'] ?? '') === 'manage_booking'));
        $this->assertTrue($nodes->contains(fn (array $node) => ($node['id'] ?? '') === 'manage_booking-reschedule'));
        $this->assertTrue($nodes->contains(fn (array $node) => ($node['type'] ?? '') === 'send_booking_link'));
        $this->assertTrue($nodes->contains(fn (array $node) => ($node['id'] ?? '') === 'spa-faq-openai'));
        $this->assertFalse($nodes->contains(fn (array $node) => ($node['type'] ?? '') === 'request_payment'));

        $rescheduleNode = $nodes->firstWhere('id', 'manage_booking-reschedule');
        $this->assertNotNull($rescheduleNode);
        $this->assertTrue($rescheduleNode['data']['settings']['allow_reschedule']);
        $this->assertSame('reschedule', $rescheduleNode['data']['settings']['default_action']);

        $cancelNode = $nodes->firstWhere('id', 'manage_booking-1');
        $this->assertNotNull($cancelNode);
        $this->assertSame('cancel', $cancelNode['data']['settings']['default_action']);

        $menu = $nodes->firstWhere('id', 'list_message-1');
        $menuTitles = collect($menu['data']['settings']['sections'][0]['rows'] ?? [])
            ->pluck('title')
            ->all();
        $this->assertContains('Reschedule', $menuTitles);
        $this->assertContains('Cancel appointment', $menuTitles);

        foreach (['success', 'unavailable', 'error'] as $handle) {
            $this->assertTrue(
                $edges->contains(fn (array $edge) => ($edge['source'] ?? '') === 'book_appointment-1'
                    && ($edge['sourceHandle'] ?? '') === $handle),
                "Spa booking is missing [{$handle}] output."
            );
        }

        foreach (['rescheduled', 'not_found', 'error'] as $handle) {
            $this->assertTrue(
                $edges->contains(fn (array $edge) => ($edge['source'] ?? '') === 'manage_booking-reschedule'
                    && ($edge['sourceHandle'] ?? '') === $handle),
                "Spa reschedule is missing [{$handle}] output."
            );
        }

        foreach (['cancelled', 'not_found', 'error'] as $handle) {
            $this->assertTrue(
                $edges->contains(fn (array $edge) => ($edge['source'] ?? '') === 'manage_booking-1'
                    && ($edge['sourceHandle'] ?? '') === $handle),
                "Spa cancel is missing [{$handle}] output."
            );
        }

        $this->assertTrue(
            $edges->contains(fn (array $edge) => ($edge['source'] ?? '') === 'list_message-1'
                && ($edge['target'] ?? '') === 'manage_booking-reschedule'
                && ($edge['sourceHandle'] ?? '') === 'section1-row2')
        );
        $this->assertTrue(
            $edges->contains(fn (array $edge) => ($edge['source'] ?? '') === 'keyword_trigger-1'
                && ($edge['target'] ?? '') === 'manage_booking-reschedule'
                && ($edge['sourceHandle'] ?? '') === 'keyword-kw4')
        );
    }

    public function test_shop_checkout_includes_order_status_after_payment(): void
    {
        $flowData = config('flow-templates.whatsapp_shop_checkout.flow_data');
        $this->assertTrue(
            collect($flowData['nodes'])->contains(fn (array $node) => ($node['type'] ?? '') === 'order_status')
        );

        $paySuccess = collect($flowData['edges'])->first(
            fn (array $edge) => ($edge['source'] ?? '') === 'request_payment-1' && ($edge['sourceHandle'] ?? '') === 'success'
        );
        $this->assertNotNull($paySuccess);
        $this->assertSame('order_status-1', $paySuccess['target']);
    }

    public function test_listing_templates_default_to_interactive_list(): void
    {
        foreach ([
            'services_listing_booking' => 'listing_inquiry-1',
            'catalog_listings_showcase' => 'listing_inquiry-1',
            'real_estate_agency_bot' => 'listing_inquiry-1',
            'hotel_tour_concierge_bot' => 'listing_inquiry-2',
        ] as $key => $nodeId) {
            $node = collect(config("flow-templates.{$key}.flow_data.nodes"))->firstWhere('id', $nodeId);
            $this->assertNotNull($node, "Missing {$nodeId} in {$key}");
            $this->assertSame('interactive_list', $node['data']['settings']['displayMode'] ?? null, $key);
            $this->assertTrue((bool) ($node['data']['settings']['autoResumeFlow'] ?? false), $key);
        }
    }

    public function test_catalog_listings_showcase_requires_setup_wizard(): void
    {
        $this->assertTrue((bool) config('flow-templates.catalog_listings_showcase.requires_setup_wizard'));
    }
}
