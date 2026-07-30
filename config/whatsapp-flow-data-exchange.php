<?php

/**
 * WhatsApp Flow data_exchange handler configuration.
 *
 * Screens reference handlers via endpoint_template (in flow JSON or Meta import).
 * ConfigurableDataExchangeHandler serves keys listed under http_handlers below.
 *
 * Expected HTTP response shape:
 *   { "screen": "NEXT_SCREEN_ID", "data": { "field_key": "value", "options": [...] } }
 *
 * @return array<string, array<string, mixed>>
 */
return [
    'bundle_screen_defaults' => [
        // Map form_bundle_key + screen id when the screen has no endpoint_template.
        // 'appointment_booking' => [
        //     'PICK_SERVICE' => 'booking_catalog',
        // ],
    ],

    /**
     * Custom handlers keyed by endpoint_template.
     *
     * Types:
     *   - http  : proxy to an external or internal URL (no PHP class required)
     *   - class : delegate to a WhatsappFlowDataExchangeHandler implementation
     */
    'http_handlers' => [

        /*
        |--------------------------------------------------------------------------
        | Sample: HTTP webhook (copy, uncomment, set your URL)
        |--------------------------------------------------------------------------
        |
        | In your WhatsApp Form, set the screen endpoint_template to: custom_catalog
        |
        | Your endpoint receives POST JSON:
        |   {
        |     "flow_id": 12,
        |     "meta_flow_id": "123456789",
        |     "screen_id": "PICK_ITEM",
        |     "endpoint_template": "custom_catalog",
        |     "data": { "category": "electronics" },
        |     "flow_token": "flow_1_1700000000",
        |     "company_id": 3
        |   }
        |
        | Respond with 200 JSON (field types must match the target screen Meta schema):
        |   {
        |     "screen": "PICK_ITEM",
        |     "data": {
        |       "product_options": [
        |         { "id": "sku-1", "title": "Widget A" },
        |         { "id": "sku-2", "title": "Widget B" }
        |       ]
        |     }
        |   }
        |
        | init_data is merged into INIT for the first open of that screen.
        */
        // 'custom_catalog' => [
        //     'type' => 'http',
        //     'url' => env('APP_URL').'/api/whatsapp-flows/data-exchange/custom-catalog',
        //     'method' => 'POST',
        //     'timeout' => 15,
        //     'headers' => [
        //         'Accept' => 'application/json',
        //     ],
        //     'init_data' => [
        //         'category_options' => [
        //             ['id' => 'electronics', 'title' => 'Electronics'],
        //             ['id' => 'services', 'title' => 'Services'],
        //         ],
        //     ],
        // ],

        /*
        |--------------------------------------------------------------------------
        | Sample: delegate to a PHP handler class
        |--------------------------------------------------------------------------
        |
        | Use when logic lives in app code but you want routing defined in config.
        | Set screen endpoint_template to: my_loan_summary
        |
        | The handler class must implement App\Contracts\WhatsappFlowDataExchangeHandler
        | and be resolvable from the container (constructor dependencies are injected).
        */
        // 'my_loan_summary' => [
        //     'type' => 'class',
        //     'handler' => \App\Services\WhatsappFlows\DynamicOptionsDataExchangeHandler::class,
        // ],

    ],
];
