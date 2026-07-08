<?php

return [
    'brand_name' => env('CONVOCONNECT_BRAND_NAME', 'ConvoConnect'),

    'enabled' => env('HOSTPINNACLE_ENABLED', false),

    'auto_provision_on_company_create' => env('HOSTPINNACLE_AUTO_PROVISION_ON_CREATE', false),

    'base_url' => env('HOSTPINNACLE_BASE_URL', 'https://smsportal.hostpinnacle.co.ke'),

    'reseller_user_id' => env('HOSTPINNACLE_RESELLER_USER_ID', ''),

    'reseller_password' => env('HOSTPINNACLE_RESELLER_PASSWORD', ''),

    'reseller_api_key' => env('HOSTPINNACLE_RESELLER_API_KEY', ''),

    'default_provider' => env('SMS_DEFAULT_PROVIDER', 'hostpinnacle'),

    'bulk_min_batch' => (int) env('HOSTPINNACLE_BULK_MIN_BATCH', 2),

    'sms_credits_per_messaging_credit' => (float) env('HOSTPINNACLE_SMS_CREDITS_RATIO', 1),

    'default_country_code' => env('HOSTPINNACLE_DEFAULT_COUNTRY_CODE', '254'),

    'default_country' => env('HOSTPINNACLE_DEFAULT_COUNTRY', 'Kenya'),

    'default_region' => env('HOSTPINNACLE_DEFAULT_REGION', 'Nairobi'),

    'default_city' => env('HOSTPINNACLE_DEFAULT_CITY', 'Nairobi'),

    'default_mobile' => env('HOSTPINNACLE_DEFAULT_MOBILE', '254700000000'),

    'sub_user_expiry_years' => (int) env('HOSTPINNACLE_SUB_USER_EXPIRY_YEARS', 5),

    'sub_user_type' => env('HOSTPINNACLE_SUB_USER_TYPE', 'customer'),

    'min_sms_balance' => (int) env('HOSTPINNACLE_MIN_SMS_BALANCE', 1),

    'require_approved_sender_id' => env('HOSTPINNACLE_REQUIRE_APPROVED_SENDER', true),
];
