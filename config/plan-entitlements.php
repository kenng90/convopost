<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Plan tier presets (used by PlanEntitlementsSeeder)
    |--------------------------------------------------------------------------
    |
    | limit_items    → campaigns per billing period (0 = unlimited)
    | limit_views    → outbound/inbound messages per period (0 = unlimited)
    | limit_orders   → contacts stored (0 = unlimited)
    | limit_catalog_items → catalog SKUs (0 = unlimited)
    | limit_agents       → staff agent seats per organization (0 = unlimited)
    | limit_companies    → organizations / WhatsApp numbers per owner (0 = unlimited)
    | limit_integrations → connected store integrations per organization (0 = unlimited)
    | plugins        → module aliases allowed; null = all modules
    | capabilities   → feature flags; null = all capabilities
    |
    */
    'tiers' => [
        'starter' => [
            'name' => 'Starter',
            'price' => 29,
            'description' => 'Team inbox, contacts, and basic workflow automation for solo operators and small support teams.',
            'features' => 'Shared team inbox, Customer 360 sidebar, Up to 3 agents, 500 contacts, 1,000 messages/mo, Flow templates & visual builder, Contact CRM',
            'limit_items' => 0,
            'limit_views' => 1000,
            'limit_orders' => 500,
            'limit_catalog_items' => 0,
            'limit_agents' => 3,
            'limit_companies' => 1,
            'limit_integrations' => 0,
            'included_agent_seats' => 3,
            'agent_seat_price' => 0,
            'included_companies' => 1,
            'company_seat_price' => 0,
            'plugins' => [
                'wpbox',
                'contacts',
                'agents',
                'flowmaker',
            ],
            'managed_ai_monthly_credits' => 50,
            'capabilities' => [
                'inbox',
                'contacts',
                'flows',
                'inbox_instagram',
            ],
        ],

        'growth' => [
            'name' => 'Growth',
            'price' => 79,
            'description' => 'Outbound campaigns, catalog commerce, and store integrations for WhatsApp sales teams.',
            'features' => 'Everything in Starter, Agent Copilot, Campaigns & broadcasts, Product catalog & branded shop, Shopify/WooCommerce import, Chat widget, 5 agents, 5,000 contacts',
            'limit_items' => 10,
            'limit_views' => 5000,
            'limit_orders' => 5000,
            'limit_catalog_items' => 100,
            'limit_agents' => 5,
            'limit_companies' => 3,
            'limit_integrations' => 1,
            'included_agent_seats' => 3,
            'agent_seat_price' => 15,
            'included_companies' => 1,
            'company_seat_price' => 25,
            'plugins' => [
                'wpbox',
                'contacts',
                'agents',
                'flowmaker',
                'whatsappcatalog',
                'shopifylist',
                'woolist',
                'embedwhatsapp',
            ],
            'managed_ai_monthly_credits' => 250,
            'capabilities' => [
                'inbox',
                'contacts',
                'flows',
                'campaigns',
                'catalog',
                'integrations',
                'inbox_instagram',
            ],
        ],

        'pro' => [
            'name' => 'Pro',
            'price' => 149,
            'description' => 'Full operations stack — WhatsApp Flows, voice, journeys, payments, and API access.',
            'features' => 'Everything in Growth, WhatsApp Flows, AI Flow Assistant, Journey playbooks, Revenue dashboard, M-Pesa & Stripe, Public invoices, 15 agents, API access',
            'limit_items' => 0,
            'limit_views' => 0,
            'limit_orders' => 0,
            'limit_catalog_items' => 0,
            'limit_agents' => 15,
            'limit_companies' => 0,
            'limit_integrations' => 0,
            'included_agent_seats' => 10,
            'agent_seat_price' => 12,
            'included_companies' => 1,
            'company_seat_price' => 35,
            'plugins' => [
                'wpbox',
                'contacts',
                'agents',
                'flowmaker',
                'whatsappcatalog',
                'shopifylist',
                'woolist',
                'embedwhatsapp',
                'whatsappflows',
                'whatsappcall',
                'journies',
                'reminders',
                'knowledge',
                'reports',
            ],
            'managed_ai_monthly_credits' => 1000,
            'capabilities' => [
                'inbox',
                'contacts',
                'flows',
                'campaigns',
                'catalog',
                'integrations',
                'whatsapp_flows',
                'voice',
                'journeys',
                'payments',
                'reminders',
                'knowledge',
                'api_access',
                'ai_flow_assistant',
                'inbox_instagram',
                'inbox_messenger',
                'outcomes_cart_recovery',
                'outcomes_booking_convert',
                'outcomes_lead_to_cash',
                'outcomes_suite',
            ],
        ],

        'agency' => [
            'name' => 'Agency',
            'price' => 299,
            'description' => 'Manage multiple client WhatsApp numbers, unlimited usage, and full API access from one account.',
            'features' => 'Everything in Pro, Health Monitor, Integration Hub, Unlimited agents & numbers, Cross-client operations, Full REST API, Priority support',
            'limit_items' => 0,
            'limit_views' => 0,
            'limit_orders' => 0,
            'limit_catalog_items' => 0,
            'limit_agents' => 0,
            'limit_companies' => 0,
            'limit_integrations' => 0,
            'included_agent_seats' => 0,
            'agent_seat_price' => 0,
            'included_companies' => 0,
            'company_seat_price' => 0,
            'plugins' => null,
            'managed_ai_monthly_credits' => 5000,
            'capabilities' => null,
        ],
    ],

    /*
    | Human-readable labels for usage meters on the billing page.
    */
    'limit_labels' => [
        'campaigns' => 'Campaigns this billing period',
        'messages' => 'Messages this billing period',
        'contacts' => 'Contacts stored',
    ],

    'resource_limit_labels' => [
        'agents' => 'Agent seats',
        'companies' => 'Organizations',
        'integrations' => 'Store integrations',
    ],

    /*
    | Capability flags stored on each plan (config key: capabilities).
    | Used by plan.capability middleware and API access checks.
    */
    'capability_labels' => [
        'inbox' => 'Team inbox',
        'contacts' => 'Contact CRM',
        'flows' => 'Workflow automation',
        'campaigns' => 'Campaigns & broadcasts',
        'catalog' => 'Product catalog',
        'integrations' => 'Store integrations',
        'whatsapp_flows' => 'WhatsApp Flows',
        'voice' => 'WhatsApp voice calling',
        'journeys' => 'Journey pipelines',
        'payments' => 'In-chat payments',
        'reminders' => 'Bookings (appointments & events)',
        'knowledge' => 'Knowledge base',
        'api_access' => 'REST API access',
        'ai_flow_assistant' => 'AI Flow Assistant',
        'inbox_instagram' => 'Instagram DMs inbox',
        'inbox_messenger' => 'Facebook Messenger inbox',
        'outcomes_cart_recovery' => 'Cart Recovery playbook',
        'outcomes_booking_convert' => 'Booking Convert playbook',
        'outcomes_lead_to_cash' => 'Lead-to-Cash playbook',
        'outcomes_suite' => 'Commerce Ops Suite (all outcome playbooks)',
    ],

];
