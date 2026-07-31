<?php

return [
    'graph_api_version' => env('WHATSAPP_FLOW_GRAPH_API_VERSION', 'v19.0'),
    'graph_api_base_url' => 'https://graph.facebook.com',

    'default_flow_message_version' => '3',
    'default_flow_cta' => 'Open Form',
    'default_header' => 'Complete the form',
    'default_footer' => 'Your responses help us serve you better',
    'default_first_screen_id' => 'WELCOME',

    'abandonment_timeout_hours' => (int) env('WHATSAPP_FLOW_ABANDONMENT_HOURS', 24),

    /** Max depth when flattening nested JSON response values into workflow variables. */
    'nested_variable_max_depth' => 3,

    /** When true, also write legacy whatsapp_flow_responses / form_* keys (last node wins). */
    'keep_legacy_variables' => true,
];
