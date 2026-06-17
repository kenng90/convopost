<?php

return [
    'name' => 'Platform',

    'integrations' => [
        'google_sheets' => [
            'name' => 'Google Sheets',
            'description' => 'Export contacts and campaign results to a spreadsheet.',
            'icon' => 'ni ni-books',
            'fields' => ['api_key', 'webhook_url'],
        ],
        'hubspot' => [
            'name' => 'HubSpot CRM',
            'description' => 'Sync WhatsApp contacts and deal stages with HubSpot.',
            'icon' => 'ni ni-briefcase-24',
            'fields' => ['api_key'],
        ],
        'zapier' => [
            'name' => 'Zapier Webhook',
            'description' => 'Trigger Zaps on new messages, payments, and journey moves.',
            'icon' => 'ni ni-send',
            'fields' => ['webhook_url'],
        ],
        'quickbooks' => [
            'name' => 'QuickBooks',
            'description' => 'Push paid invoices to your accounting ledger.',
            'icon' => 'ni ni-money-coins',
            'fields' => ['api_key'],
        ],
    ],
];
