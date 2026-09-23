<?php

return [
    'name' => 'Social',

    /*
    |--------------------------------------------------------------------------
    | Social publishing providers
    |--------------------------------------------------------------------------
    |
    | OAuth credentials fall back to existing FACEBOOK_* env keys where useful.
    | LinkedIn uses dedicated SOCIAL_LINKEDIN_* keys.
    |
    */
    'providers' => [
        'facebook' => [
            'enabled' => env('SOCIAL_FACEBOOK_ENABLED', true),
            'label' => 'Facebook Page',
            'publisher' => Modules\Social\Publishing\FacebookPublisher::class,
            'scopes' => [
                'pages_show_list',
                'pages_manage_posts',
                'pages_read_engagement',
                'business_management',
            ],
            'oauth' => [
                'client_id' => env('SOCIAL_FACEBOOK_CLIENT_ID', env('FACEBOOK_CLIENT_ID', env('FACEBOOK_APP_ID', ''))),
                'client_secret' => env('SOCIAL_FACEBOOK_CLIENT_SECRET', env('FACEBOOK_CLIENT_SECRET', env('FACEBOOK_APP_SECRET', ''))),
                'redirect' => env('SOCIAL_FACEBOOK_REDIRECT', env('FACEBOOK_REDIRECT', '')),
                'graph_version' => env('SOCIAL_FACEBOOK_GRAPH_VERSION', 'v21.0'),
            ],
        ],
        'instagram' => [
            'enabled' => env('SOCIAL_INSTAGRAM_ENABLED', true),
            'label' => 'Instagram',
            'publisher' => Modules\Social\Publishing\InstagramPublisher::class,
            'scopes' => [
                'instagram_basic',
                'instagram_content_publish',
                'pages_show_list',
                'pages_read_engagement',
                'business_management',
            ],
            'oauth' => [
                'client_id' => env('SOCIAL_INSTAGRAM_CLIENT_ID', env('FACEBOOK_CLIENT_ID', env('FACEBOOK_APP_ID', ''))),
                'client_secret' => env('SOCIAL_INSTAGRAM_CLIENT_SECRET', env('FACEBOOK_CLIENT_SECRET', env('FACEBOOK_APP_SECRET', ''))),
                'redirect' => env('SOCIAL_INSTAGRAM_REDIRECT', ''),
                'graph_version' => env('SOCIAL_FACEBOOK_GRAPH_VERSION', 'v21.0'),
            ],
        ],
        'linkedin' => [
            'enabled' => env('SOCIAL_LINKEDIN_ENABLED', true),
            'label' => 'LinkedIn',
            'publisher' => Modules\Social\Publishing\LinkedInPublisher::class,
            'scopes' => [
                'openid',
                'profile',
                'w_member_social',
                'r_organization_social',
                'w_organization_social',
            ],
            'oauth' => [
                'client_id' => env('SOCIAL_LINKEDIN_CLIENT_ID', ''),
                'client_secret' => env('SOCIAL_LINKEDIN_CLIENT_SECRET', ''),
                'redirect' => env('SOCIAL_LINKEDIN_REDIRECT', ''),
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Media library uploads
    |--------------------------------------------------------------------------
    */
    'media' => [
        'disk' => env('SOCIAL_MEDIA_DISK', 'public'),
        'directory' => env('SOCIAL_MEDIA_DIRECTORY', 'social/media'),
        'max_kilobytes' => (int) env('SOCIAL_MEDIA_MAX_KB', 51200), // 50 MB
        'allowed_mimes' => ['jpg', 'jpeg', 'png', 'gif', 'webp', 'mp4', 'mov', 'webm'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Managed AI captions / rewrites
    |--------------------------------------------------------------------------
    */
    'ai' => [
        'model' => env('SOCIAL_AI_MODEL', env('MANAGED_AI_FLOW_MODEL', 'openai/gpt-4o-mini')),
    ],
];
