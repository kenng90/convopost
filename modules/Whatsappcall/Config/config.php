<?php

return [
    'name' => 'Whatsappcall',
    'ai_worker_secret' => env('WHATSAPP_AI_WORKER_SECRET', ''),
    'ai_worker_url' => env('WHATSAPP_AI_WORKER_URL', ''),
    'laravel_callback_url' => env('WHATSAPP_AI_LARAVEL_CALLBACK_URL', env('APP_URL', 'http://127.0.0.1:8000')),
];
