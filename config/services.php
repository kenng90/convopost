<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT'),
        'calendar_redirect' => env('GOOGLE_CALENDAR_REDIRECT'),
    ],

    'facebook' => [
        'client_id' => env('FACEBOOK_CLIENT_ID'),
        'client_secret' => env('FACEBOOK_CLIENT_SECRET'),
        'redirect' => env('FACEBOOK_REDIRECT'),
        'app_id' => env('FACEBOOK_APP_ID', ''),
        'app_secret' => env('FACEBOOK_APP_SECRET', ''),
        'config_id' => env('FACEBOOK_CONFIG_ID', ''),

    ],

    'whatsapp' => [
        'app_secret' => env('WHATSAPP_APP_SECRET', env('FACEBOOK_APP_SECRET', '')),
    ],

    'tiktok' => [
        'base_url' => env('TIKTOK_BUSINESS_API_BASE', 'https://business-api.tiktok.com/open_api/v1.3'),
        'app_id' => env('TIKTOK_APP_ID', ''),
        'app_secret' => env('TIKTOK_APP_SECRET', ''),
        'webhook_token' => env('TIKTOK_WEBHOOK_TOKEN', ''),
    ],

    'woocommerce_demo' => [
        'store_url' => env('WOOCOMMERCE_DEMO_STORE_URL', ''),
        'consumer_key' => env('WOOCOMMERCE_DEMO_CONSUMER_KEY', ''),
        'consumer_secret' => env('WOOCOMMERCE_DEMO_CONSUMER_SECRET', ''),
    ],

];
