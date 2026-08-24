<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Job-based owner sidebar navigation
    |--------------------------------------------------------------------------
    |
    | Modules still register ownermenus; this config assigns them to sections.
    | Synthetic groups are defined under "groups".
    |
    */

    'enabled' => env('OWNER_NAVIGATION_GROUPED', true),

    'sections' => [
        [
            'id' => 'inbox',
            'label' => 'Inbox',
        ],
        [
            'id' => 'audience',
            'label' => 'Audience',
        ],
        [
            'id' => 'outbound',
            'label' => 'Outbound',
        ],
        [
            'id' => 'automations',
            'label' => 'Automations & commerce',
        ],
        [
            'id' => 'insights',
            'label' => 'Insights',
        ],
        [
            'id' => 'setup',
            'label' => 'Setup',
        ],
        [
            'id' => 'addons',
            'label' => 'Add-ons',
        ],
    ],

    /*
     * Top-level module menu ids assigned to a section (whole group moves together).
     */
    'menu_ids' => [
        'contactMenu' => 'audience',
        'more' => 'setup',
        'bookingsMenu' => 'automations',
        'knowledgeMenu' => 'addons',
    ],

    /*
     * Single-link menus (by route name) assigned to a section.
     */
    'routes' => [
        'chat.index' => 'inbox',
        'whatsappcall.calls.index' => 'inbox',
        'orgmanager.index' => 'setup',

        'campaigns.index' => 'outbound',

        'flows.index' => 'automations',
        'catalogs.page' => 'automations',

        'embedwhatsapp.edit' => 'addons',
        'journies.index' => 'addons',
    ],

    /*
     * Synthetic collapsible groups injected into a section.
     */
    'groups' => [
        'whatsappFormsMenu' => [
            'section' => 'automations',
            'id' => 'whatsappFormsMenu',
            'name' => 'WhatsApp Forms',
            'icon' => 'ni ni-send text-purple',
            'route' => 'whatsapp-flows.index',
            'plugin' => 'whatsappflows',
            'menus' => [
                [
                    'name' => 'All forms',
                    'icon' => 'ni ni-send text-purple',
                    'route' => 'whatsapp-flows.index',
                ],
                [
                    'name' => 'Form submissions',
                    'icon' => 'ni ni-collection text-success',
                    'route' => 'whatsapp-flows.responses',
                ],
            ],
        ],
        'insightsMenu' => [
            'section' => 'insights',
            'id' => 'insightsMenu',
            'name' => 'Reports',
            'icon' => 'ni ni-chart-bar-32 text-primary',
            'route' => 'reports.dashboard',
            'menus' => [
                [
                    'name' => 'Overview',
                    'icon' => 'ni ni-chart-bar-32 text-primary',
                    'route' => 'reports.index',
                ],
                [
                    'name' => 'Transactions',
                    'icon' => 'ni ni-chart-pie-35 text-warning',
                    'route' => 'reports.dashboard',
                ],
                [
                    'name' => 'Collections',
                    'icon' => 'ni ni-money-coins text-success',
                    'route' => 'collections.index',
                ],
                // [
                //     'name' => 'Form submissions',
                //     'icon' => 'ni ni-collection text-success',
                //     'route' => 'whatsapp-flows.responses',
                //     'plugin' => 'whatsappflows',
                // ],
            ],
        ],
        'callsMenu' => [
            'section' => 'setup',
            'id' => 'callsMenu',
            'name' => 'WhatsApp calls',
            'icon' => 'ni ni-mobile-button text-green',
            'route' => 'whatsappcall.calls.index',
            'plugin' => 'whatsappcall',
            'menus' => [
                [
                    'name' => 'Call log',
                    'icon' => 'ni ni-mobile-button text-green',
                    'route' => 'whatsappcall.calls.index',
                ],
                [
                    'name' => 'Call settings',
                    'icon' => 'ni ni-settings text-green',
                    'route' => 'whatsappcall.settings',
                ],
            ],
        ],
        'workspaceMenu' => [
            'section' => 'setup',
            'id' => 'workspaceMenu',
            'name' => 'Workspace',
            'icon' => 'ni ni-shop text-primary',
            'route' => 'admin.companies.edit',
            'menus' => [], // filled dynamically
        ],
    ],

    /*
     * Routes consumed from modules but rendered via synthetic groups (skip duplicate).
     */
    'absorb_routes' => [
        'whatsapp-flows.index',
        'whatsapp-flows.responses',
        'reports.index',
        'whatsappcall.calls.index',
        'whatsappcall.settings',
    ],

];
