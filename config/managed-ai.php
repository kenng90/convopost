<?php

return [

    'enabled' => env('MANAGED_AI_ENABLED', true),

    'default_monthly_credits' => (int) env('MANAGED_AI_DEFAULT_CREDITS', 0),

    'platform_openrouter_api_key' => env('MANAGED_AI_OPENROUTER_KEY', env('OPENROUTER_API_KEY')),

    'flow_generate_model' => env('MANAGED_AI_FLOW_MODEL', 'openai/gpt-4o-mini'),

    'embedding_model' => env('MANAGED_AI_EMBEDDING_MODEL', 'openai/text-embedding-3-small'),

    'llm_enabled' => env('MANAGED_AI_LLM_ENABLED', true),

    'fallback_to_rules' => env('MANAGED_AI_FALLBACK_TO_RULES', true),

    /** When true, rule-based fallback still deducts credits when using the platform key. */
    'charge_rules_fallback' => env('MANAGED_AI_CHARGE_RULES_FALLBACK', false),

    /*
    | Fallback tier credits when plan config managed_ai_monthly_credits is unset.
    */
    'tiers' => [
        'starter' => [
            'monthly_credits' => 50,
        ],
        'growth' => [
            'monthly_credits' => 250,
        ],
        'pro' => [
            'monthly_credits' => 1000,
        ],
        'agency' => [
            'monthly_credits' => 5000,
        ],
    ],

];
