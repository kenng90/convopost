<?php

return [

    'enabled' => env('MANAGED_AI_ENABLED', true),

    'default_monthly_credits' => (int) env('MANAGED_AI_DEFAULT_CREDITS', 0),

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
