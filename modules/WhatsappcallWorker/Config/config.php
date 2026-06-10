<?php

return [
    'port' => (int) env('WHATSAPP_AI_WORKER_PORT', 8787),
    'host' => env('WHATSAPP_AI_WORKER_HOST', '127.0.0.1'),
    'worker_path' => env('WHATSAPP_AI_WORKER_PATH', base_path('modules/WhatsappcallWorker/worker')),
    'default_url' => 'http://'.env('WHATSAPP_AI_WORKER_HOST', '127.0.0.1').':'.env('WHATSAPP_AI_WORKER_PORT', 8787),
];
