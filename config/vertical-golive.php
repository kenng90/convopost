<?php

return [

    'packs' => [
        'healthcare' => [
            'name' => 'Healthcare clinic',
            'flow_template' => 'healthcare_clinic_bot',
            'playbook' => 'booking_convert',
            'catalog_mode' => 'service',
            'catalog_vertical' => 'general_service',
            'catalog_name' => 'Clinic services',
            'catalog_items' => [
                ['id' => 'general-consult', 'title' => 'General consultation', 'price' => 2500, 'duration_minutes' => 30],
                ['id' => 'follow-up', 'title' => 'Follow-up visit', 'price' => 1500, 'duration_minutes' => 20],
                ['id' => 'lab-review', 'title' => 'Lab results review', 'price' => 1000, 'duration_minutes' => 15],
            ],
            'booking_services' => [
                ['name' => 'General consultation', 'default_duration_minutes' => 30],
                ['name' => 'Follow-up visit', 'default_duration_minutes' => 20],
                ['name' => 'Lab results review', 'default_duration_minutes' => 15],
            ],
            'install_booking_templates' => true,
            'welcome_message' => 'Welcome to :company. Reply BOOK to schedule an appointment, or tell us what you need.',
        ],
        'spa' => [
            'name' => 'Spa & wellness',
            'flow_template' => 'spa_wellness_booking',
            'playbook' => 'booking_convert',
            'catalog_mode' => 'service',
            'catalog_vertical' => 'general_service',
            'catalog_name' => 'Spa treatments',
            'catalog_items' => [
                ['id' => 'swedish-massage', 'title' => 'Swedish massage', 'price' => 4500, 'duration_minutes' => 60],
                ['id' => 'facial', 'title' => 'Signature facial', 'price' => 3500, 'duration_minutes' => 45],
            ],
            'booking_services' => [
                ['name' => 'Swedish massage', 'default_duration_minutes' => 60],
                ['name' => 'Signature facial', 'default_duration_minutes' => 45],
            ],
            'install_booking_templates' => true,
            'welcome_message' => 'Welcome to :company. Reply BOOK to reserve a treatment.',
        ],
        'shop' => [
            'name' => 'WhatsApp shop',
            'flow_template' => 'whatsapp_shop_checkout',
            'playbook' => 'cart_recovery',
            'catalog_mode' => 'commerce',
            'catalog_vertical' => 'retail',
            'catalog_name' => 'Shop catalog',
            'catalog_items' => [
                ['id' => 'starter-bundle', 'title' => 'Starter bundle', 'price' => 1500],
                ['id' => 'bestseller', 'title' => 'Bestseller', 'price' => 2500],
            ],
            'install_invoice_template' => true,
            'welcome_message' => 'Welcome to :company. Browse the catalog and reply with an item to order.',
        ],
        'sales' => [
            'name' => 'Lead to cash',
            'flow_template' => 'lead_intake_routing',
            'playbook' => 'lead_to_cash',
            'welcome_message' => 'Welcome to :company. Tell us what you are looking for and we will help you next.',
        ],
        'hotel' => [
            'name' => 'Hotel & tours',
            'flow_template' => 'hotel_tour_concierge_bot',
            'playbook' => null,
            'catalog_mode' => 'listing',
            'catalog_vertical' => 'real_estate',
            'catalog_name' => 'Rooms & tours',
            'catalog_items' => [
                ['id' => 'deluxe-room', 'title' => 'Deluxe room', 'price' => 12000],
                ['id' => 'city-tour', 'title' => 'City tour', 'price' => 3500],
            ],
            'welcome_message' => 'Welcome to :company. Reply to check availability or ask about tours.',
        ],
        'real_estate' => [
            'name' => 'Real estate',
            'flow_template' => 'real_estate_agency_bot',
            'playbook' => 'lead_to_cash',
            'catalog_mode' => 'listing',
            'catalog_vertical' => 'real_estate',
            'catalog_name' => 'Property listings',
            'catalog_items' => [
                ['id' => 'sample-listing', 'title' => 'Sample 2-bedroom listing', 'price' => 4500000],
            ],
            'welcome_message' => 'Welcome to :company. Tell us the area and budget you have in mind.',
        ],
        'automotive' => [
            'name' => 'Automotive dealer',
            'flow_template' => 'automotive_dealer_bot',
            'playbook' => 'lead_to_cash',
            'catalog_mode' => 'listing',
            'catalog_vertical' => 'automotive',
            'catalog_name' => 'Vehicle inventory',
            'catalog_items' => [
                ['id' => 'demo-vehicle', 'title' => 'Demo vehicle', 'price' => 1850000],
            ],
            'welcome_message' => 'Welcome to :company. Ask about stock, financing, or a test drive.',
        ],
        'microfinance' => [
            'name' => 'Microfinance',
            'flow_template' => 'microfinance_banking_bot',
            'playbook' => 'lead_to_cash',
            'welcome_message' => 'Welcome to :company. Reply LOAN or SAVINGS to get started.',
        ],
        'faq' => [
            'name' => 'AI FAQ',
            'flow_template' => 'ai_faq_minimal',
            'playbook' => null,
            'welcome_message' => 'Welcome to :company. Ask a question and we will answer from our FAQ.',
        ],
    ],

];
