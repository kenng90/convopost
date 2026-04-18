<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $catalog->name }} - Shop</title>
    
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
        
        .stock-low {
            background-color: #ffc107;
            color: #333;
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
        @if($items && count($items) > 0)
            <div class="row">
                @foreach($items as $item)
                    <div class="col-md-4 col-sm-6 mb-4">
                        <div class="product-card">
                            <div class="product-image" @if(isset($item['imageUrl']) && $item['imageUrl']) style="background-image: url('{{ $item['imageUrl'] }}');" @endif>
                                @if(!isset($item['imageUrl']) || !$item['imageUrl'])
                                    <i class="fas fa-image"></i>
                                @endif
                                <span class="stock-badge stock-{{ strtolower(str_replace(' ', '', $item['stockStatus'] ?? 'In Stock')) }}">
                                    {{ $item['stockStatus'] ?? 'In Stock' }}
                                </span>
                            </div>
                            <div class="product-body">
                                @if(isset($item['category']) && $item['category'])
                                    <div class="product-category">{{ $item['category'] }}</div>
                                @endif
                                <div class="product-title">{{ $item['title'] ?? 'Product' }}</div>
                                <div class="product-description">{{ $item['description'] ?? '' }}</div>
                                
                                <!-- Tags -->
                                @if(isset($item['tags']) && is_array($item['tags']) && count($item['tags']) > 0)
                                    <div class="product-tags">
                                        @foreach($item['tags'] as $tag)
                                            <span class="tag-badge">{{ $tag }}</span>
                                        @endforeach
                                    </div>
                                @endif
                                
                                @if(isset($item['price']))
                                    <div class="product-price">${{ number_format((float)$item['price'], 2) }}</div>
                                @endif
                                
                                <!-- Variants Selection -->
                                @if(isset($item['variants']) && is_array($item['variants']) && count($item['variants']) > 0)
                                    <div class="variant-selector">
                                        <label class="variant-label">{{ count($item['variants']) > 1 ? 'Choose Option' : 'Variant' }}</label>
                                        <div class="variant-options" data-product-id="{{ $item['id'] }}">
                                            @foreach($item['variants'] as $variant)
                                                <button type="button" class="variant-option" data-variant="{{ $variant }}" onclick="selectVariant(this, '{{ $item['id'] }}')">
                                                    {{ $variant }}
                                                </button>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif
                                
                                <div class="quantity-selector">
                                    <button class="quantity-btn" onclick="decreaseQty(this)">−</button>
                                    <input type="number" class="quantity-input" value="1" min="1" max="999">
                                    <button class="quantity-btn" onclick="increaseQty(this)">+</button>
                                </div>
                                <button class="add-to-cart-btn" onclick="addToCart('{{ $item['id'] }}', '{{ $item['title'] ?? 'Product' }}', {{ (float)($item['price'] ?? 0) }}, this)" @if(($item['stockStatus'] ?? 'In Stock') === 'Out of Stock') disabled @endif>
                                    @if(($item['stockStatus'] ?? 'In Stock') === 'Out of Stock')
                                        <i class="fas fa-ban mr-2"></i>Out of Stock
                                    @else
                                        <i class="fas fa-shopping-cart mr-2"></i>Add to Cart
                                    @endif
                                </button>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <div class="alert alert-info" role="alert">
                <i class="fas fa-info-circle mr-2"></i>No products available in this catalog yet.
            </div>
        @endif
    </div>

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
            <div class="cart-total">
                <span>Total:</span>
                <span id="cartTotal">$0.00</span>
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                <button class="checkout-btn" id="checkoutBtn" onclick="proceedToCheckout()" disabled style="background-color: #25D366;">
                    <i class="fab fa-whatsapp mr-2"></i>WhatsApp
                </button>
                <button class="checkout-btn" id="invoiceBtn" onclick="generateInvoice()" disabled style="background-color: #007bff;">
                    <i class="fas fa-file-invoice mr-2"></i>Invoice
                </button>
            </div>
        </div>
    </div>

    <!-- Cart Toggle Button -->
    <button class="cart-toggle" id="cartToggle" onclick="toggleCart()">
        <i class="fas fa-shopping-cart"></i>
        <span class="cart-badge" id="cartBadge" style="display: none;">0</span>
    </button>

    <!-- Overlay -->
    <div class="overlay" id="overlay" onclick="toggleCart()"></div>

    <!-- Bootstrap JS -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // Cart state with selected variants
        let cart = JSON.parse(localStorage.getItem('catalog_{{ $catalog->id }}_cart')) || [];
        let selectedVariants = {};
        
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
                cartTotal.textContent = '$0.00';
                checkoutBtn.disabled = true;
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
                        <div class="cart-item-qty">$${price.toFixed(2)} × ${item.quantity} = $${itemTotal.toFixed(2)}</div>
                    </div>
                `;
            });

            cartItemsDiv.innerHTML = html;
            cartTotal.textContent = '$' + total.toFixed(2);
            checkoutBtn.disabled = false;
            document.getElementById('invoiceBtn').disabled = false;
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

            // Generate order message
            let orderMessage = "📦 *Order from Catalog: {{ $catalog->name }}*\n\n";
            orderMessage += "📋 *Items:*\n";
            let total = 0;

            cart.forEach(item => {
                const price = parseFloat(item.price) || 0;
                const itemTotal = price * item.quantity;
                total += itemTotal;

                let itemLine = `• ${item.title}`;
                if (item.variant) {
                    itemLine += ` (${item.variant})`;
                }
                itemLine += ` (x${item.quantity}) - $${price.toFixed(2)} = $${itemTotal.toFixed(2)}\n`;

                orderMessage += itemLine;
            });

            orderMessage += `\n💰 *Total: $${total.toFixed(2)}*\n`;
            orderMessage += "\nPlease confirm this order.";

            // Get company WhatsApp number from data
            const whatsappNumber = "{{ $company->getConfig('whatsapp_phone_number', '') }}";

            if (!whatsappNumber) {
                alert('WhatsApp number not configured for this seller. Please contact the seller directly.');
                return;
            }

            // Create WhatsApp link
            const encodedMessage = encodeURIComponent(orderMessage);
            const whatsappUrl = `https://wa.me/${whatsappNumber}?text=${encodedMessage}`;

            // Open WhatsApp
            window.open(whatsappUrl, '_blank');
        }

        // Generate invoice and redirect to payment
        function generateInvoice() {
            if (cart.length === 0) return;

            // Collect customer info
            const customerName = prompt('Enter your name (optional):', '');
            const customerPhone = prompt('Enter your phone number (required):', '');

            if (!customerPhone) {
                alert('Phone number is required');
                return;
            }

            const customerEmail = prompt('Enter your email (optional):', '');

            // Calculate total
            let total = 0;
            cart.forEach(item => {
                const price = parseFloat(item.price) || 0;
                total += price * item.quantity;
            });

            // Show loading
            const button = document.getElementById('invoiceBtn');
            const originalText = button.textContent;
            button.disabled = true;
            button.innerHTML = '<span class="spinner-border spinner-border-sm mr-2"></span>Creating...';

            // Create invoice
            const csrfToken = document.querySelector('meta[name="csrf-token"]');
            const token = csrfToken ? csrfToken.getAttribute('content') : '';

            fetch('/catalog/{{ $catalog->id }}/create-invoice', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': token,
                },
                body: JSON.stringify({
                    items: cart,
                    customerName: customerName || 'Guest Customer',
                    customerPhone: customerPhone,
                    customerEmail: customerEmail || null,
                    amount: total.toFixed(2),
                })
            })
            .then(response => {
                // Check if response is actually JSON
                const contentType = response.headers.get('content-type');
                if (!contentType || !contentType.includes('application/json')) {
                    return response.text().then(text => {
                        throw new Error('Server returned HTML instead of JSON. Status: ' + response.status);
                    });
                }
                return response.json();
            })
            .then(data => {
                button.disabled = false;
                button.textContent = originalText;

                if (data.success) {
                    // Show success message
                    let successMsg = '✅ Invoice created successfully!';
                    if (data.invoice.whatsapp_sent) {
                        successMsg += '\n📱 Invoice sent to WhatsApp';
                    } else {
                        successMsg += '\n⚠️ Invoice created but WhatsApp not configured';
                    }

                    // Clear cart and close sidebar
                    cart = [];
                    saveCart();
                    updateCartDisplay();
                    toggleCart();

                    // Show message and redirect
                    console.log(successMsg);
                    setTimeout(() => {
                        window.location.href = '/catalog/pay/' + data.invoice.id;
                    }, 1500);
                } else {
                    alert('Error: ' + (data.message || 'Failed to create invoice'));
                }
            })
            .catch(error => {
                button.disabled = false;
                button.textContent = originalText;
                console.error('Invoice creation error:', error);
                alert('Error creating invoice: ' + error.message);
            });
        }
    </script>
</body>
</html>
