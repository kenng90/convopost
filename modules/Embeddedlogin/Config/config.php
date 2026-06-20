<?php

return [
    'name' => 'Embeddedlogin',

    /** WhatsApp-only Embedded Signup v4 configuration ID (Meta App Dashboard). */
    'config_id' => env('EMBEDDED_FB_CONFIG_ID', ''),

    /**
     * Optional omnichannel v4 configuration ID (WhatsApp + Instagram + Messenger).
     * When empty, only WhatsApp-only signup is offered.
     */
    'omni_config_id' => env('EMBEDDED_FB_OMNI_CONFIG_ID', ''),

    /** Meta sessionInfoVersion for WA_EMBEDDED_SIGNUP FINISH payloads. */
    'session_info_version' => env('EMBEDDED_FB_SESSION_INFO_VERSION', '3'),

    /** Default UI selection: whatsapp_only | omnichannel */
    'default_flow' => env('EMBEDDED_FB_DEFAULT_FLOW', 'whatsapp_only'),

    'solution_id' => env('BS_PROVIDER_ID', ''),

    'graph_version' => env('EMBEDDED_FB_GRAPH_VERSION', 'v22.0'),
];
