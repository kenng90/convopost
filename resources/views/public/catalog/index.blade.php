<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $catalog->name }} - {{ $presentation['public_title_suffix'] ?? 'Shop' }}</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <style>
        body {
            background-color: #f8f9fa;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
        }
        
        .navbar {
            background-color: #fff;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .product-card {
            border: none;
            border-radius: 8px;
            overflow: hidden;
            transition: transform 0.2s, box-shadow 0.2s;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            height: 100%;
            display: flex;
            flex-direction: column;
        }
        
        .product-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        
        .product-image {
            height: 200px;
            background-color: #e9ecef;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 48px;
            color: #ccc;
            background-size: cover;
            background-position: center;
            position: relative;
        }
        
        .stock-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .stock-in {
            background-color: #28a745;
            color: white;
        }
        
        .stock-out {
            background-color: #dc3545;
            color: white;
        }
        
        .stock-reserved,
        .stock-underoffer {
            background-color: #ffc107;
            color: #333;
        }

        .stock-sold,
        .stock-leased {
            background-color: #6c757d;
            color: white;
        }

        .listing-highlight {
            font-size: 12px;
            color: #495057;
        }

        .listing-image-gallery {
            position: relative;
            overflow: hidden;
        }

        .listing-gallery-track,
        .listing-gallery-slide {
            position: absolute;
            inset: 0;
            background-size: cover;
            background-position: center;
        }

        .listing-gallery-slide {
            opacity: 0;
            transition: opacity 0.25s ease;
        }

        .listing-gallery-slide.active {
            opacity: 1;
        }

        .gallery-nav {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            border: none;
            background: rgba(0, 0, 0, 0.45);
            color: #fff;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            z-index: 2;
        }

        .gallery-prev { left: 8px; }
        .gallery-next { right: 8px; }

        .gallery-dots {
            position: absolute;
            bottom: 10px;
            left: 0;
            right: 0;
            display: flex;
            justify-content: center;
            gap: 6px;
            z-index: 2;
        }

        .gallery-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.55);
            cursor: pointer;
        }

        .gallery-dot.active {
            background: #fff;
        }

        #listingMap {
            height: 320px;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            border: 1px solid #dee2e6;
        }
        
        .product-body {
            padding: 15px;
            flex: 1;
            display: flex;
            flex-direction: column;
        }
        
        .product-title {
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 8px;
        }
        
        .product-category {
            font-size: 12px;
            color: #6c757d;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
        }
        
        .product-description {
            font-size: 13px;
            color: #6c757d;
            margin-bottom: 10px;
            flex: 1;
            overflow: hidden;
            text-overflow: ellipsis;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
        }
        
        .product-tags {
            margin-bottom: 10px;
        }
        
        .tag-badge {
            display: inline-block;
            background-color: #e7f3ff;
            color: #0066cc;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 500;
            margin-right: 4px;
            margin-bottom: 4px;
        }
        
        .product-price {
            font-size: 20px;
            font-weight: 700;
            color: #28a745;
            margin-bottom: 10px;
        }
        
        .variant-selector {
            margin-bottom: 12px;
        }
        
        .variant-label {
            font-size: 12px;
            font-weight: 600;
            color: #333;
            display: block;
            margin-bottom: 6px;
        }
        
        .variant-options {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
        }
        
        .variant-option {
            padding: 6px 12px;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            background-color: #fff;
            font-size: 12px;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .variant-option:hover {
            border-color: #0066cc;
            color: #0066cc;
        }
        
        .variant-option.selected {
            background-color: #0066cc;
            color: white;
            border-color: #0066cc;
        }
        
        .variant-option.disabled {
            background-color: #f8f9fa;
            color: #ccc;
            cursor: not-allowed;
            border-color: #e9ecef;
        }
        
        .quantity-selector {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 10px;
        }
        
        .quantity-btn {
            width: 32px;
            height: 32px;
            padding: 0;
            border: 1px solid #dee2e6;
            background-color: #fff;
            border-radius: 4px;
            cursor: pointer;
        }
        
        .quantity-btn:hover {
            background-color: #f8f9fa;
        }
        
        .quantity-input {
            width: 50px;
            text-align: center;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            padding: 4px;
        }
        
        .add-to-cart-btn {
            width: 100%;
            background-color: #28a745;
            color: white;
            border: none;
            border-radius: 4px;
            padding: 8px 12px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.2s;
        }
        
        .add-to-cart-btn:hover:not(:disabled) {
            background-color: #218838;
        }
        
        .add-to-cart-btn:disabled {
            background-color: #ccc;
            cursor: not-allowed;
        }

        .booking-slot-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 8px;
            max-height: 220px;
            overflow-y: auto;
            margin-bottom: 12px;
        }

        .booking-slot-btn {
            width: 100%;
            border: 1px solid #dee2e6;
            border-radius: 6px;
            background: #fff;
            padding: 10px 12px;
            text-align: left;
            font-size: 14px;
            font-weight: 600;
            color: #212529;
            cursor: pointer;
            transition: border-color 0.2s, background-color 0.2s;
        }

        .booking-slot-btn:hover {
            border-color: #25D366;
            background: #f3fff7;
        }

        .booking-slot-btn.selected {
            border-color: #25D366;
            background: #e8f9ee;
            box-shadow: 0 0 0 1px #25D366;
        }

        .booking-slot-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .booking-loading-text {
            font-size: 12px;
            color: #6c757d;
        }

        .booking-summary-card {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 12px;
            margin-bottom: 12px;
            font-size: 13px;
        }

        .booking-summary-card dt {
            color: #6c757d;
            font-weight: 500;
        }

        .booking-summary-card dd {
            margin-bottom: 8px;
            font-weight: 600;
            color: #212529;
        }

        .booking-view-hidden {
            display: none !important;
        }

        .booking-panel-loader {
            padding: 48px 20px;
            text-align: center;
        }

        .booking-panel-loader .spinner-border {
            width: 2.5rem;
            height: 2.5rem;
            color: #25D366;
        }

        .add-to-cart-btn.is-loading {
            opacity: 0.85;
            cursor: wait;
        }
        
        .cart-sidebar {
            position: fixed;
            right: -350px;
            top: 0;
            width: 350px;
            height: 100vh;
            background-color: #fff;
            box-shadow: -2px 0 8px rgba(0,0,0,0.15);
            transition: right 0.3s;
            z-index: 999;
            display: flex;
            flex-direction: column;
        }
        
        .cart-sidebar.open {
            right: 0;
        }
        
        .cart-header {
            padding: 20px;
            border-bottom: 1px solid #dee2e6;
            font-size: 18px;
            font-weight: 700;
        }
        
        .cart-items {
            flex: 1;
            overflow-y: auto;
            padding: 15px;
        }
        
        .cart-item {
            padding: 12px;
            border-bottom: 1px solid #dee2e6;
        }
        
        .cart-item-title {
            font-weight: 600;
            font-size: 14px;
        }
        
        .cart-item-variant {
            font-size: 12px;
            color: #6c757d;
            margin-top: 4px;
        }
        
        .cart-item-qty {
            color: #6c757d;
            font-size: 12px;
            margin-top: 4px;
        }
        
        .cart-item-remove {
            background: none;
            border: none;
            color: #dc3545;
            cursor: pointer;
            padding: 0;
            float: right;
        }
        
        .cart-footer {
            padding: 20px;
            border-top: 1px solid #dee2e6;
        }

        .delivery-details-section {
            margin-bottom: 16px;
            padding-bottom: 16px;
            border-bottom: 1px solid #eee;
        }

        .delivery-details-title {
            font-size: 14px;
            font-weight: 700;
            margin-bottom: 4px;
            color: #212529;
        }

        .delivery-details-hint {
            font-size: 11px;
            color: #6c757d;
            margin-bottom: 12px;
            line-height: 1.4;
        }

        .delivery-field {
            margin-bottom: 10px;
        }

        .delivery-field label {
            display: block;
            font-size: 11px;
            font-weight: 600;
            color: #495057;
            margin-bottom: 4px;
        }

        .delivery-field label .optional {
            font-weight: 400;
            color: #6c757d;
        }

        .delivery-field input,
        .delivery-field textarea {
            width: 100%;
            border: 1px solid #ced4da;
            border-radius: 4px;
            padding: 8px 10px;
            font-size: 13px;
            line-height: 1.4;
        }

        .delivery-field input:focus,
        .delivery-field textarea:focus {
            outline: none;
            border-color: #007bff;
            box-shadow: 0 0 0 2px rgba(0, 123, 255, 0.15);
        }

        .delivery-field.has-error input,
        .delivery-field.has-error textarea {
            border-color: #dc3545;
        }

        .delivery-field-error {
            display: none;
            font-size: 11px;
            color: #dc3545;
            margin-top: 4px;
        }

        .delivery-field.has-error .delivery-field-error {
            display: block;
        }

        .cart-success-banner {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
            border-radius: 4px;
            padding: 12px;
            font-size: 13px;
            line-height: 1.45;
            margin-bottom: 14px;
        }

        .cart-error-banner {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
            border-radius: 4px;
            padding: 12px;
            font-size: 13px;
            line-height: 1.45;
            margin-bottom: 14px;
        }
        
        .cart-total {
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 15px;
            display: flex;
            justify-content: space-between;
        }
        
        .checkout-btn {
            width: 100%;
            background-color: #007bff;
            color: white;
            border: none;
            border-radius: 4px;
            padding: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
        }
        
        .checkout-btn:hover:not(:disabled) {
            background-color: #0056b3;
        }
        
        .checkout-btn:disabled {
            background-color: #ccc;
            cursor: not-allowed;
        }
        
        .cart-toggle {
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 60px;
            height: 60px;
            background-color: #28a745;
            color: white;
            border: none;
            border-radius: 50%;
            font-size: 20px;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            z-index: 998;
        }
        
        .cart-badge {
            position: absolute;
            top: -8px;
            right: -8px;
            background-color: #dc3545;
            color: white;
            border-radius: 50%;
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: 700;
        }
        
        .header-brand {
            font-size: 20px;
            font-weight: 700;
            color: #333;
        }
        
        .grid-container {
            padding: 30px 0;
        }
        
        .empty-cart {
            text-align: center;
            padding: 40px 20px;
            color: #6c757d;
        }
        
        .empty-cart-icon {
            font-size: 48px;
            margin-bottom: 15px;
            opacity: 0.5;
        }
        
        .overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: rgba(0,0,0,0.5);
            display: none;
            z-index: 998;
        }
        
        .overlay.visible {
            display: block;
        }

        .catalog-filters {
            background: #fff;
            border-radius: 8px;
            padding: 16px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.08);
            margin-bottom: 20px;
        }

        .catalog-filters .form-control,
        .catalog-filters .custom-select {
            font-size: 14px;
        }

        .catalog-results-meta {
            font-size: 14px;
            color: #6c757d;
            margin-bottom: 16px;
        }

        .catalog-pagination {
            margin-top: 24px;
            margin-bottom: 40px;
        }

        @media (max-width: 767px) {
            .catalog-filters .filter-actions {
                display: flex;
                gap: 8px;
            }

            .catalog-filters .filter-actions .btn {
                flex: 1;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-light">
        <div class="container">
            <span class="header-brand">{{ $catalog->name }}</span>
            <small class="text-muted">by {{ $company->name }}</small>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="container grid-container">
        @if($totalInCatalog > 0)
            <form method="GET" action="{{ route('catalog.public', $catalog->id) }}" class="catalog-filters" id="catalogFiltersForm">
                @if($flowToken)
                    <input type="hidden" name="flow_token" value="{{ $flowToken }}">
                @endif
                <div class="form-row">
                    <div class="form-group col-md-4 col-12">
                        <label for="filter-q" class="sr-only">Search</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fas fa-search"></i></span>
                            </div>
                            <input type="search" id="filter-q" name="q" class="form-control" placeholder="{{ $presentation['search_placeholder'] ?? 'Search...' }}" value="{{ $filters['q'] }}">
                        </div>
                    </div>
                    <div class="form-group col-md-2 col-6">
                        <label for="filter-category" class="sr-only">Category</label>
                        <select id="filter-category" name="category" class="custom-select">
                            <option value="">All categories</option>
                            @foreach($filterOptions['categories'] as $category)
                                <option value="{{ $category }}" @selected($filters['category'] === $category)>{{ $category }}</option>
                            @endforeach
                        </select>
                    </div>
                    @if($presentation['supports_inventory'] ?? true)
                    <div class="form-group col-md-2 col-6">
                        <label for="filter-stock" class="sr-only">Stock</label>
                        <select id="filter-stock" name="stock" class="custom-select">
                            <option value="">All stock</option>
                            <option value="In Stock" @selected($filters['stock'] === 'In Stock')>In Stock</option>
                            <option value="Low Stock" @selected($filters['stock'] === 'Low Stock')>Low Stock</option>
                            <option value="Out of Stock" @selected($filters['stock'] === 'Out of Stock')>Out of Stock</option>
                        </select>
                    </div>
                    @else
                    <div class="form-group col-md-2 col-6">
                        <label for="filter-status" class="sr-only">Status</label>
                        <select id="filter-status" name="status" class="custom-select">
                            <option value="">All statuses</option>
                            @foreach($filterOptions['statuses'] as $status)
                                <option value="{{ $status }}" @selected($filters['status'] === $status)>{{ $status }}</option>
                            @endforeach
                        </select>
                    </div>
                    @endif
                    @if(in_array('location', $presentation['filter_facets'] ?? [], true))
                    <div class="form-group col-md-2 col-6">
                        <label for="filter-location" class="sr-only">Location</label>
                        <select id="filter-location" name="location" class="custom-select">
                            <option value="">All locations</option>
                            @foreach($filterOptions['locations'] as $location)
                                <option value="{{ $location }}" @selected($filters['location'] === $location)>{{ $location }}</option>
                            @endforeach
                        </select>
                    </div>
                    @endif
                    @if(($presentation['supports_geo_map'] ?? false) && in_array('geo', $presentation['filter_facets'] ?? [], true))
                    <div class="form-group col-md-3 col-12">
                        <div class="d-flex flex-wrap align-items-center" style="gap: 8px;">
                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="useMyLocationForFilter()">
                                <i class="fas fa-location-arrow mr-1"></i>Near me
                            </button>
                            <select id="filter-radius" name="radius_km" class="custom-select" style="max-width: 140px;">
                                @foreach([5, 10, 25, 50, 100] as $radius)
                                    <option value="{{ $radius }}" @selected((float) ($filters['radius_km'] ?? 25) === (float) $radius)>{{ $radius }} km</option>
                                @endforeach
                            </select>
                            <input type="hidden" id="filter-near-lat" name="near_lat" value="{{ $filters['near_lat'] ?? '' }}">
                            <input type="hidden" id="filter-near-lng" name="near_lng" value="{{ $filters['near_lng'] ?? '' }}">
                            @if(!empty($filters['near_lat']) && !empty($filters['near_lng']))
                                <a href="{{ route('catalog.public', $catalog->id) }}" class="btn btn-sm btn-link">Clear map filter</a>
                            @endif
                        </div>
                    </div>
                    @endif
                    <div class="form-group col-md-2 col-6">
                        <label for="filter-tag" class="sr-only">Tag</label>
                        <select id="filter-tag" name="tag" class="custom-select">
                            <option value="">All tags</option>
                            @foreach($filterOptions['tags'] as $tag)
                                <option value="{{ $tag }}" @selected($filters['tag'] === $tag)>{{ $tag }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group col-md-2 col-6">
                        <label for="filter-sort" class="sr-only">Sort</label>
                        <select id="filter-sort" name="sort" class="custom-select">
                            <option value="default" @selected($filters['sort'] === 'default')>Default order</option>
                            <option value="title_asc" @selected($filters['sort'] === 'title_asc')>Name (A–Z)</option>
                            <option value="title_desc" @selected($filters['sort'] === 'title_desc')>Name (Z–A)</option>
                            <option value="price_asc" @selected($filters['sort'] === 'price_asc')>Price (low to high)</option>
                            <option value="price_desc" @selected($filters['sort'] === 'price_desc')>Price (high to low)</option>
                        </select>
                    </div>
                </div>
                <div class="form-row align-items-end">
                    <div class="form-group col-md-2 col-6">
                        <label for="filter-min-price" class="small text-muted mb-1">Min price ({{ $currencySymbol }})</label>
                        <input type="number" id="filter-min-price" name="min_price" class="form-control" min="0" step="0.01" placeholder="0" value="{{ $filters['min_price'] }}">
                    </div>
                    <div class="form-group col-md-2 col-6">
                        <label for="filter-max-price" class="small text-muted mb-1">Max price ({{ $currencySymbol }})</label>
                        <input type="number" id="filter-max-price" name="max_price" class="form-control" min="0" step="0.01" placeholder="Any" value="{{ $filters['max_price'] }}">
                    </div>
                    <div class="form-group col-md-8 col-12 filter-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-filter mr-1"></i> Apply filters
                        </button>
                        <a href="{{ route('catalog.public', $catalog->id) }}" class="btn btn-outline-secondary">Clear</a>
                    </div>
                </div>
            </form>

            <div class="catalog-results-meta">
                @php $itemNoun = $presentation['item_noun'] ?? 'item'; $itemNounPlural = $presentation['item_noun_plural'] ?? 'items'; @endphp
                @if($filteredTotal > 0)
                    Showing {{ $items->firstItem() }}–{{ $items->lastItem() }} of {{ $filteredTotal }} {{ $filteredTotal === 1 ? $itemNoun : $itemNounPlural }}
                    @if($filteredTotal < $totalInCatalog)
                        ({{ $totalInCatalog }} total in catalog)
                    @endif
                @else
                    No {{ $itemNounPlural }} match your filters ({{ $totalInCatalog }} in catalog)
                @endif
            </div>
        @endif

        @if($totalInCatalog === 0)
            <div class="alert alert-info" role="alert">
                <i class="fas fa-info-circle mr-2"></i>No {{ $itemNounPlural }} available in this catalog yet.
            </div>
        @elseif($items->count() > 0)
            @if(($presentation['supports_geo_map'] ?? false) && count($mapMarkers ?? []) > 0)
                <div id="listingMap"></div>
            @endif
            <div class="row">
                @foreach($items as $item)
                    @if($presentation['supports_cart'] ?? true)
                        @include('public.catalog.partials.product-card', ['item' => $item])
                    @else
                        @include('public.catalog.partials.listing-card', ['item' => $item, 'presentation' => $presentation])
                    @endif
                @endforeach
            </div>

            @if($items->hasPages())
                <div class="catalog-pagination d-flex justify-content-center">
                    {{ $items->withQueryString()->links('pagination::bootstrap-4') }}
                </div>
            @endif
        @else
            <div class="alert alert-warning" role="alert">
                <i class="fas fa-search mr-2"></i>No {{ $presentation['item_noun_plural'] ?? 'items' }} match your search or filters.
                <a href="{{ route('catalog.public', $catalog->id) }}" class="alert-link ml-1">Clear filters</a>
            </div>
        @endif
    </div>

    @if($presentation['supports_cart'] ?? true)
    <!-- Cart Sidebar -->
    <div class="cart-sidebar" id="cartSidebar">
        <div class="cart-header">
            <i class="fas fa-shopping-cart mr-2"></i>Your Cart
            <button onclick="toggleCart()" style="position: absolute; right: 15px; top: 15px; background: none; border: none; font-size: 20px; cursor: pointer;">×</button>
        </div>
        <div class="cart-items" id="cartItems">
            <div class="empty-cart">
                <div class="empty-cart-icon"><i class="fas fa-shopping-bag"></i></div>
                <p>Your cart is empty</p>
            </div>
        </div>
        <div class="cart-footer">
            <div id="cartSuccessBanner" class="cart-success-banner" style="display: none;" role="status"></div>
            <div id="cartErrorBanner" class="cart-error-banner" style="display: none;" role="alert"></div>

            <div id="deliveryDetailsSection" class="delivery-details-section" style="display: none;">
                <div class="delivery-details-title">Delivery details</div>
                <p class="delivery-details-hint">Required for Pay. Optional for WhatsApp — helps us fulfil your order faster.</p>

                <div class="delivery-field" id="fieldCustomerName">
                    <label for="customerName">Full name <span class="optional">(recommended)</span></label>
                    <input type="text" id="customerName" name="customerName" autocomplete="name" placeholder="Your name">
                    <div class="delivery-field-error" id="errorCustomerName"></div>
                </div>

                <div class="delivery-field" id="fieldCustomerPhone">
                    <label for="customerPhone">Phone (M-Pesa / WhatsApp)</label>
                    <input type="tel" id="customerPhone" name="customerPhone" autocomplete="tel" placeholder="e.g. 254712345678">
                    <div class="delivery-field-error" id="errorCustomerPhone"></div>
                </div>

                <div class="delivery-field" id="fieldDeliveryAddress">
                    <label for="deliveryAddress">Delivery address</label>
                    <textarea id="deliveryAddress" name="deliveryAddress" rows="2" autocomplete="street-address" placeholder="Street, building, area, city"></textarea>
                    <div class="delivery-field-error" id="errorDeliveryAddress"></div>
                </div>

                <div class="delivery-field" id="fieldOrderNotes">
                    <label for="orderNotes">Order notes <span class="optional">(optional)</span></label>
                    <textarea id="orderNotes" name="orderNotes" rows="2" placeholder="Delivery instructions, gate code, etc."></textarea>
                </div>
            </div>

            <div class="cart-total">
                <span>Total:</span>
                <span id="cartTotal">{{ $currencySymbol }} 0.00</span>
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                <button class="checkout-btn" id="checkoutBtn" onclick="proceedToCheckout()" disabled style="background-color: #25D366;">
                    <i class="fab fa-whatsapp mr-2"></i>WhatsApp
                </button>
                <button class="checkout-btn" id="invoiceBtn" onclick="generateInvoice()" disabled style="background-color: #007bff;">
                    <i class="fas fa-credit-card mr-2"></i>Pay
                </button>
            </div>
            <p class="delivery-details-hint" id="payHelperText" style="display: none; margin-top: 10px; margin-bottom: 0;">Invoice will be sent to your WhatsApp — open the link there when ready to pay.</p>
        </div>
    </div>

    <!-- Cart Toggle Button -->
    <button class="cart-toggle" id="cartToggle" onclick="toggleCart()">
        <i class="fas fa-shopping-cart"></i>
        <span class="cart-badge" id="cartBadge" style="display: none;">0</span>
    </button>

    <!-- Overlay -->
    <div class="overlay" id="overlay" onclick="toggleCart()"></div>
    @endif

    @if(!($presentation['supports_cart'] ?? true))
    <div class="cart-sidebar" id="bookingSidebar">
        <div class="cart-header">
            <i class="fas fa-calendar-check mr-2"></i><span id="bookingPanelTitle">Book</span>
            <button type="button" onclick="closeBookingPanel()" style="position: absolute; right: 15px; top: 15px; background: none; border: none; font-size: 20px; cursor: pointer;">×</button>
        </div>

        <div id="bookingLoadingView" class="cart-items booking-view-hidden booking-panel-loader">
            <div class="spinner-border mb-3" role="status" aria-hidden="true"></div>
            <p class="font-weight-bold mb-1" id="bookingLoadingTitle">Preparing booking</p>
            <p class="text-muted small mb-0" id="bookingLoadingHint">Loading available times...</p>
        </div>

        <div id="bookingSuccessView" class="cart-items booking-view-hidden" style="padding: 20px;">
            <div class="text-center mb-3">
                <div class="mx-auto mb-3 d-flex align-items-center justify-content-center" style="width: 64px; height: 64px; border-radius: 50%; background: #d4edda; color: #28a745;">
                    <i class="fas fa-check fa-2x"></i>
                </div>
                <h5 class="font-weight-bold mb-1">Booking confirmed</h5>
                <p class="text-muted small mb-0">Your appointment has been scheduled.</p>
            </div>
            <dl class="booking-summary-card mb-0" id="bookingConfirmationSummary"></dl>
            <p class="delivery-details-hint mt-3 mb-0">You may receive a WhatsApp confirmation if reminders are configured for this service.</p>
            <button type="button" class="checkout-btn mt-3" onclick="resetBookingPanel()" style="background-color: #6c757d; width: 100%;">
                Book another
            </button>
        </div>

        <div id="bookingPayingView" class="cart-items booking-view-hidden text-center" style="padding: 30px 20px;">
            <div class="mb-3" style="font-size: 42px; color: #007bff;">
                <i class="fas fa-mobile-alt"></i>
            </div>
            <h5 class="font-weight-bold">Complete payment on your phone</h5>
            <p class="text-muted small">We sent an M-Pesa prompt. Enter your PIN to confirm your booking.</p>
            <p class="booking-loading-text mt-3">Waiting for payment confirmation...</p>
        </div>

        <div id="bookingFormView" class="cart-items" style="padding: 20px;">
            <div id="bookingSuccessBanner" class="cart-success-banner" style="display: none;" role="status"></div>
            <div id="bookingErrorBanner" class="cart-error-banner" style="display: none;" role="alert"></div>

            <div id="bookingSlotSection" class="delivery-details-section booking-view-hidden" style="border-bottom: none; margin-bottom: 0; padding-bottom: 0;">
                <div class="delivery-details-title">Choose a time</div>
                <p class="delivery-details-hint" id="bookingSlotHint">Select an available date and time for your appointment.</p>
                <p class="delivery-details-hint booking-view-hidden" id="bookingTimezoneHint"></p>

                <div class="delivery-field" id="fieldBookingDuration">
                    <label for="bookingDuration">Duration</label>
                    <select id="bookingDuration" class="form-control form-control-sm" onchange="onBookingDurationChange()"></select>
                </div>

                <div class="delivery-field">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <label for="bookingDate" class="mb-0">Date</label>
                        <span id="bookingDatesLoading" class="booking-loading-text booking-view-hidden">Loading dates...</span>
                    </div>
                    <select id="bookingDate" class="form-control form-control-sm" onchange="loadBookingSlots()">
                        <option value="">Select a date</option>
                    </select>
                </div>

                <div class="delivery-field">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <label class="mb-0">Available times</label>
                        <span id="bookingSlotsLoading" class="booking-loading-text booking-view-hidden">Loading times...</span>
                    </div>
                    <div id="bookingSlotsEmpty" class="text-muted small booking-view-hidden">No times available for this date. Please choose another day.</div>
                    <div id="bookingSlotGrid" class="booking-slot-grid"></div>
                </div>
            </div>

            <div id="bookingLegacySection" class="delivery-details-section" style="border-bottom: none; margin-bottom: 0; padding-bottom: 0;">
                <div class="delivery-details-title" id="bookingDetailsTitle">Booking details</div>
                <p class="delivery-details-hint" id="bookingDetailsHint">Required to send your booking request on WhatsApp.</p>

                <div class="delivery-field" id="fieldBookingCustomerName">
                    <label for="bookingCustomerName">Full name <span class="optional">(recommended)</span></label>
                    <input type="text" id="bookingCustomerName" autocomplete="name" placeholder="Your name">
                    <div class="delivery-field-error" id="errorBookingCustomerName"></div>
                </div>

                <div class="delivery-field" id="fieldBookingCustomerPhone">
                    <label for="bookingCustomerPhone">Phone</label>
                    <input type="tel" id="bookingCustomerPhone" autocomplete="tel" placeholder="e.g. 254712345678">
                    <div class="delivery-field-error" id="errorBookingCustomerPhone"></div>
                </div>

                <div class="delivery-field" id="fieldBookingPreferredDateTime">
                    <label for="bookingPreferredDateTime">Preferred date / time <span class="optional" id="bookingDateOptional">(optional)</span></label>
                    <input type="text" id="bookingPreferredDateTime" placeholder="e.g. Saturday 10am or 2026-06-28 14:00">
                    <div class="delivery-field-error" id="errorBookingPreferredDateTime"></div>
                </div>

                <div class="delivery-field" id="fieldBookingNotes">
                    <label for="bookingNotes">Notes <span class="optional">(optional)</span></label>
                    <textarea id="bookingNotes" rows="2" placeholder="Viewing request, questions, or special requests"></textarea>
                    <div class="delivery-field-error" id="errorBookingNotes"></div>
                </div>
            </div>
        </div>

        <div class="cart-footer" id="bookingFooter">
            <button class="checkout-btn" id="bookingSubmitBtn" onclick="submitBookingRequest()" style="background-color: #25D366; width: 100%;">
                <i class="fas fa-calendar-check mr-2" id="bookingSubmitIcon"></i><span id="bookingSubmitLabel">Confirm booking</span>
            </button>
        </div>
    </div>

    <div class="overlay" id="bookingOverlay" onclick="closeBookingPanel()"></div>
    @endif

    @if(($presentation['supports_geo_map'] ?? false) && count($mapMarkers ?? []) > 0)
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
    @endif

    <!-- Bootstrap JS -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        function formatPrice(amount) {
            const value = parseFloat(amount) || 0;
            const formatted = value.toLocaleString(undefined, {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2,
            });

            @if(in_array($currencyCode, ['USD', 'EUR', 'GBP']))
                return '{{ $currencySymbol }}' + formatted;
            @else
                return '{{ $currencySymbol }} ' + formatted;
            @endif
        }

        const flowToken = @json($flowToken);
        const catalogId = {{ $catalog->id }};
        const flowNodeSettings = @json($flowNodeSettings ?? []);

        function trackCatalogEvent(event, metadata = {}) {
            fetch(`/catalog/${catalogId}/events`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
                body: JSON.stringify({ event, metadata }),
            }).catch(() => {});
        }

        trackCatalogEvent('view');

        @if($presentation['supports_cart'] ?? true)
        // Cart state with selected variants
        let cart = JSON.parse(localStorage.getItem('catalog_{{ $catalog->id }}_cart')) || [];
        let selectedVariants = {};
        const deliveryStorageKey = 'catalog_{{ $catalog->id }}_delivery';

        function loadDeliveryDetails() {
            try {
                const saved = JSON.parse(localStorage.getItem(deliveryStorageKey) || '{}');
                document.getElementById('customerName').value = saved.customerName || '';
                document.getElementById('customerPhone').value = saved.customerPhone || '';
                document.getElementById('deliveryAddress').value = saved.deliveryAddress || '';
                document.getElementById('orderNotes').value = saved.orderNotes || '';
            } catch (e) {
                // Ignore invalid saved data
            }
        }

        function saveDeliveryDetails() {
            const details = getDeliveryFormValues();
            localStorage.setItem(deliveryStorageKey, JSON.stringify(details));
        }

        function getDeliveryFormValues() {
            return {
                customerName: document.getElementById('customerName').value.trim(),
                customerPhone: document.getElementById('customerPhone').value.trim(),
                deliveryAddress: document.getElementById('deliveryAddress').value.trim(),
                orderNotes: document.getElementById('orderNotes').value.trim(),
            };
        }

        function clearDeliveryFieldErrors() {
            ['fieldCustomerName', 'fieldCustomerPhone', 'fieldDeliveryAddress'].forEach((id) => {
                const field = document.getElementById(id);
                if (field) {
                    field.classList.remove('has-error');
                }
            });
            ['errorCustomerName', 'errorCustomerPhone', 'errorDeliveryAddress'].forEach((id) => {
                const error = document.getElementById(id);
                if (error) {
                    error.textContent = '';
                }
            });
            hideCartBanners();
        }

        function setDeliveryFieldError(fieldId, errorId, message) {
            document.getElementById(fieldId).classList.add('has-error');
            document.getElementById(errorId).textContent = message;
        }

        function hideCartBanners() {
            document.getElementById('cartSuccessBanner').style.display = 'none';
            document.getElementById('cartErrorBanner').style.display = 'none';
        }

        function showCartSuccessBanner(message) {
            const banner = document.getElementById('cartSuccessBanner');
            banner.textContent = message;
            banner.style.display = 'block';
            document.getElementById('cartErrorBanner').style.display = 'none';
        }

        function showCartErrorBanner(message) {
            const banner = document.getElementById('cartErrorBanner');
            banner.textContent = message;
            banner.style.display = 'block';
            document.getElementById('cartSuccessBanner').style.display = 'none';
        }

        function validateDeliveryDetails(requireAddress = true) {
            clearDeliveryFieldErrors();
            const details = getDeliveryFormValues();
            let valid = true;

            if (!details.customerPhone) {
                setDeliveryFieldError('fieldCustomerPhone', 'errorCustomerPhone', 'Phone number is required.');
                valid = false;
            } else if (details.customerPhone.replace(/\D/g, '').length < 9) {
                setDeliveryFieldError('fieldCustomerPhone', 'errorCustomerPhone', 'Enter a valid phone number (e.g. 254712345678).');
                valid = false;
            }

            if (requireAddress && !details.deliveryAddress) {
                setDeliveryFieldError('fieldDeliveryAddress', 'errorDeliveryAddress', 'Delivery address is required for Pay.');
                valid = false;
            }

            if (!valid) {
                const firstError = document.querySelector('.delivery-field.has-error input, .delivery-field.has-error textarea');
                if (firstError) {
                    firstError.focus();
                }
            }

            return valid ? details : null;
        }

        function updateDeliverySectionVisibility() {
            const hasItems = cart.length > 0;
            document.getElementById('deliveryDetailsSection').style.display = hasItems ? 'block' : 'none';
            document.getElementById('payHelperText').style.display = hasItems ? 'block' : 'none';
        }

        ['customerName', 'customerPhone', 'deliveryAddress', 'orderNotes'].forEach((id) => {
            const input = document.getElementById(id);
            if (!input) {
                return;
            }
            input.addEventListener('input', () => {
                saveDeliveryDetails();
                const field = input.closest('.delivery-field');
                if (field) {
                    field.classList.remove('has-error');
                }
            });
        });

        loadDeliveryDetails();
        
        // Initialize cart display
        updateCartDisplay();

        // Toggle cart sidebar
        function toggleCart() {
            document.getElementById('cartSidebar').classList.toggle('open');
            document.getElementById('overlay').classList.toggle('visible');
        }

        // Select variant
        function selectVariant(button, productId) {
            const variants = button.parentElement.querySelectorAll('.variant-option');
            variants.forEach(v => v.classList.remove('selected'));
            button.classList.add('selected');
            selectedVariants[productId] = button.dataset.variant;
        }

        // Add to cart
        function addToCart(productId, productTitle, productPrice, button) {
            const qtyInput = button.parentElement.querySelector('.quantity-input');
            const quantity = parseInt(qtyInput.value) || 1;
            const price = parseFloat(productPrice) || 0;
            const selectedVariant = selectedVariants[productId] || null;

            // Check if variants are required
            const variantOptions = button.closest('.product-card').querySelector('[data-product-id="' + productId + '"]');
            if (variantOptions && !selectedVariant) {
                alert('Please select a variant before adding to cart');
                return;
            }

            // Create cart item key to differentiate by variant
            const cartItemKey = selectedVariant ? `${productId}_${selectedVariant}` : productId;

            const existingItem = cart.find(item => item.cartKey === cartItemKey);
            if (existingItem) {
                existingItem.quantity += quantity;
            } else {
                cart.push({
                    cartKey: cartItemKey,
                    id: productId,
                    title: productTitle,
                    price: price,
                    quantity: quantity,
                    variant: selectedVariant
                });
            }

            saveCart();
            updateCartDisplay();
            hideCartBanners();
            trackCatalogEvent('cart_add', { product_id: productId, quantity });
            
            // Reset quantity and variant
            qtyInput.value = 1;
            if (variantOptions) {
                variantOptions.querySelectorAll('.variant-option').forEach(v => v.classList.remove('selected'));
            }
            delete selectedVariants[productId];
            
            // Show feedback
            button.textContent = '✓ Added';
            button.style.backgroundColor = '#28a745';
            setTimeout(() => {
                button.innerHTML = button.closest('.product-card').querySelector('.product-image').nextElementSibling.querySelector('[data-product-id="' + productId + '"]') ? '<i class="fas fa-shopping-cart mr-2"></i>Add to Cart' : '<i class="fas fa-shopping-cart mr-2"></i>Add to Cart';
                button.style.backgroundColor = '';
            }, 2000);
        }

        // Increase quantity
        function increaseQty(button) {
            const input = button.parentElement.querySelector('.quantity-input');
            input.value = (parseInt(input.value) || 0) + 1;
        }

        // Decrease quantity
        function decreaseQty(button) {
            const input = button.parentElement.querySelector('.quantity-input');
            const value = parseInt(input.value) || 1;
            if (value > 1) {
                input.value = value - 1;
            }
        }

        // Save cart to localStorage
        function saveCart() {
            localStorage.setItem('catalog_{{ $catalog->id }}_cart', JSON.stringify(cart));
        }

        // Update cart display
        function updateCartDisplay() {
            const cartItemsDiv = document.getElementById('cartItems');
            const cartTotal = document.getElementById('cartTotal');
            const cartBadge = document.getElementById('cartBadge');
            const checkoutBtn = document.getElementById('checkoutBtn');

            if (cart.length === 0) {
                cartItemsDiv.innerHTML = '<div class="empty-cart"><div class="empty-cart-icon"><i class="fas fa-shopping-bag"></i></div><p>Your cart is empty</p></div>';
                cartBadge.style.display = 'none';
                cartTotal.textContent = formatPrice(0);
                checkoutBtn.disabled = true;
                document.getElementById('invoiceBtn').disabled = true;
                updateDeliverySectionVisibility();
                return;
            }

            // Show badge
            cartBadge.textContent = cart.length;
            cartBadge.style.display = 'flex';

            // Build cart items HTML
            let html = '';
            let total = 0;

            cart.forEach((item, index) => {
                const price = parseFloat(item.price) || 0;
                const itemTotal = price * item.quantity;
                total += itemTotal;
                
                const variantText = item.variant ? `<div class="cart-item-variant">📦 Variant: <strong>${item.variant}</strong></div>` : '';
                
                html += `
                    <div class="cart-item">
                        <button class="cart-item-remove" onclick="removeFromCart(${index})">
                            <i class="fas fa-trash"></i>
                        </button>
                        <div class="cart-item-title">${item.title}</div>
                        ${variantText}
                        <div class="cart-item-qty">${formatPrice(price)} × ${item.quantity} = ${formatPrice(itemTotal)}</div>
                    </div>
                `;
            });

            cartItemsDiv.innerHTML = html;
            cartTotal.textContent = formatPrice(total);
            checkoutBtn.disabled = false;
            document.getElementById('invoiceBtn').disabled = false;
            updateDeliverySectionVisibility();
        }

        // Remove from cart
        function removeFromCart(index) {
            cart.splice(index, 1);
            saveCart();
            updateCartDisplay();
        }

        // Proceed to checkout via WhatsApp
        function proceedToCheckout() {
            if (cart.length === 0) return;

            const whatsappNumber = @json($whatsappOrderNumber);
            if (!whatsappNumber) {
                showCartErrorBanner('WhatsApp number not configured for this seller. Please contact the seller directly.');
                return;
            }

            const details = getDeliveryFormValues();
            saveDeliveryDetails();

            const payload = {
                items: cart.map(item => ({ id: item.id, quantity: item.quantity })),
                flow_token: flowToken,
            };

            if (details.customerName) {
                payload.customerName = details.customerName;
            }
            if (details.customerPhone) {
                payload.customerPhone = details.customerPhone;
            }
            if (details.orderNotes) {
                payload.notes = details.orderNotes;
            }

            fetch(`/catalog/${catalogId}/generate-order`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
                body: JSON.stringify(payload),
            })
            .then(r => r.json())
            .then(data => {
                if (!data.success) {
                    showCartErrorBanner(data.message || 'Could not generate order');
                    return;
                }

                hideCartBanners();
                const encodedMessage = encodeURIComponent(data.message);
                window.open(`https://wa.me/${whatsappNumber}?text=${encodedMessage}`, '_blank');
            })
            .catch(() => showCartErrorBanner('Could not start WhatsApp checkout. Please try again.'));
        }

        // Generate invoice and send payment link via WhatsApp
        function generateInvoice() {
            if (cart.length === 0) return;

            const details = validateDeliveryDetails(true);
            if (!details) {
                showCartErrorBanner('Please complete the required delivery details below.');
                return;
            }

            saveDeliveryDetails();

            let total = 0;
            cart.forEach(item => {
                const price = parseFloat(item.price) || 0;
                total += price * item.quantity;
            });

            const button = document.getElementById('invoiceBtn');
            const originalHtml = button.innerHTML;
            button.disabled = true;
            button.innerHTML = '<span class="spinner-border spinner-border-sm mr-2"></span>Creating...';

            const csrfToken = document.querySelector('meta[name="csrf-token"]');
            const token = csrfToken ? csrfToken.getAttribute('content') : '';

            const requestBody = {
                items: cart,
                customerPhone: details.customerPhone,
                deliveryAddress: details.deliveryAddress,
                amount: total.toFixed(2),
                flow_token: flowToken,
            };

            if (details.customerName) {
                requestBody.customerName = details.customerName;
            }
            if (details.orderNotes) {
                requestBody.notes = details.orderNotes;
            }

            fetch('/catalog/{{ $catalog->id }}/create-invoice', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': token,
                },
                body: JSON.stringify(requestBody)
            })
            .then(response => {
                const contentType = response.headers.get('content-type');
                if (!contentType || !contentType.includes('application/json')) {
                    return response.text().then(() => {
                        throw new Error('Server returned an unexpected response. Status: ' + response.status);
                    });
                }
                return response.json();
            })
            .then(data => {
                button.disabled = false;
                button.innerHTML = originalHtml;

                if (data.success) {
                    let successMsg = 'Invoice created successfully!';
                    if (data.invoice.whatsapp_sent) {
                        successMsg += ' It has been sent to your WhatsApp — open the link there when you are ready to pay.';
                    } else {
                        successMsg += ' WhatsApp delivery is not configured for this seller.';
                    }

                    cart = [];
                    saveCart();
                    updateCartDisplay();
                    showCartSuccessBanner(successMsg);
                } else {
                    showCartErrorBanner(data.message || 'Failed to create invoice');
                }
            })
            .catch(error => {
                button.disabled = false;
                button.innerHTML = originalHtml;
                console.error('Invoice creation error:', error);
                showCartErrorBanner('Error creating invoice: ' + error.message);
            });
        }
        @else
        let selectedBookingItemId = null;
        let selectedBookingItemTitle = '';
        let bookingMode = 'whatsapp';
        let bookingSourceConfig = null;
        let bookingDates = [];
        let bookingSlots = [];
        let selectedBookingSlotId = '';
        let selectedBookingSlotLabel = '';
        let bookingPanelOpening = false;
        let activeBookingCardButton = null;
        const bookingStorageKey = 'catalog_{{ $catalog->id }}_booking';
        const completionType = flowNodeSettings.completionType || 'booking';
        const requirePreferredDateTime = !!flowNodeSettings.requirePreferredDateTime;

        function bookingView(name) {
            const views = {
                form: document.getElementById('bookingFormView'),
                success: document.getElementById('bookingSuccessView'),
                paying: document.getElementById('bookingPayingView'),
                loading: document.getElementById('bookingLoadingView'),
            };
            Object.entries(views).forEach(([key, element]) => {
                if (!element) {
                    return;
                }
                element.classList.toggle('booking-view-hidden', key !== name);
            });
            const footer = document.getElementById('bookingFooter');
            if (footer) {
                footer.classList.toggle('booking-view-hidden', name !== 'form');
            }
        }

        function setBookingCardButtonLoading(button, isLoading) {
            if (!button) {
                return;
            }

            if (isLoading) {
                if (!button.dataset.originalHtml) {
                    button.dataset.originalHtml = button.innerHTML;
                }
                button.disabled = true;
                button.classList.add('is-loading');
                button.innerHTML = '<span class="spinner-border spinner-border-sm mr-2" role="status" aria-hidden="true"></span>Opening...';
                activeBookingCardButton = button;
                return;
            }

            button.disabled = false;
            button.classList.remove('is-loading');
            if (button.dataset.originalHtml) {
                button.innerHTML = button.dataset.originalHtml;
            }

            if (activeBookingCardButton === button) {
                activeBookingCardButton = null;
            }
        }

        function resetActiveBookingCardButton() {
            if (activeBookingCardButton) {
                setBookingCardButtonLoading(activeBookingCardButton, false);
            }
        }

        function setBookingPanelLoading(isLoading, hint) {
            const hintEl = document.getElementById('bookingLoadingHint');
            if (hintEl && hint) {
                hintEl.textContent = hint;
            }

            if (isLoading) {
                bookingView('loading');
            }
        }

        function loadBookingDetails() {
            try {
                const saved = JSON.parse(localStorage.getItem(bookingStorageKey) || '{}');
                document.getElementById('bookingCustomerName').value = saved.customerName || '';
                document.getElementById('bookingCustomerPhone').value = saved.customerPhone || '';
                document.getElementById('bookingPreferredDateTime').value = saved.preferredDateTime || '';
                document.getElementById('bookingNotes').value = saved.notes || '';
            } catch (e) {
                // Ignore invalid saved data
            }
        }

        function saveBookingDetails() {
            localStorage.setItem(bookingStorageKey, JSON.stringify(getBookingFormValues()));
        }

        function getBookingFormValues() {
            return {
                customerName: document.getElementById('bookingCustomerName').value.trim(),
                customerPhone: document.getElementById('bookingCustomerPhone').value.trim(),
                preferredDateTime: document.getElementById('bookingPreferredDateTime').value.trim(),
                notes: document.getElementById('bookingNotes').value.trim(),
            };
        }

        function clearBookingFieldErrors() {
            ['fieldBookingCustomerName', 'fieldBookingCustomerPhone', 'fieldBookingPreferredDateTime', 'fieldBookingNotes'].forEach((id) => {
                const field = document.getElementById(id);
                if (field) {
                    field.classList.remove('has-error');
                }
            });
            ['errorBookingCustomerName', 'errorBookingCustomerPhone', 'errorBookingPreferredDateTime', 'errorBookingNotes'].forEach((id) => {
                const error = document.getElementById(id);
                if (error) {
                    error.textContent = '';
                }
            });
            hideBookingBanners();
        }

        function setBookingFieldError(fieldId, errorId, message) {
            document.getElementById(fieldId).classList.add('has-error');
            document.getElementById(errorId).textContent = message;
        }

        function hideBookingBanners() {
            document.getElementById('bookingSuccessBanner').style.display = 'none';
            document.getElementById('bookingErrorBanner').style.display = 'none';
        }

        function showBookingErrorBanner(message) {
            hideBookingBanners();
            const banner = document.getElementById('bookingErrorBanner');
            banner.textContent = message;
            banner.style.display = 'block';
        }

        function showBookingSuccessBanner(message) {
            hideBookingBanners();
            const banner = document.getElementById('bookingSuccessBanner');
            banner.textContent = message;
            banner.style.display = 'block';
        }

        function formatBookingDateLabel(dateString) {
            const date = new Date(dateString + 'T12:00:00');
            return date.toLocaleDateString(undefined, {
                weekday: 'short',
                month: 'short',
                day: 'numeric',
            });
        }

        function setBookingLoadingState(target, isLoading) {
            const element = document.getElementById(target);
            if (element) {
                element.classList.toggle('booking-view-hidden', !isLoading);
            }
        }

        function resetBookingSlotState() {
            bookingDates = [];
            bookingSlots = [];
            selectedBookingSlotId = '';
            selectedBookingSlotLabel = '';
            document.getElementById('bookingDate').innerHTML = '<option value="">Select a date</option>';
            document.getElementById('bookingSlotGrid').innerHTML = '';
            document.getElementById('bookingSlotsEmpty').classList.add('booking-view-hidden');
        }

        function configureBookingUiForMode(mode) {
            bookingMode = mode;
            const slotSection = document.getElementById('bookingSlotSection');
            const legacySection = document.getElementById('bookingLegacySection');
            const submitLabel = document.getElementById('bookingSubmitLabel');
            const submitIcon = document.getElementById('bookingSubmitIcon');
            const detailsTitle = document.getElementById('bookingDetailsTitle');
            const detailsHint = document.getElementById('bookingDetailsHint');
            const phoneField = document.getElementById('fieldBookingCustomerPhone');
            const dateField = document.getElementById('fieldBookingPreferredDateTime');
            const durationField = document.getElementById('fieldBookingDuration');

            if (mode === 'slots') {
                slotSection.classList.remove('booking-view-hidden');
                legacySection.classList.remove('booking-view-hidden');
                detailsTitle.textContent = 'Your details';
                detailsHint.textContent = 'Enter your contact details to confirm the booking.';
                submitLabel.textContent = bookingSourceConfig?.payment_required ? 'Confirm & pay' : 'Confirm booking';
                submitIcon.className = 'fas fa-calendar-check mr-2';
                phoneField.style.display = 'block';
                dateField.style.display = 'none';
                durationField.style.display = (bookingSourceConfig?.duration_options || []).length > 1 ? 'block' : 'none';

                const timezoneHint = document.getElementById('bookingTimezoneHint');
                if (bookingSourceConfig?.timezone) {
                    timezoneHint.textContent = 'Times shown in ' + bookingSourceConfig.timezone + '.';
                    timezoneHint.classList.remove('booking-view-hidden');
                } else {
                    timezoneHint.classList.add('booking-view-hidden');
                }

                return;
            }

            slotSection.classList.add('booking-view-hidden');
            legacySection.classList.remove('booking-view-hidden');
            detailsTitle.textContent = mode === 'inquiry' ? 'Inquiry details' : 'Booking details';
            detailsHint.textContent = mode === 'inquiry'
                ? 'Required to send your inquiry on WhatsApp.'
                : 'Required to send your booking request on WhatsApp.';
            submitLabel.textContent = mode === 'inquiry' ? 'Inquire on WhatsApp' : @json($presentation['cta_label'] ?? 'Book on WhatsApp');
            submitIcon.className = 'fab fa-whatsapp mr-2';
            phoneField.style.display = mode === 'inquiry' ? 'none' : 'block';
            dateField.style.display = mode === 'inquiry' ? 'none' : 'block';

            const dateOptional = document.getElementById('bookingDateOptional');
            if (dateOptional) {
                dateOptional.textContent = requirePreferredDateTime ? '' : '(optional)';
            }
        }

        async function loadBookingConfig(itemId) {
            const params = new URLSearchParams();
            if (flowToken) {
                params.set('flow_token', flowToken);
            }

            const response = await fetch(`/catalog/${catalogId}/items/${encodeURIComponent(itemId)}/booking-config?` + params.toString());
            const data = await response.json();

            if (!response.ok || !data.success) {
                throw new Error(data.message || 'Could not load booking options.');
            }

            bookingSourceConfig = data.source || null;
            configureBookingUiForMode(data.mode || 'whatsapp');

            if (data.mode === 'slots') {
                populateBookingDurationOptions();
                setBookingPanelLoading(true, 'Loading available dates...');
                await loadBookingDates();
            }
        }

        function populateBookingDurationOptions() {
            const select = document.getElementById('bookingDuration');
            const options = bookingSourceConfig?.duration_options || [bookingSourceConfig?.default_duration_minutes || 30];
            select.innerHTML = '';

            options.forEach((minutes) => {
                const option = document.createElement('option');
                option.value = minutes;
                option.textContent = minutes + ' minutes';
                select.appendChild(option);
            });
        }

        function currentBookingDurationMinutes() {
            const select = document.getElementById('bookingDuration');
            if (!select || select.options.length === 0) {
                return bookingSourceConfig?.default_duration_minutes || 30;
            }

            return parseInt(select.value, 10) || bookingSourceConfig?.default_duration_minutes || 30;
        }

        async function loadBookingDates() {
            if (!selectedBookingItemId || bookingMode !== 'slots') {
                return;
            }

            resetBookingSlotState();
            setBookingLoadingState('bookingDatesLoading', true);

            try {
                const params = new URLSearchParams({
                    duration_minutes: String(currentBookingDurationMinutes()),
                });
                const response = await fetch(`/catalog/${catalogId}/items/${encodeURIComponent(selectedBookingItemId)}/availability/dates?` + params.toString());
                const data = await response.json();

                if (!response.ok || !data.success) {
                    throw new Error(data.message || 'Could not load available dates.');
                }

                bookingDates = data.dates || [];
                const dateSelect = document.getElementById('bookingDate');
                dateSelect.innerHTML = '<option value="">' + (bookingDates.length ? 'Select a date' : 'No dates available') + '</option>';
                bookingDates.forEach((date) => {
                    const option = document.createElement('option');
                    option.value = date;
                    option.textContent = formatBookingDateLabel(date);
                    dateSelect.appendChild(option);
                });
            } catch (error) {
                showBookingErrorBanner(error.message || 'Could not load available dates.');
            } finally {
                setBookingLoadingState('bookingDatesLoading', false);
            }
        }

        async function loadBookingSlots() {
            const selectedDate = document.getElementById('bookingDate').value;
            selectedBookingSlotId = '';
            selectedBookingSlotLabel = '';
            document.getElementById('bookingSlotGrid').innerHTML = '';
            document.getElementById('bookingSlotsEmpty').classList.add('booking-view-hidden');

            if (!selectedDate || !selectedBookingItemId || bookingMode !== 'slots') {
                return;
            }

            setBookingLoadingState('bookingSlotsLoading', true);

            try {
                const params = new URLSearchParams({
                    date: selectedDate,
                    duration_minutes: String(currentBookingDurationMinutes()),
                });
                const response = await fetch(`/catalog/${catalogId}/items/${encodeURIComponent(selectedBookingItemId)}/availability/slots?` + params.toString());
                const data = await response.json();

                if (!response.ok || !data.success) {
                    throw new Error(data.message || 'Could not load available times.');
                }

                bookingSlots = data.slots || [];
                renderBookingSlots();
            } catch (error) {
                showBookingErrorBanner(error.message || 'Could not load available times.');
            } finally {
                setBookingLoadingState('bookingSlotsLoading', false);
            }
        }

        function renderBookingSlots() {
            const grid = document.getElementById('bookingSlotGrid');
            grid.innerHTML = '';

            if (!bookingSlots.length) {
                document.getElementById('bookingSlotsEmpty').classList.remove('booking-view-hidden');
                return;
            }

            document.getElementById('bookingSlotsEmpty').classList.add('booking-view-hidden');

            bookingSlots.forEach((slot) => {
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'booking-slot-btn' + (selectedBookingSlotId === slot.id ? ' selected' : '');
                button.textContent = slot.title || slot.label || 'Available slot';
                button.onclick = () => selectBookingSlot(slot);
                grid.appendChild(button);
            });
        }

        function selectBookingSlot(slot) {
            selectedBookingSlotId = slot.id;
            selectedBookingSlotLabel = slot.title || slot.label || '';
            renderBookingSlots();
            hideBookingBanners();
        }

        function onBookingDurationChange() {
            loadBookingDates();
        }

        function validateBookingDetails() {
            clearBookingFieldErrors();
            const details = getBookingFormValues();
            let valid = true;

            if (bookingMode === 'slots') {
                if (!selectedBookingSlotId) {
                    showBookingErrorBanner('Please select an available time slot.');
                    return null;
                }

                if (!details.customerPhone) {
                    setBookingFieldError('fieldBookingCustomerPhone', 'errorBookingCustomerPhone', 'Phone is required.');
                    valid = false;
                } else if (details.customerPhone.replace(/\D/g, '').length < 9) {
                    setBookingFieldError('fieldBookingCustomerPhone', 'errorBookingCustomerPhone', 'Enter a valid phone number (e.g. 254712345678).');
                    valid = false;
                }

                return valid ? details : null;
            }

            if (completionType !== 'inquiry' && bookingMode !== 'inquiry' && !details.customerPhone) {
                setBookingFieldError('fieldBookingCustomerPhone', 'errorBookingCustomerPhone', 'Phone is required.');
                valid = false;
            }

            if (requirePreferredDateTime && bookingMode === 'whatsapp' && !details.preferredDateTime) {
                setBookingFieldError('fieldBookingPreferredDateTime', 'errorBookingPreferredDateTime', 'Preferred date and time is required.');
                valid = false;
            }

            if (!valid) {
                const firstError = document.querySelector('#bookingSidebar .delivery-field.has-error input, #bookingSidebar .delivery-field.has-error textarea');
                if (firstError) {
                    firstError.focus();
                }
            }

            return valid ? details : null;
        }

        function showBookingConfirmation(reservation) {
            const start = reservation?.start_date ? new Date(reservation.start_date) : null;
            const end = reservation?.end_date ? new Date(reservation.end_date) : null;
            const summary = document.getElementById('bookingConfirmationSummary');
            const timeLabel = reservation.time_label
                || selectedBookingSlotLabel
                || (start && end
                    ? start.toLocaleTimeString(undefined, { hour: '2-digit', minute: '2-digit' }) + ' – ' + end.toLocaleTimeString(undefined, { hour: '2-digit', minute: '2-digit' })
                    : '');

            summary.innerHTML = `
                <dt>Service</dt><dd>${reservation.service || reservation.source?.name || selectedBookingItemTitle}</dd>
                <dt>Date</dt><dd>${reservation.date_label || (start ? start.toLocaleDateString(undefined, { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' }) : '')}</dd>
                <dt>Time</dt><dd>${timeLabel}</dd>
                <dt>Reference</dt><dd>#${reservation.id || ''}</dd>
            `;
            bookingView('success');
        }

        function resetBookingPanel() {
            bookingView('form');
            hideBookingBanners();
            resetBookingSlotState();
            if (selectedBookingItemId) {
                loadBookingConfig(selectedBookingItemId).catch(() => configureBookingUiForMode('whatsapp'));
            }
        }

        async function openBookingPanel(itemId, itemTitle, triggerButton) {
            if (bookingPanelOpening) {
                return;
            }

            bookingPanelOpening = true;
            setBookingCardButtonLoading(triggerButton, true);

            selectedBookingItemId = itemId;
            selectedBookingItemTitle = itemTitle || 'Listing';
            document.getElementById('bookingPanelTitle').textContent = completionType === 'inquiry'
                ? 'Inquire: ' + selectedBookingItemTitle
                : 'Book: ' + selectedBookingItemTitle;

            loadBookingDetails();
            hideBookingBanners();
            resetBookingSlotState();

            document.getElementById('bookingSidebar').classList.add('open');
            document.getElementById('bookingOverlay').classList.add('visible');
            setBookingPanelLoading(true, 'Loading booking options...');

            try {
                await loadBookingConfig(itemId);
                bookingView('form');
            } catch (error) {
                configureBookingUiForMode('whatsapp');
                bookingView('form');
                showBookingErrorBanner(error.message || 'Could not load booking options.');
            } finally {
                bookingPanelOpening = false;
                resetActiveBookingCardButton();
            }
        }

        function closeBookingPanel() {
            bookingPanelOpening = false;
            resetActiveBookingCardButton();
            document.getElementById('bookingSidebar').classList.remove('open');
            document.getElementById('bookingOverlay').classList.remove('visible');
        }

        async function pollBookingPayment(invoicePublicUuid) {
            bookingView('paying');

            for (let attempt = 0; attempt < 45; attempt++) {
                await new Promise((resolve) => setTimeout(resolve, 2000));

                const response = await fetch(`/catalog/${catalogId}/booking-payment/${encodeURIComponent(invoicePublicUuid)}`);
                const data = await response.json();

                if (!response.ok || !data.success) {
                    continue;
                }

                const payment = data.payment || {};
                if (payment.status === 'success' && payment.fulfilled && payment.reservation) {
                    showBookingConfirmation(payment.reservation);
                    trackCatalogEvent('listing_booking', { item_id: selectedBookingItemId, reservation_id: payment.reservation.id });
                    return;
                }

                if (payment.status === 'failed') {
                    bookingView('form');
                    showBookingErrorBanner('Payment failed or was cancelled. Please try again.');
                    return;
                }
            }

            bookingView('form');
            showBookingErrorBanner('Payment is taking longer than expected. If you completed M-Pesa, contact the business with your receipt.');
        }

        function submitBookingRequest() {
            if (!selectedBookingItemId) {
                return;
            }

            const details = validateBookingDetails();
            if (!details) {
                if (!document.getElementById('bookingErrorBanner').style.display || document.getElementById('bookingErrorBanner').style.display === 'none') {
                    showBookingErrorBanner('Please complete the required booking details.');
                }
                return;
            }

            saveBookingDetails();

            if (bookingMode === 'slots') {
                submitSlotBooking(details);
                return;
            }

            submitWhatsAppBooking(details);
        }

        function submitSlotBooking(details) {
            const button = document.getElementById('bookingSubmitBtn');
            const originalHtml = button.innerHTML;
            button.disabled = true;
            button.innerHTML = '<span class="spinner-border spinner-border-sm mr-2"></span>Confirming...';

            fetch(`/catalog/${catalogId}/items/${encodeURIComponent(selectedBookingItemId)}/book`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
                body: JSON.stringify({
                    slot_id: selectedBookingSlotId,
                    customerName: details.customerName || null,
                    customerPhone: details.customerPhone,
                    notes: details.notes || null,
                    duration_minutes: currentBookingDurationMinutes(),
                    flow_token: flowToken,
                }),
            })
            .then(async (response) => {
                const data = await response.json();
                button.disabled = false;
                button.innerHTML = originalHtml;

                if (!response.ok || !data.success) {
                    if (response.status === 409) {
                        showBookingErrorBanner(data.message || 'That time was just taken. Please choose another slot.');
                        loadBookingSlots();
                        return;
                    }

                    showBookingErrorBanner(data.message || 'Could not confirm booking.');
                    return;
                }

                if (data.requires_action && data.invoice_public_uuid) {
                    pollBookingPayment(data.invoice_public_uuid);
                    return;
                }

                trackCatalogEvent('listing_booking', {
                    item_id: selectedBookingItemId,
                    reservation_id: data.reservation?.id || null,
                });

                showBookingConfirmation(data.reservation || {});
            })
            .catch(() => {
                button.disabled = false;
                button.innerHTML = originalHtml;
                showBookingErrorBanner('Could not confirm booking. Please try again.');
            });
        }

        function submitWhatsAppBooking(details) {
            const endpoint = (bookingMode === 'inquiry' || completionType === 'inquiry')
                ? `/catalog/${catalogId}/generate-inquiry`
                : `/catalog/${catalogId}/generate-booking`;

            const payload = {
                item_id: selectedBookingItemId,
                customerName: details.customerName || null,
                notes: details.notes || null,
                flow_token: flowToken,
            };

            if (bookingMode !== 'inquiry' && completionType !== 'inquiry') {
                payload.customerPhone = details.customerPhone;
                payload.preferredDateTime = details.preferredDateTime || null;
            }

            const button = document.getElementById('bookingSubmitBtn');
            const originalHtml = button.innerHTML;
            button.disabled = true;
            button.innerHTML = '<span class="spinner-border spinner-border-sm mr-2"></span>Sending...';

            fetch(endpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
                body: JSON.stringify(payload),
            })
            .then(response => response.json())
            .then(data => {
                button.disabled = false;
                button.innerHTML = originalHtml;

                if (!data.success) {
                    showBookingErrorBanner(data.message || 'Could not start WhatsApp booking.');
                    return;
                }

                trackCatalogEvent((bookingMode === 'inquiry' || completionType === 'inquiry') ? 'listing_inquiry' : 'listing_booking', {
                    item_id: selectedBookingItemId,
                });

                if (data.whatsapp_url) {
                    window.open(data.whatsapp_url, '_blank');
                    showBookingSuccessBanner('Open WhatsApp and send the message to continue in chat.');
                    return;
                }

                showBookingErrorBanner('WhatsApp number is not configured for this business.');
            })
            .catch(() => {
                button.disabled = false;
                button.innerHTML = originalHtml;
                showBookingErrorBanner('Could not start WhatsApp booking. Please try again.');
            });
        }

        ['bookingCustomerName', 'bookingCustomerPhone', 'bookingPreferredDateTime', 'bookingNotes'].forEach((id) => {
            const input = document.getElementById(id);
            if (!input) {
                return;
            }
            input.addEventListener('input', () => {
                saveBookingDetails();
                const field = input.closest('.delivery-field');
                if (field) {
                    field.classList.remove('has-error');
                }
            });
        });

        loadBookingDetails();

        function listingGalleryGo(cardId, index) {
            const card = document.getElementById(cardId);
            if (!card) return;
            const slides = card.querySelectorAll('.listing-gallery-slide');
            const dots = card.querySelectorAll('.gallery-dot');
            slides.forEach((slide, i) => slide.classList.toggle('active', i === index));
            dots.forEach((dot, i) => dot.classList.toggle('active', i === index));
        }

        function listingGalleryPrev(cardId) {
            const card = document.getElementById(cardId);
            if (!card) return;
            const slides = card.querySelectorAll('.listing-gallery-slide');
            const current = [...slides].findIndex(slide => slide.classList.contains('active'));
            const next = current <= 0 ? slides.length - 1 : current - 1;
            listingGalleryGo(cardId, next);
        }

        function listingGalleryNext(cardId) {
            const card = document.getElementById(cardId);
            if (!card) return;
            const slides = card.querySelectorAll('.listing-gallery-slide');
            const current = [...slides].findIndex(slide => slide.classList.contains('active'));
            const next = current >= slides.length - 1 ? 0 : current + 1;
            listingGalleryGo(cardId, next);
        }

        function useMyLocationForFilter() {
            if (!navigator.geolocation) {
                alert('Location is not supported in this browser.');
                return;
            }

            navigator.geolocation.getCurrentPosition((position) => {
                document.getElementById('filter-near-lat').value = position.coords.latitude;
                document.getElementById('filter-near-lng').value = position.coords.longitude;
                document.getElementById('catalogFiltersForm').submit();
            }, () => alert('Could not access your location.'));
        }

        @if(($presentation['supports_geo_map'] ?? false) && count($mapMarkers ?? []) > 0)
        const listingMapMarkers = @json($mapMarkers);
        @endif
        @endif
    </script>
    @if(($presentation['supports_geo_map'] ?? false) && count($mapMarkers ?? []) > 0)
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            if (!window.L || !listingMapMarkers || listingMapMarkers.length === 0) {
                return;
            }

            const map = L.map('listingMap');
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; OpenStreetMap contributors',
            }).addTo(map);

            const bounds = [];
            listingMapMarkers.forEach((marker) => {
                const popup = `<strong>${marker.title}</strong>`;
                L.marker([marker.lat, marker.lng]).addTo(map).bindPopup(popup);
                bounds.push([marker.lat, marker.lng]);
            });

            if (bounds.length === 1) {
                map.setView(bounds[0], 13);
            } else {
                map.fitBounds(bounds, { padding: [24, 24] });
            }
        });
    </script>
    @endif
</body>
</html>
