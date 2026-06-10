<div class="col-md-4 col-sm-6 mb-4">
    <div class="product-card">
        <div class="product-image" @if(!empty($item['imageUrl'])) style="background-image: url('{{ $item['imageUrl'] }}');" @endif>
            @if(empty($item['imageUrl']))
                <i class="fas fa-image"></i>
            @endif
            <span class="stock-badge stock-{{ strtolower(str_replace(' ', '', $item['stockStatus'] ?? 'In Stock')) }}">
                {{ $item['stockStatus'] ?? 'In Stock' }}
            </span>
        </div>
        <div class="product-body">
            @if(!empty($item['category']))
                <div class="product-category">{{ $item['category'] }}</div>
            @endif
            <div class="product-title">{{ $item['title'] ?? 'Product' }}</div>
            <div class="product-description">{{ $item['description'] ?? '' }}</div>

            @if(!empty($item['tags']) && is_array($item['tags']))
                <div class="product-tags">
                    @foreach($item['tags'] as $tag)
                        <span class="tag-badge">{{ $tag }}</span>
                    @endforeach
                </div>
            @endif

            @if(isset($item['price']))
                <div class="product-price">KSh {{ number_format((float) $item['price'], 2) }}</div>
            @endif

            @if(!empty($item['variants']) && is_array($item['variants']))
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
                <button type="button" class="quantity-btn" onclick="decreaseQty(this)">−</button>
                <input type="number" class="quantity-input" value="1" min="1" max="999">
                <button type="button" class="quantity-btn" onclick="increaseQty(this)">+</button>
            </div>
            <button
                type="button"
                class="add-to-cart-btn"
                onclick="addToCart('{{ $item['id'] }}', {{ json_encode($item['title'] ?? 'Product') }}, {{ (float) ($item['price'] ?? 0) }}, this)"
                @if(($item['stockStatus'] ?? 'In Stock') === 'Out of Stock') disabled @endif
            >
                @if(($item['stockStatus'] ?? 'In Stock') === 'Out of Stock')
                    <i class="fas fa-ban mr-2"></i>Out of Stock
                @else
                    <i class="fas fa-shopping-cart mr-2"></i>Add to Cart
                @endif
            </button>
        </div>
    </div>
</div>
