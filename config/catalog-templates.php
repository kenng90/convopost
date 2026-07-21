<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Catalog modes
    |--------------------------------------------------------------------------
    |
    | commerce → cart, checkout, inventory
    | listing  → inquiry-led showcase (property, vehicles, etc.)
    | service  → bookable packages / appointments
    |
    */
    'modes' => [
        'commerce' => [
            'label' => 'Sell products',
            'description' => 'WhatsApp shop with cart, checkout, and inventory.',
            'icon' => 'ni-bag-17',
            'default_vertical' => 'retail',
            'supports_cart' => true,
            'supports_checkout' => true,
            'supports_inventory' => true,
            'item_noun' => 'product',
            'item_noun_plural' => 'products',
            'public_title_suffix' => 'Shop',
            'search_placeholder' => 'Search products...',
            'cta_label' => 'Add to Cart',
        ],
        'listing' => [
            'label' => 'Show listings',
            'description' => 'Property, vehicles, and other items customers inquire about on WhatsApp.',
            'icon' => 'ni-building',
            'default_vertical' => 'general_listing',
            'supports_cart' => false,
            'supports_booking' => true,
            'supports_checkout' => false,
            'supports_inventory' => false,
            'item_noun' => 'listing',
            'item_noun_plural' => 'listings',
            'public_title_suffix' => 'Listings',
            'search_placeholder' => 'Search listings...',
            'cta_label' => 'Book',
            'inquire_cta_label' => 'Inquire on WhatsApp',
        ],
        'service' => [
            'label' => 'Offer services',
            'description' => 'Service packages customers can book or ask about.',
            'icon' => 'ni-calendar-grid-58',
            'default_vertical' => 'general_service',
            'supports_cart' => false,
            'supports_booking' => true,
            'supports_checkout' => false,
            'supports_inventory' => false,
            'item_noun' => 'service',
            'item_noun_plural' => 'services',
            'public_title_suffix' => 'Services',
            'search_placeholder' => 'Search services...',
            'cta_label' => 'Book',
            'inquire_cta_label' => 'Inquire on WhatsApp',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Vertical templates (schema + presentation)
    |--------------------------------------------------------------------------
    */
    'verticals' => [
        'retail' => [
            'mode' => 'commerce',
            'label' => 'General products',
            'description' => 'Physical or digital products with price and stock.',
            'status_field' => 'stockStatus',
            'status_options' => ['In Stock', 'Low Stock', 'Out of Stock'],
            'card_highlights' => [],
            'item_fields' => [],
            'filter_facets' => ['category', 'tag', 'stock', 'price'],
        ],
        'general_listing' => [
            'mode' => 'listing',
            'label' => 'General listings',
            'description' => 'Use when real estate, automotive, or services don\'t fit. Flexible listings with location, status, and custom details.',
            'supports_geo_map' => true,
            'book_cta_label' => 'Book',
            'status_field' => 'listing_status',
            'status_options' => ['Available', 'Under Offer', 'Sold', 'Leased'],
            'card_highlights' => ['location', 'listing_status'],
            'item_fields' => [
                ['key' => 'location', 'label' => 'Location', 'type' => 'text', 'filterable' => true],
                ['key' => 'latitude', 'label' => 'Latitude', 'type' => 'number', 'filterable' => false],
                ['key' => 'longitude', 'label' => 'Longitude', 'type' => 'number', 'filterable' => false],
                ['key' => 'listing_status', 'label' => 'Status', 'type' => 'select', 'options' => ['Available', 'Under Offer', 'Sold', 'Leased'], 'filterable' => true],
                ['key' => 'booking_source_name', 'label' => 'Bookable service', 'type' => 'text', 'filterable' => false],
            ],
            'filter_facets' => ['category', 'tag', 'status', 'price', 'location', 'geo'],
        ],
        'real_estate' => [
            'mode' => 'listing',
            'label' => 'Real estate',
            'description' => 'Homes, apartments, land, and commercial property.',
            'supports_geo_map' => true,
            'book_cta_label' => 'Book viewing',
            'status_field' => 'listing_status',
            'status_options' => ['Available', 'Under Offer', 'Sold', 'Leased'],
            'card_highlights' => ['location', 'bedrooms', 'bathrooms', 'listing_status'],
            'item_fields' => [
                ['key' => 'location', 'label' => 'Location', 'type' => 'text', 'filterable' => true],
                ['key' => 'latitude', 'label' => 'Latitude', 'type' => 'number', 'filterable' => false],
                ['key' => 'longitude', 'label' => 'Longitude', 'type' => 'number', 'filterable' => false],
                ['key' => 'bedrooms', 'label' => 'Bedrooms', 'type' => 'number', 'filterable' => true],
                ['key' => 'bathrooms', 'label' => 'Bathrooms', 'type' => 'number', 'filterable' => true],
                ['key' => 'area_sqm', 'label' => 'Area (m²)', 'type' => 'number', 'filterable' => false],
                ['key' => 'listing_status', 'label' => 'Status', 'type' => 'select', 'options' => ['Available', 'Under Offer', 'Sold', 'Leased'], 'filterable' => true],
                ['key' => 'property_type', 'label' => 'Property type', 'type' => 'select', 'options' => ['Apartment', 'House', 'Land', 'Commercial'], 'filterable' => true],
                ['key' => 'booking_source_name', 'label' => 'Bookable service', 'type' => 'text', 'filterable' => false],
            ],
            'filter_facets' => ['category', 'tag', 'status', 'price', 'location', 'geo'],
        ],
        'automotive' => [
            'mode' => 'listing',
            'label' => 'Automotive',
            'description' => 'Cars, trucks, motorcycles, and other vehicles.',
            'supports_geo_map' => true,
            'book_cta_label' => 'Book test drive',
            'status_field' => 'listing_status',
            'status_options' => ['Available', 'Reserved', 'Sold'],
            'card_highlights' => ['make', 'model', 'year', 'mileage', 'listing_status'],
            'item_fields' => [
                ['key' => 'make', 'label' => 'Make', 'type' => 'text', 'filterable' => true],
                ['key' => 'model', 'label' => 'Model', 'type' => 'text', 'filterable' => true],
                ['key' => 'year', 'label' => 'Year', 'type' => 'number', 'filterable' => true],
                ['key' => 'mileage', 'label' => 'Mileage (km)', 'type' => 'number', 'filterable' => false],
                ['key' => 'fuel_type', 'label' => 'Fuel', 'type' => 'select', 'options' => ['Petrol', 'Diesel', 'Hybrid', 'Electric'], 'filterable' => true],
                ['key' => 'listing_status', 'label' => 'Status', 'type' => 'select', 'options' => ['Available', 'Reserved', 'Sold'], 'filterable' => true],
                ['key' => 'booking_source_name', 'label' => 'Bookable service', 'type' => 'text', 'filterable' => false],
            ],
            'filter_facets' => ['category', 'tag', 'status', 'price', 'make', 'geo'],
        ],
        'general_service' => [
            'mode' => 'service',
            'label' => 'General services',
            'description' => 'Consulting, repairs, beauty, and other bookable services.',
            'book_cta_label' => 'Book',
            'status_field' => 'availability',
            'status_options' => ['Available', 'Fully Booked'],
            'card_highlights' => ['duration', 'availability'],
            'item_fields' => [
                ['key' => 'duration', 'label' => 'Duration', 'type' => 'text', 'filterable' => false],
                ['key' => 'availability', 'label' => 'Availability', 'type' => 'select', 'options' => ['Available', 'Fully Booked'], 'filterable' => true],
                ['key' => 'booking_source_name', 'label' => 'Bookable service', 'type' => 'text', 'filterable' => false],
            ],
            'filter_facets' => ['category', 'tag', 'status', 'price'],
        ],
    ],

];
