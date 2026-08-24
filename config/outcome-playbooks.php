<?php

/**
 * Outcome SKU playbooks: Cart Recovery, Booking Convert, Lead-to-Cash.
 *
 * Each playbook installs a journey pipeline and optionally a Flowmaker template.
 * Company config keys store installed journey/flow IDs for automation hooks.
 */
return [

    'suite_key' => 'commerce_ops_suite',

    'sku_billing' => [
        'guarantee_hours' => 48,
        'skus' => [
            'cart_recovery' => [
                'action' => 'outcome_cart_recovered',
                'event' => 'cart.recovered',
            ],
            'booking_convert' => [
                'action' => 'outcome_booking_attended',
                'event' => 'booking.attended',
            ],
            'lead_to_cash' => [
                'action' => 'outcome_lead_collected',
                'event' => 'invoice.paid',
            ],
        ],
    ],

    'credit_action_estimates' => [
        'send_campaign_marketing' => 3,
        'send_template_utility' => 1,
        'send_bot_auto_reply' => 1,
        'mpesa_stk_push' => 5,
    ],

    'playbooks' => [

        'cart_recovery' => [
            'name' => 'Cart Recovery',
            'sku' => 'cart_recovery',
            'tagline' => 'Recover abandoned carts on WhatsApp with catalog and pay-in-chat.',
            'description' => 'Enroll abandoned shoppers into a recovery journey, nudge with templates, and move them to Recovered or Lost.',
            'icon' => 'ni ni-cart',
            'color' => '#16a34a',
            'journey_template' => 'cart_recovery',
            'flow_template' => 'whatsapp_shop_checkout',
            'config_journey_key' => 'outcome_cart_recovery_journey_id',
            'config_flow_key' => 'outcome_cart_recovery_flow_id',
            'config_installed_key' => 'outcome_cart_recovery_installed',
            'entry_stage' => 'Abandoned',
            'success_stages' => ['Recovered', 'Paid'],
            'lost_stages' => ['Lost'],
            'estimated_credits_per_100' => [
                'marketing' => 300,
                'utility' => 100,
                'bot' => 50,
            ],
            'checklist' => [
                'Connect Shopify, WooCommerce, or use ConvoConnect catalog carts',
                'Approve marketing templates for recovery nudges',
                'Link catalog and M-Pesa/Paystack for checkout',
                'Review Recovered revenue on the Outcomes dashboard',
            ],
            'metrics' => ['abandoned', 'recovered', 'lost', 'recovered_revenue'],
        ],

        'booking_convert' => [
            'name' => 'Booking Convert',
            'sku' => 'booking_convert',
            'tagline' => 'Book → remind → cut no-shows → rebook on WhatsApp.',
            'description' => 'Pipeline for appointments: booked, reminded, attended, no-show recovery, and rebooked.',
            'icon' => 'ni ni-calendar-grid-58',
            'color' => '#0ea5e9',
            'journey_template' => 'booking_convert',
            'flow_template' => 'spa_wellness_booking',
            'config_journey_key' => 'outcome_booking_convert_journey_id',
            'config_flow_key' => 'outcome_booking_convert_flow_id',
            'config_installed_key' => 'outcome_booking_convert_installed',
            'entry_stage' => 'Booked',
            'success_stages' => ['Attended', 'Rebooked'],
            'lost_stages' => ['No-show'],
            'no_show_stage' => 'No-show',
            'attended_stage' => 'Attended',
            'estimated_credits_per_100' => [
                'marketing' => 60,
                'utility' => 200,
                'bot' => 40,
            ],
            'checklist' => [
                'Configure Reminders services, staff, and hours',
                'Install booking message template pack',
                'Optional: enable deposit/STK on paid services',
                'Schedule no-show processor (hourly)',
            ],
            'metrics' => ['booked', 'attended', 'no_shows', 'attendance_rate'],
        ],

        'lead_to_cash' => [
            'name' => 'Lead-to-Cash',
            'sku' => 'lead_to_cash',
            'tagline' => 'Capture → qualify → quote/invoice → paid in one thread.',
            'description' => 'Sales pipeline from new lead through proposal and payment on WhatsApp.',
            'icon' => 'ni ni-money-coins',
            'color' => '#7c3aed',
            'journey_template' => 'lead_to_cash',
            'flow_template' => 'lead_intake_routing',
            'config_journey_key' => 'outcome_lead_to_cash_journey_id',
            'config_flow_key' => 'outcome_lead_to_cash_flow_id',
            'config_installed_key' => 'outcome_lead_to_cash_installed',
            'entry_stage' => 'New Lead',
            'success_stages' => ['Paid', 'Won'],
            'lost_stages' => ['Lost'],
            'paid_stage' => 'Paid',
            'estimated_credits_per_100' => [
                'marketing' => 150,
                'utility' => 100,
                'bot' => 80,
            ],
            'checklist' => [
                'Publish lead WhatsApp Flow or keyword intake',
                'Configure invoice + M-Pesa/Paystack',
                'Train agents on proposal → pay handoff',
                'Paid invoices auto-move contacts to Paid stage',
            ],
            'metrics' => ['leads', 'proposals', 'paid', 'pipeline_paid_revenue'],
        ],
    ],
];
