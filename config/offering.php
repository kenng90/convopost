<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Offering mode
    |--------------------------------------------------------------------------
    |
    | social_commerce — market Social + Store + Pay; WhatsApp CRM UI stays dormant
    |                   (code retained for post non-compete re-enable).
    | full            — unlock WhatsApp inbox, campaigns, calls, and related menus.
    |
    | Flip to "full" when counsel confirms the non-compete window has ended.
    | Operator checklist: OFFERING_MODE_FULL_RUNBOOK.md
    |
    */
    'mode' => env('OFFERING_MODE', 'social_commerce'),

    /*
    |--------------------------------------------------------------------------
    | Modules hidden from navigation when WhatsApp is dormant
    |--------------------------------------------------------------------------
    |
    | Entire module menus are omitted. Webhooks and classes remain loaded.
    |
    */
    'whatsapp_dormant_modules' => [
        'whatsappcall',
        'whatsappcallworker',
        'whatsappflows',
        'embedwhatsapp',
        'embeddedlogin',
        'instagram',
        'messenger',
        'tiktok',
        'smswpbox',
        'emailwpbox',
        'voicecall',
    ],

    /*
    |--------------------------------------------------------------------------
    | Route names blocked / hidden when WhatsApp is dormant
    |--------------------------------------------------------------------------
    |
    | Exact route names. Used for menu filtering and UI middleware.
    | Public webhooks are intentionally not listed.
    |
    */
    'whatsapp_dormant_routes' => [
        'chat.index',
        'campaigns.index',
        'campaigns.wizard',
        'campaigns.show',
        'campaigns.create',
        'campaigns.store',
        'campaigns.update',
        'campaigns.delete',
        'campaigns.clone',
        'campaigns.cancel',
        'campaigns.launch',
        'campaigns.report',
        'campaigns.pause',
        'campaigns.resume',
        'campaigns.integrations',
        'campaigns.segments.index',
        'wpbox.api.campaigns',
        'wpbox.api.create',
        'wpbox.api.store',
        'wpbox.api.edit',
        'wpbox.api.update',
        'wpbox.api.toggle',
        'wpbox.api.clone',
        'wpbox.api.index',
        'whatsapp.setup',
        'whatsapp.store',
        'templates.index',
        'templates.create',
        'templates.store',
        'templates.load',
        'templates.submit',
        'templates.destroy',
        'api.info',
        'whatsappcall.calls.index',
        'whatsappcall.settings',
        'whatsapp-flows.index',
        'whatsapp-flows.responses',
        'instagram.setup',
        'messenger.setup',
        'tiktok.setup',
        'embedwhatsapp.edit',
        'voicecall.index',
    ],

    /*
    |--------------------------------------------------------------------------
    | Default authenticated home when in social commerce mode
    |--------------------------------------------------------------------------
    */
    'social_home_route' => env('SOCIAL_HOME_ROUTE', 'social.calendar'),

];
