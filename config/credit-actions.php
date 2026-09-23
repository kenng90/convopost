<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Core credit actions (Kenya GTM defaults)
    |--------------------------------------------------------------------------
    |
    | Modules may register additional actions via module.json "cost_per_action".
    | Admin overrides are stored in action_credit_cost.
    |
    | cost: -1 = usage-based (amount passed at charge time); >= 0 = fixed credits
    |
    */
    'actions' => [
        [
            'action' => 'send_service_window_reply',
            'name' => 'Inbox reply (24h service window)',
            'category' => 'messaging',
            'default_cost' => 0,
            'help' => 'Session replies while the customer service window is open. Meta service messages are free.',
            'module' => 'wpbox',
            'sort_order' => 10,
        ],
        [
            'action' => 'send_outside_window_reply',
            'name' => 'Inbox reply (outside service window)',
            'category' => 'messaging',
            'default_cost' => 1,
            'help' => 'Non-template outbound messages when the 24-hour window has expired.',
            'module' => 'wpbox',
            'sort_order' => 20,
        ],
        [
            'action' => 'send_campaign_marketing',
            'name' => 'Campaign / marketing template',
            'category' => 'campaigns',
            'default_cost' => 3,
            'help' => 'Marketing template broadcasts and campaigns (~Meta marketing rate).',
            'module' => 'wpbox',
            'sort_order' => 30,
        ],
        [
            'action' => 'send_template_utility',
            'name' => 'Utility template (booking, receipt)',
            'category' => 'campaigns',
            'default_cost' => 1,
            'help' => 'Utility or authentication template messages (~Meta utility rate).',
            'module' => 'wpbox',
            'sort_order' => 40,
        ],
        [
            'action' => 'send_sms_message',
            'name' => 'SMS campaign message',
            'category' => 'campaigns',
            'default_cost' => 2,
            'help' => 'Outbound SMS via ConvoConnect, Twilio, or Telnyx.',
            'module' => 'smswpbox',
            'sort_order' => 45,
        ],
        [
            'action' => 'send_email_message',
            'name' => 'Email campaign message',
            'category' => 'campaigns',
            'default_cost' => 1,
            'help' => 'Outbound email campaign message.',
            'module' => 'emailwpbox',
            'sort_order' => 46,
        ],
        [
            'action' => 'send_bot_auto_reply',
            'name' => 'Flow / bot auto-reply',
            'category' => 'automation',
            'default_cost' => 1,
            'help' => 'Automated trigger, flow, or bot outbound messages.',
            'module' => 'wpbox',
            'sort_order' => 50,
        ],
        [
            'action' => 'mpesa_stk_push',
            'name' => 'M-Pesa STK push',
            'category' => 'payments',
            'default_cost' => 5,
            'help' => 'Daraja STK push initiated from a flow payment node.',
            'module' => 'flowmaker',
            'sort_order' => 60,
        ],
        [
            'action' => 'ai_flow_generate',
            'name' => 'AI Flow Assistant — generate draft',
            'category' => 'managed_ai',
            'default_cost' => 5,
            'help' => 'Managed AI credits per flow draft when using the platform OpenRouter key.',
            'module' => 'flowmaker',
            'sort_order' => 70,
        ],
        [
            'action' => 'ai_llm_reply',
            'name' => 'Flow LLM node reply',
            'category' => 'managed_ai',
            'default_cost' => 1,
            'help' => 'Managed AI credits per LLM node invocation when using the platform OpenRouter key.',
            'module' => 'flowmaker',
            'sort_order' => 80,
        ],
        [
            'action' => 'ai_embedding',
            'name' => 'Knowledge base embedding chunk',
            'category' => 'managed_ai',
            'default_cost' => 1,
            'help' => 'Managed AI credits per embedding chunk when indexing knowledge base documents via the platform OpenRouter key.',
            'module' => 'flowmaker',
            'sort_order' => 90,
        ],
        [
            'action' => 'social_ai_caption',
            'name' => 'Social AI — generate caption',
            'category' => 'managed_ai',
            'default_cost' => 2,
            'help' => 'Managed AI credits per social caption generation when using the platform OpenRouter key.',
            'module' => 'social',
            'sort_order' => 92,
        ],
        [
            'action' => 'social_ai_rewrite',
            'name' => 'Social AI — rewrite caption',
            'category' => 'managed_ai',
            'default_cost' => 1,
            'help' => 'Managed AI credits per social caption rewrite when using the platform OpenRouter key.',
            'module' => 'social',
            'sort_order' => 93,
        ],
        [
            'action' => 'outcome_cart_recovered',
            'name' => 'Outcome SKU — recovered cart',
            'category' => 'outcomes',
            'default_cost' => 10,
            'help' => 'Success fee when an abandoned cart is recovered. Refunded if reversed within the guarantee window.',
            'module' => 'platform',
            'sort_order' => 100,
        ],
        [
            'action' => 'outcome_booking_attended',
            'name' => 'Outcome SKU — attended booking',
            'category' => 'outcomes',
            'default_cost' => 8,
            'help' => 'Success fee when a booking is marked attended. Refunded if it becomes a no-show inside the guarantee window.',
            'module' => 'platform',
            'sort_order' => 110,
        ],
        [
            'action' => 'outcome_lead_collected',
            'name' => 'Outcome SKU — collected invoice',
            'category' => 'outcomes',
            'default_cost' => 12,
            'help' => 'Success fee when a lead-to-cash invoice is paid.',
            'module' => 'platform',
            'sort_order' => 120,
        ],
    ],

    'category_labels' => [
        'messaging' => 'Messaging',
        'campaigns' => 'Campaigns & templates',
        'automation' => 'Automation',
        'payments' => 'Payments',
        'managed_ai' => 'Managed AI',
        'outcomes' => 'Outcome SKUs',
        'other' => 'Other',
    ],

    'service_window_hours' => 24,

];
