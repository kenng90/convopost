<?php

return [

    'packs' => [
        'healthcare' => [
            'name' => 'Healthcare clinic',
            'flow_template' => 'healthcare_clinic_bot',
            'playbook' => 'booking_convert',
        ],
        'spa' => [
            'name' => 'Spa & wellness',
            'flow_template' => 'spa_wellness_booking',
            'playbook' => 'booking_convert',
        ],
        'shop' => [
            'name' => 'WhatsApp shop',
            'flow_template' => 'whatsapp_shop_checkout',
            'playbook' => 'cart_recovery',
        ],
        'sales' => [
            'name' => 'Lead to cash',
            'flow_template' => 'lead_intake_routing',
            'playbook' => 'lead_to_cash',
        ],
        'hotel' => [
            'name' => 'Hotel & tours',
            'flow_template' => 'hotel_tour_concierge_bot',
            'playbook' => null,
        ],
        'real_estate' => [
            'name' => 'Real estate',
            'flow_template' => 'real_estate_agency_bot',
            'playbook' => 'lead_to_cash',
        ],
        'automotive' => [
            'name' => 'Automotive dealer',
            'flow_template' => 'automotive_dealer_bot',
            'playbook' => 'lead_to_cash',
        ],
        'microfinance' => [
            'name' => 'Microfinance',
            'flow_template' => 'microfinance_banking_bot',
            'playbook' => 'lead_to_cash',
        ],
        'faq' => [
            'name' => 'AI FAQ',
            'flow_template' => 'ai_faq_minimal',
            'playbook' => null,
        ],
    ],

];
