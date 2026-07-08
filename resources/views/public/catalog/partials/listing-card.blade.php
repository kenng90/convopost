@php
    $statusField = $presentation['status_field'] ?? 'listing_status';
    $statusValue = $item[$statusField] ?? ($item['metadata'][$statusField] ?? 'Available');
    $statusClass = strtolower(str_replace(' ', '', (string) $statusValue));
    $highlights = $presentation['card_highlights'] ?? [];
    $images = [];

    if (is_array($item['images'] ?? null)) {
        foreach ($item['images'] as $imageUrl) {
            $imageUrl = trim((string) $imageUrl);
            if ($imageUrl !== '') {
                $images[] = $imageUrl;
            }
        }
    }

    if ($images === [] && is_array($item['metadata']['images'] ?? null)) {
        foreach ($item['metadata']['images'] as $imageUrl) {
            $imageUrl = trim((string) $imageUrl);
            if ($imageUrl !== '') {
                $images[] = $imageUrl;
            }
        }
    }

    $primaryImage = trim((string) ($item['imageUrl'] ?? ''));
    if ($primaryImage !== '' && ! in_array($primaryImage, $images, true)) {
        array_unshift($images, $primaryImage);
    }

    $cardId = 'listing-card-' . preg_replace('/[^a-zA-Z0-9_-]/', '', (string) ($item['id'] ?? uniqid()));
@endphp

<div class="col-md-4 col-sm-6 mb-4" id="{{ $cardId }}">
    <div class="product-card">
        <div class="product-image listing-image-gallery" data-card-id="{{ $cardId }}">
            @if(count($images) > 1)
                <div class="listing-gallery-track">
                    @foreach($images as $index => $imageUrl)
                        <div class="listing-gallery-slide @if($index === 0) active @endif" style="background-image: url('{{ $imageUrl }}');"></div>
                    @endforeach
                </div>
                <button type="button" class="gallery-nav gallery-prev" aria-label="Previous image" onclick="listingGalleryPrev('{{ $cardId }}')">
                    <i class="fas fa-chevron-left"></i>
                </button>
                <button type="button" class="gallery-nav gallery-next" aria-label="Next image" onclick="listingGalleryNext('{{ $cardId }}')">
                    <i class="fas fa-chevron-right"></i>
                </button>
                <div class="gallery-dots">
                    @foreach($images as $index => $imageUrl)
                        <span class="gallery-dot @if($index === 0) active @endif" onclick="listingGalleryGo('{{ $cardId }}', {{ $index }})"></span>
                    @endforeach
                </div>
            @elseif(count($images) === 1)
                <div class="listing-gallery-slide active" style="background-image: url('{{ $images[0] }}');"></div>
            @else
                <i class="fas fa-image"></i>
            @endif

            @if($statusValue)
                <span class="stock-badge stock-{{ $statusClass }}">
                    {{ $statusValue }}
                </span>
            @endif
        </div>
        <div class="product-body">
            @if(!empty($item['category']))
                <div class="product-category">{{ $item['category'] }}</div>
            @endif
            <div class="product-title">{{ $item['title'] ?? 'Listing' }}</div>
            <div class="product-description">{{ $item['description'] ?? '' }}</div>

            @if(!empty($highlights))
                <div class="product-tags mb-2">
                    @foreach($highlights as $fieldKey)
                        @php
                            $value = $item[$fieldKey] ?? ($item['metadata'][$fieldKey] ?? null);
                        @endphp
                        @if($value !== null && $value !== '' && $fieldKey !== $statusField)
                            <span class="tag-badge">{{ ucfirst(str_replace('_', ' ', $fieldKey)) }}: {{ $value }}</span>
                        @endif
                    @endforeach
                </div>
            @endif

            @if(!empty($item['tags']) && is_array($item['tags']))
                <div class="product-tags">
                    @foreach($item['tags'] as $tag)
                        <span class="tag-badge">{{ $tag }}</span>
                    @endforeach
                </div>
            @endif

            @if(isset($item['price']) && (float) $item['price'] > 0)
                <div class="product-price">{{ $currencySymbol }} {{ number_format((float) $item['price'], 2) }}</div>
            @endif

            <button
                type="button"
                class="add-to-cart-btn"
                style="background-color: #25D366;"
                onclick="openBookingPanel(@js($item['id']), @js($item['title'] ?? 'Listing'))"
                @if(in_array($statusValue, ['Sold', 'Leased', 'Reserved'], true)) disabled @endif
            >
                @if(in_array($statusValue, ['Sold', 'Leased', 'Reserved'], true))
                    <i class="fas fa-ban mr-2"></i>{{ $statusValue }}
                @else
                    <i class="fab fa-whatsapp mr-2"></i>{{ $presentation['cta_label'] ?? 'Inquire on WhatsApp' }}
                @endif
            </button>
        </div>
    </div>
</div>
