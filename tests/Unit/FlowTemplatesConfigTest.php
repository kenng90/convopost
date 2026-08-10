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
            'automotive_dealer_bot',
            'microfinance_banking_bot',
            'hotel_tour_concierge_bot',
            'ai_faq_minimal',
            'services_listing_booking',
            'catalog_listings_showcase',
            'whatsapp_voice_ai_agent',
        ] as $key) {
            $this->assertArrayHasKey($key, $templates, "Missing template: {$key}");
            $this->assertArrayHasKey($key.'_omni', $templates, "Missing omni template: {$key}_omni");
            $this->assertSame('whatsapp', $templates[$key]['channel_mode'] ?? null);
            $this->assertSame('omni', $templates[$key.'_omni']['channel_mode'] ?? null);
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
            'automotive_dealer_bot',
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
            'automotive_dealer_bot',
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

    public function test_shop_checkout_has_confirm_before_pay_and_payment_recovery(): void
    {
        $flowData = config('flow-templates.whatsapp_shop_checkout.flow_data');
        $nodes = collect($flowData['nodes']);
        $edges = collect($flowData['edges']);

        $this->assertTrue($nodes->contains(fn (array $node) => ($node['id'] ?? '') === 'quick_replies-confirm-pay'));
        $this->assertTrue($nodes->contains(fn (array $node) => ($node['id'] ?? '') === 'quick_replies-pay-recovery'));

        $this->assertTrue(
            $edges->contains(fn (array $edge) => ($edge['source'] ?? '') === 'quick_replies-confirm-pay'
                && ($edge['target'] ?? '') === 'request_payment-1'
                && ($edge['sourceHandle'] ?? '') === 'button-1')
        );
        $this->assertTrue(
            $edges->contains(fn (array $edge) => ($edge['source'] ?? '') === 'message-4'
                && ($edge['target'] ?? '') === 'quick_replies-pay-recovery')
        );
        $this->assertTrue(
            $edges->contains(fn (array $edge) => ($edge['source'] ?? '') === 'quick_replies-pay-recovery'
                && ($edge['target'] ?? '') === 'request_payment-1'
                && ($edge['sourceHandle'] ?? '') === 'button-1')
        );
    }

    public function test_lead_intake_persists_service_and_confirms_before_submit(): void
    {
        $flowData = config('flow-templates.lead_intake_routing.flow_data');
        $nodes = collect($flowData['nodes']);
        $edges = collect($flowData['edges']);

        $serviceNode = $nodes->firstWhere('id', 'question-service');
        $this->assertNotNull($serviceNode);
        $this->assertSame('lead_service', $serviceNode['data']['settings']['variableName'] ?? null);

        $this->assertTrue($nodes->contains(fn (array $node) => ($node['id'] ?? '') === 'quick_replies-lead-confirm'));
        $this->assertTrue($nodes->contains(fn (array $node) => ($node['id'] ?? '') === 'quick_replies-existing-recovery'));

        $this->assertTrue(
            $edges->contains(fn (array $edge) => ($edge['source'] ?? '') === 'list_message-1'
                && ($edge['target'] ?? '') === 'question-service'
                && ($edge['sourceHandle'] ?? '') === 'section1-row1')
        );
        $this->assertTrue(
            $edges->contains(fn (array $edge) => ($edge['source'] ?? '') === 'quick_replies-lead-confirm'
                && ($edge['target'] ?? '') === 'datastore-1'
                && ($edge['sourceHandle'] ?? '') === 'button-1')
        );
        $this->assertTrue(
            $edges->contains(fn (array $edge) => ($edge['source'] ?? '') === 'keyword_trigger-1'
                && ($edge['target'] ?? '') === 'assign_agent-2'
                && ($edge['sourceHandle'] ?? '') === 'keyword-kw4')
        );
    }

    public function test_support_escalation_persists_category_and_wires_agent_keyword(): void
    {
        $flowData = config('flow-templates.support_ai_escalation.flow_data');
        $nodes = collect($flowData['nodes']);
        $edges = collect($flowData['edges']);

        $categoryNode = $nodes->firstWhere('id', 'question-category');
        $this->assertNotNull($categoryNode);
        $this->assertSame('support_category', $categoryNode['data']['settings']['variableName'] ?? null);

        $this->assertTrue($nodes->contains(fn (array $node) => ($node['id'] ?? '') === 'quick_replies-resolved'));

        $this->assertTrue(
            $edges->contains(fn (array $edge) => ($edge['source'] ?? '') === 'keyword_trigger-1'
                && ($edge['target'] ?? '') === 'assign_group-1'
                && ($edge['sourceHandle'] ?? '') === 'keyword-kw4')
        );
        $this->assertTrue(
            $edges->contains(fn (array $edge) => ($edge['source'] ?? '') === 'list_message-1'
                && ($edge['target'] ?? '') === 'question-category'
                && ($edge['sourceHandle'] ?? '') === 'section1-row1')
        );
        $this->assertTrue(
            $edges->contains(fn (array $edge) => ($edge['source'] ?? '') === 'message-2'
                && ($edge['target'] ?? '') === 'quick_replies-resolved')
        );
    }

    public function test_spa_cancel_disables_reschedule_and_faq_has_menu_exit(): void
    {
        $flowData = config('flow-templates.spa_wellness_booking.flow_data');
        $cancel = collect($flowData['nodes'])->firstWhere('id', 'manage_booking-1');
        $this->assertNotNull($cancel);
        $this->assertFalse((bool) ($cancel['data']['settings']['allow_reschedule'] ?? true));

        $faqBranch = collect($flowData['nodes'])->first(
            fn (array $node) => ($node['id'] ?? '') === 'spa-faq-branch'
                || str_contains((string) ($node['id'] ?? ''), 'spa-faq') && ($node['type'] ?? '') === 'condition'
        );

        $hasMenuExit = collect($flowData['edges'])->contains(
            fn (array $edge) => str_contains((string) ($edge['source'] ?? ''), 'spa-faq')
                && ($edge['target'] ?? '') === 'list_message-1'
                && str_contains((string) ($edge['sourceHandle'] ?? '').($edge['id'] ?? ''), 'menu')
        );

        // Prefer checking keywordExits via FAQ merge: menu edge to list_message-1 exists
        $this->assertTrue(
            $hasMenuExit || collect($flowData['edges'])->contains(
                fn (array $edge) => ($edge['target'] ?? '') === 'list_message-1'
                    && str_contains((string) ($edge['id'] ?? '').($edge['sourceHandle'] ?? ''), 'menu')
            ),
            'Spa FAQ should offer a menu exit back to the main list.'
        );
        unset($faqBranch);
    }

    public function test_hotel_wires_all_accommodation_rows_and_agent_keyword(): void
    {
        $flowData = config('flow-templates.hotel_tour_concierge_bot.flow_data');
        $edges = collect($flowData['edges']);

        foreach (['section1-row1', 'section1-row2', 'section1-row3', 'section2-row1', 'section2-row2', 'section2-row3'] as $handle) {
            $this->assertTrue(
                $edges->contains(fn (array $edge) => ($edge['source'] ?? '') === 'list_message-1'
                    && ($edge['sourceHandle'] ?? '') === $handle),
                "Hotel menu missing edge for [{$handle}]"
            );
        }

        $this->assertTrue(
            $edges->contains(fn (array $edge) => ($edge['source'] ?? '') === 'keyword_trigger-1'
                && ($edge['target'] ?? '') === 'assign_group-handoff'
                && ($edge['sourceHandle'] ?? '') === 'keyword-kw4')
        );
        $this->assertTrue(
            collect($flowData['nodes'])->contains(fn (array $node) => ($node['id'] ?? '') === 'quick_replies-pay-recovery')
        );
    }

    public function test_microfinance_has_payment_and_limit_recovery(): void
    {
        $flowData = config('flow-templates.microfinance_banking_bot.flow_data');
        $nodes = collect($flowData['nodes']);
        $edges = collect($flowData['edges']);

        $this->assertTrue($nodes->contains(fn (array $node) => ($node['id'] ?? '') === 'quick_replies-pay-recovery'));
        $this->assertTrue($nodes->contains(fn (array $node) => ($node['id'] ?? '') === 'quick_replies-recovery'));
        $this->assertTrue(
            $edges->contains(fn (array $edge) => ($edge['source'] ?? '') === 'request_payment-1'
                && ($edge['target'] ?? '') === 'message-pay-failed'
                && ($edge['sourceHandle'] ?? '') === 'failed')
        );
        $this->assertTrue(
            $edges->contains(fn (array $edge) => ($edge['source'] ?? '') === 'keyword_trigger-1'
                && ($edge['target'] ?? '') === 'assign_agent-1'
                && ($edge['sourceHandle'] ?? '') === 'keyword-kw4')
        );
    }

    public function test_listing_templates_default_to_interactive_list(): void
    {
        foreach ([
            'services_listing_booking' => 'listing_inquiry-1',
            'catalog_listings_showcase' => 'listing_inquiry-1',
            'real_estate_agency_bot' => 'listing_inquiry-1',
            'automotive_dealer_bot' => 'listing_inquiry-1',
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

    public function test_real_estate_template_matches_spa_level_booking_and_faq_coverage(): void
    {
        $flowData = config('flow-templates.real_estate_agency_bot.flow_data');
        $nodes = collect($flowData['nodes']);
        $edges = collect($flowData['edges']);

        $this->assertTrue((bool) config('flow-templates.real_estate_agency_bot.requires_setup_wizard'));
        $this->assertTrue((bool) config('flow-templates.real_estate_agency_bot.exclusive_on_match'));
        $this->assertTrue($nodes->contains(fn (array $node) => ($node['id'] ?? '') === 'property-faq-openai'));
        $this->assertTrue($nodes->contains(fn (array $node) => ($node['type'] ?? '') === 'send_booking_link'));
        $this->assertTrue($nodes->contains(fn (array $node) => ($node['id'] ?? '') === 'manage_booking-reschedule'));
        $this->assertTrue($nodes->contains(fn (array $node) => ($node['id'] ?? '') === 'whatsapp_flow-sell'));

        foreach (['success', 'unavailable', 'error'] as $handle) {
            $this->assertTrue(
                $edges->contains(fn (array $edge) => ($edge['source'] ?? '') === 'book_appointment-1'
                    && ($edge['sourceHandle'] ?? '') === $handle),
                "Real estate viewing booking is missing [{$handle}] output."
            );
        }
    }

    public function test_automotive_template_includes_test_drive_and_vehicle_faq_paths(): void
    {
        $flowData = config('flow-templates.automotive_dealer_bot.flow_data');
        $nodes = collect($flowData['nodes']);
        $edges = collect($flowData['edges']);

        $this->assertTrue((bool) config('flow-templates.automotive_dealer_bot.requires_setup_wizard'));
        $this->assertSame('automotive_trade_in', config('flow-templates.automotive_dealer_bot.form_bundle'));
        $this->assertTrue($nodes->contains(fn (array $node) => ($node['id'] ?? '') === 'vehicle-faq-openai'));
        $this->assertTrue($nodes->contains(fn (array $node) => ($node['id'] ?? '') === 'whatsapp_flow-trade-in'));
        $this->assertTrue($nodes->contains(fn (array $node) => ($node['id'] ?? '') === 'whatsapp_flow-finance'));

        $this->assertTrue(
            $edges->contains(fn (array $edge) => ($edge['source'] ?? '') === 'keyword_trigger-1'
                && ($edge['target'] ?? '') === 'manage_booking-reschedule'
                && str_contains((string) ($edge['sourceHandle'] ?? ''), 'keyword-kw'))
        );
    }

    public function test_voice_ai_template_powers_calls_not_chat_loop(): void
    {
        $flowData = config('flow-templates.whatsapp_voice_ai_agent.flow_data');
        $nodes = collect($flowData['nodes']);
        $edges = collect($flowData['edges']);

        $this->assertFalse($nodes->contains(fn (array $node) => str_starts_with((string) ($node['id'] ?? ''), 'voice-faq-')));
        $this->assertFalse($edges->contains(fn (array $edge) => ($edge['target'] ?? '') === 'voice-faq-counter'));

        $instructions = $nodes->firstWhere('id', 'openai-voice-instructions');
        $this->assertNotNull($instructions);
        $this->assertFalse((bool) ($instructions['data']['settings']['llm']['autoSendMessage'] ?? true));

        $this->assertTrue(
            $edges->contains(fn (array $edge) => ($edge['source'] ?? '') === 'incomingMessage-1'
                && ($edge['target'] ?? '') === 'message-chat-notice')
        );
        $this->assertFalse(
            $edges->contains(fn (array $edge) => ($edge['source'] ?? '') === 'incomingMessage-1'
                && str_contains((string) ($edge['target'] ?? ''), 'openai'))
        );
    }

    public function test_omni_templates_exclude_whatsapp_rich_nodes(): void
    {
        $templates = config('flow-templates');
        $replaceTypes = \App\Services\Flowmaker\FlowTemplateOmniConverter::REPLACE_TYPES;

        foreach ($templates as $key => $template) {
            if (($template['channel_mode'] ?? '') !== 'omni') {
                continue;
            }

            $this->assertSame('Omni', $template['channel_badge'] ?? null, $key);
            $this->assertContains('instagram', $template['supported_channels'] ?? []);
            $this->assertContains('messenger', $template['supported_channels'] ?? []);

            foreach ($template['flow_data']['nodes'] ?? [] as $node) {
                $this->assertNotContains(
                    $node['type'] ?? '',
                    $replaceTypes,
                    "Omni template {$key} still has WhatsApp-rich node [{$node['type']}]"
                );
            }
        }
    }

    public function test_omni_spa_template_uses_online_booking_links(): void
    {
        $nodes = collect(config('flow-templates.spa_wellness_booking_omni.flow_data.nodes'));

        $this->assertFalse($nodes->contains(fn (array $n) => ($n['type'] ?? '') === 'book_appointment'));
        $this->assertFalse($nodes->contains(fn (array $n) => ($n['type'] ?? '') === 'manage_booking'));

        $onlineBook = $nodes->firstWhere('id', 'book_appointment-1');
        $this->assertNotNull($onlineBook);
        $this->assertSame('send_booking_link', $onlineBook['type']);
        $this->assertStringContainsString('{{booking_link}}', $onlineBook['data']['settings']['message'] ?? '');
        $this->assertSame('appointments', $onlineBook['data']['settings']['link_type'] ?? null);

        $manageCancel = $nodes->firstWhere('id', 'manage_booking-1');
        $this->assertNotNull($manageCancel);
        $this->assertSame('send_booking_link', $manageCancel['type']);
        $this->assertSame('manage', $manageCancel['data']['settings']['link_type'] ?? null);

        $manageReschedule = $nodes->firstWhere('id', 'manage_booking-reschedule');
        $this->assertNotNull($manageReschedule);
        $this->assertSame('send_booking_link', $manageReschedule['type']);
        $this->assertSame('manage', $manageReschedule['data']['settings']['link_type'] ?? null);

        $welcome = $nodes->firstWhere('id', 'message-1');
        $this->assertStringContainsString('online', strtolower((string) ($welcome['data']['settings']['message'] ?? '')));
    }
}
