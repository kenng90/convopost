<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Plan tier presets (used by PlanEntitlementsSeeder)
    |--------------------------------------------------------------------------
    |
    | Social-commerce-first copy. Inbox/campaigns capabilities are on Starter+
    | so OFFERING_MODE=full unlocks WhatsApp CRM UI without a separate plan
    | redesign. Pro includes messaging channel plugins for the same flip.
    |
    | limit_items    → campaigns per billing period (0 = unlimited)
    | limit_views    → outbound/inbound messages per period (0 = unlimited)
    | limit_orders   → contacts stored (0 = unlimited)
    | limit_catalog_items → catalog SKUs (0 = unlimited)
    | limit_agents       → staff agent seats per organization (0 = unlimited)
    | limit_companies    → organizations / brands per owner (0 = unlimited)
    | limit_integrations → connected store integrations per organization (0 = unlimited)
    | limit_social_accounts → connected social profiles (0 = unlimited)
    | limit_social_posts    → scheduled/published posts per period (0 = unlimited)
    | plugins        → module aliases allowed; null = all modules
    | capabilities   → feature flags; null = all capabilities
    |
    */
    'tiers' => [
        'starter' => [
            'name' => 'Starter',
            'price' => 29,
            'description' => 'Social publishing, contact CRM, and team inbox for solo sellers getting started.',
            'features' => 'Unganisha Social home, Up to 3 social accounts, Team inbox (WhatsApp when offering is full), Contact CRM, Up to 3 agents, 500 contacts',
            'limit_items' => 0,
            'limit_views' => 1000,
            'limit_orders' => 500,
            'limit_catalog_items' => 0,
            'limit_agents' => 3,
            'limit_companies' => 1,
            'limit_integrations' => 0,
            'limit_social_accounts' => 3,
            'limit_social_posts' => 30,
            'included_agent_seats' => 3,
            'agent_seat_price' => 0,
            'included_companies' => 1,
            'company_seat_price' => 0,
            'plugins' => [
                'social',
                'wpbox',
                'contacts',
                'agents',
            ],
            'managed_ai_monthly_credits' => 50,
            'capabilities' => [
                'social_publish',
                'contacts',
                'inbox',
            ],
        ],

        'growth' => [
            'name' => 'Growth',
            'price' => 79,
            'description' => 'Social publishing, catalog commerce, inbox, and campaigns for growing brands.',
            'features' => 'Everything in Starter, Product catalog & branded shop, Shopify/WooCommerce import, Campaigns & broadcasts, Collections & M-Pesa, 5 agents, 5,000 contacts',
            'limit_items' => 10,
            'limit_views' => 5000,
            'limit_orders' => 5000,
            'limit_catalog_items' => 100,
            'limit_agents' => 5,
            'limit_companies' => 3,
            'limit_integrations' => 1,
            'limit_social_accounts' => 10,
            'limit_social_posts' => 150,
            'included_agent_seats' => 3,
            'agent_seat_price' => 15,
            'included_companies' => 1,
            'company_seat_price' => 25,
            'plugins' => [
                'social',
                'wpbox',
                'contacts',
                'agents',
                'whatsappcatalog',
                'shopifylist',
                'woolist',
                'flowmaker',
            ],
            'managed_ai_monthly_credits' => 250,
            'capabilities' => [
                'social_publish',
                'contacts',
                'inbox',
                'campaigns',
                'flows',
                'catalog',
                'integrations',
                'collections',
                'payments',
            ],
        ],

        'pro' => [
            'name' => 'Pro',
            'price' => 149,
            'description' => 'Full social + messaging stack — inbox, campaigns, AI captions, journeys, bookings, and API access.',
            'features' => 'Everything in Growth, WhatsApp Flows & voice, Instagram/Messenger/TikTok inbox channels, Social AI captions, Social analytics, Journey playbooks, Bookings, Knowledge base, M-Pesa & Paystack, Public API, 15 agents',
            'limit_items' => 0,
            'limit_views' => 0,
            'limit_orders' => 0,
            'limit_catalog_items' => 0,
            'limit_agents' => 15,
            'limit_companies' => 0,
            'limit_integrations' => 0,
            'limit_social_accounts' => 25,
            'limit_social_posts' => 0,
            'included_agent_seats' => 10,
            'agent_seat_price' => 12,
            'included_companies' => 1,
            'company_seat_price' => 35,
            'plugins' => [
                'social',
                'wpbox',
                'contacts',
                'agents',
                'flowmaker',
                'whatsappcatalog',
                'shopifylist',
                'woolist',
                'journies',
                'reminders',
                'knowledge',
                'reports',
                // Messaging surfaces unlocked when OFFERING_MODE=full:
                'whatsappflows',
                'whatsappcall',
                'whatsappcallworker',
                'instagram',
                'messenger',
                'tiktok',
                'smswpbox',
                'emailwpbox',
                'voicecall',
                'embedwhatsapp',
                'embeddedlogin',
            ],
            'managed_ai_monthly_credits' => 1000,
            'capabilities' => [
                'social_publish',
                'social_analytics',
                'social_ai',
                'social_approvals',
                'contacts',
                'flows',
                'catalog',
                'integrations',
                'journeys',
                'payments',
                'collections',
                'reminders',
                'knowledge',
                'api_access',
                'ai_flow_assistant',
                'outcomes_cart_recovery',
                'outcomes_booking_convert',
                'outcomes_lead_to_cash',
                'outcomes_suite',
                'inbox',
                'campaigns',
                'whatsapp_flows',
                'voice',
                'inbox_instagram',
                'inbox_messenger',
                'inbox_tiktok',
            ],
        ],

        'agency' => [
            'name' => 'Agency',
            'price' => 299,
            'description' => 'Manage multiple client brands with full social + messaging CRM, unlimited social accounts, and API access.',
            'features' => 'Everything in Pro, Unlimited organizations & agents, Cross-client Social workspaces, Integration Hub, Full REST API, Priority support',            'limit_items' => 0,
            'limit_views' => 0,
            'limit_orders' => 0,
            'limit_catalog_items' => 0,
            'limit_agents' => 0,
            'limit_companies' => 0,
            'limit_integrations' => 0,
            'limit_social_accounts' => 0,
            'limit_social_posts' => 0,
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
        'social_posts' => 'Social posts this billing period',
    ],

    'resource_limit_labels' => [
        'agents' => 'Agent seats',
        'companies' => 'Organizations',
        'integrations' => 'Store integrations',
        'social_accounts' => 'Social accounts',
    ],

    /*
    | Capability flags stored on each plan (config key: capabilities).
    | Used by plan.capability middleware and API access checks.
    */
    'capability_labels' => [
        'social_publish' => 'Social publishing',
        'social_analytics' => 'Social analytics',
        'social_ai' => 'Social AI captions',
        'social_approvals' => 'Social post approvals',
        'inbox' => 'Team inbox',
        'contacts' => 'Contact CRM',
        'flows' => 'Workflow automation',
        'campaigns' => 'Campaigns & broadcasts',
        'catalog' => 'Product catalog',
        'integrations' => 'Store integrations',
        'whatsapp_flows' => 'WhatsApp Flows',
        'voice' => 'WhatsApp voice calling',
        'journeys' => 'Journey pipelines',
        'payments' => 'Payments',
        'collections' => 'Collections board',
        'reminders' => 'Bookings (appointments & events)',
        'knowledge' => 'Knowledge base',
        'api_access' => 'REST API access',
        'ai_flow_assistant' => 'AI Flow Assistant',
        'inbox_instagram' => 'Instagram DMs inbox',
        'inbox_messenger' => 'Facebook Messenger inbox',
        'inbox_tiktok' => 'TikTok DMs inbox',
        'outcomes_cart_recovery' => 'Cart Recovery playbook',
        'outcomes_booking_convert' => 'Booking Convert playbook',
        'outcomes_lead_to_cash' => 'Lead-to-Cash playbook',
        'outcomes_suite' => 'Commerce Ops Suite (all outcome playbooks)',
    ],

];
