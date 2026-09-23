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
        'tiktok' => [
            'enabled' => env('SOCIAL_TIKTOK_ENABLED', true),
            'label' => 'TikTok',
            'publisher' => Modules\Social\Publishing\TikTokPublisher::class,
            'scopes' => [
                'user.info.basic',
                'video.upload',
                'video.publish',
            ],
            'oauth' => [
                'client_key' => env('SOCIAL_TIKTOK_CLIENT_KEY', env('TIKTOK_APP_ID', '')),
                'client_secret' => env('SOCIAL_TIKTOK_CLIENT_SECRET', env('TIKTOK_APP_SECRET', '')),
                'redirect' => env('SOCIAL_TIKTOK_REDIRECT', ''),
                'authorize_url' => env('SOCIAL_TIKTOK_AUTHORIZE_URL', 'https://www.tiktok.com/v2/auth/authorize/'),
                'token_url' => env('SOCIAL_TIKTOK_TOKEN_URL', 'https://open.tiktokapis.com/v2/oauth/token/'),
                'api_base' => env('SOCIAL_TIKTOK_API_BASE', 'https://open.tiktokapis.com'),
            ],
        ],
        'youtube' => [
            'enabled' => env('SOCIAL_YOUTUBE_ENABLED', true),
            'label' => 'YouTube',
            'publisher' => Modules\Social\Publishing\YouTubePublisher::class,
            'scopes' => [
                'https://www.googleapis.com/auth/youtube.upload',
                'https://www.googleapis.com/auth/youtube.readonly',
            ],
            'oauth' => [
                'client_id' => env('SOCIAL_YOUTUBE_CLIENT_ID', env('GOOGLE_CLIENT_ID', '')),
                'client_secret' => env('SOCIAL_YOUTUBE_CLIENT_SECRET', env('GOOGLE_CLIENT_SECRET', '')),
                'redirect' => env('SOCIAL_YOUTUBE_REDIRECT', ''),
                'authorize_url' => env('SOCIAL_YOUTUBE_AUTHORIZE_URL', 'https://accounts.google.com/o/oauth2/v2/auth'),
                'token_url' => env('SOCIAL_YOUTUBE_TOKEN_URL', 'https://oauth2.googleapis.com/token'),
                'api_base' => env('SOCIAL_YOUTUBE_API_BASE', 'https://www.googleapis.com'),
            ],
        ],
        'threads' => [
            'enabled' => env('SOCIAL_THREADS_ENABLED', true),
            'label' => 'Threads',
            'publisher' => Modules\Social\Publishing\ThreadsPublisher::class,
            'scopes' => [
                'threads_basic',
                'threads_content_publish',
            ],
            'oauth' => [
                'client_id' => env('SOCIAL_THREADS_CLIENT_ID', env('FACEBOOK_APP_ID', env('FACEBOOK_CLIENT_ID', ''))),
                'client_secret' => env('SOCIAL_THREADS_CLIENT_SECRET', env('FACEBOOK_APP_SECRET', env('FACEBOOK_CLIENT_SECRET', ''))),
                'redirect' => env('SOCIAL_THREADS_REDIRECT', ''),
                'authorize_url' => env('SOCIAL_THREADS_AUTHORIZE_URL', 'https://threads.net/oauth/authorize'),
                'api_base' => env('SOCIAL_THREADS_API_BASE', 'https://graph.threads.net'),
                'graph_version' => env('SOCIAL_THREADS_GRAPH_VERSION', 'v1.0'),
            ],
        ],
        'pinterest' => [
            'enabled' => env('SOCIAL_PINTEREST_ENABLED', true),
            'label' => 'Pinterest',
            'publisher' => Modules\Social\Publishing\PinterestPublisher::class,
            'scopes' => [
                'boards:read',
                'boards:write',
                'pins:read',
                'pins:write',
                'user_accounts:read',
            ],
            'oauth' => [
                'client_id' => env('SOCIAL_PINTEREST_CLIENT_ID', ''),
                'client_secret' => env('SOCIAL_PINTEREST_CLIENT_SECRET', ''),
                'redirect' => env('SOCIAL_PINTEREST_REDIRECT', ''),
                'authorize_url' => env('SOCIAL_PINTEREST_AUTHORIZE_URL', 'https://www.pinterest.com/oauth/'),
                'token_url' => env('SOCIAL_PINTEREST_TOKEN_URL', 'https://api.pinterest.com/v5/oauth/token'),
                'api_base' => env('SOCIAL_PINTEREST_API_BASE', 'https://api.pinterest.com'),
            ],
        ],
        'gbp' => [
            'enabled' => env('SOCIAL_GBP_ENABLED', true),
            'label' => 'Google Business Profile',
            'publisher' => Modules\Social\Publishing\GbpPublisher::class,
            'scopes' => [
                'https://www.googleapis.com/auth/business.manage',
            ],
            'oauth' => [
                'client_id' => env('SOCIAL_GBP_CLIENT_ID', env('GOOGLE_CLIENT_ID', '')),
                'client_secret' => env('SOCIAL_GBP_CLIENT_SECRET', env('GOOGLE_CLIENT_SECRET', '')),
                'redirect' => env('SOCIAL_GBP_REDIRECT', ''),
                'authorize_url' => env('SOCIAL_GBP_AUTHORIZE_URL', 'https://accounts.google.com/o/oauth2/v2/auth'),
                'token_url' => env('SOCIAL_GBP_TOKEN_URL', 'https://oauth2.googleapis.com/token'),
                'account_management_base' => env('SOCIAL_GBP_ACCOUNT_BASE', 'https://mybusinessaccountmanagement.googleapis.com'),
                'business_info_base' => env('SOCIAL_GBP_INFO_BASE', 'https://mybusinessbusinessinformation.googleapis.com'),
                'local_posts_base' => env('SOCIAL_GBP_POSTS_BASE', 'https://mybusiness.googleapis.com'),
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
