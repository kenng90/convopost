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
        function inquireOnWhatsApp(itemId) {
            const customerName = window.prompt('Your name (optional)') || '';
            const notes = window.prompt('Message or viewing request (optional)') || '';

            fetch(`/catalog/${catalogId}/generate-inquiry`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
                body: JSON.stringify({
                    item_id: itemId,
                    customerName: customerName || null,
                    notes: notes || null,
                    flow_token: flowToken,
                }),
            })
            .then(response => response.json())
            .then(data => {
                if (!data.success) {
                    alert(data.message || 'Could not start WhatsApp inquiry.');
                    return;
                }

                trackCatalogEvent('listing_inquiry', { item_id: itemId });

                if (data.whatsapp_url) {
                    window.open(data.whatsapp_url, '_blank');
                    return;
                }

                alert(data.message || 'WhatsApp number is not configured for this business.');
            })
            .catch(() => alert('Could not start WhatsApp inquiry.'));
        }

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
