@php
    $vertical = $presentation['vertical'] ?? '';
    $mode = $presentation['mode'] ?? '';
    $statusField = $presentation['status_field'] ?? 'listing_status';
    $statusValue = $item[$statusField] ?? ($item['metadata'][$statusField] ?? 'Available');
    $statusClass = strtolower(str_replace(' ', '', (string) $statusValue));
    $highlights = $presentation['card_highlights'] ?? [];
    $images = [];

    $fieldValue = static function (array $item, string $key) {
        $value = $item[$key] ?? ($item['metadata'][$key] ?? null);

        return ($value !== null && $value !== '') ? $value : null;
    };

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

    $displayTitle = $item['title'] ?? 'Listing';
    if ($vertical === 'automotive') {
        $year = $fieldValue($item, 'year');
        $make = $fieldValue($item, 'make');
        $model = $fieldValue($item, 'model');
        if ($year || $make || $model) {
            $displayTitle = trim(implode(' ', array_filter([$year, $make, $model], static fn ($part) => $part !== null && $part !== '')));
        }
    }

    $hardDisabledStatuses = ['Sold', 'Leased', 'Reserved', 'Fully Booked'];
    $isHardDisabled = in_array($statusValue, $hardDisabledStatuses, true);
    $isUnderOffer = $statusValue === 'Under Offer';
    $bookDisabled = $isHardDisabled || $isUnderOffer;
    $inquireDisabled = false;
    $inquireCtaLabel = $presentation['inquire_cta_label'] ?? 'Inquire';
    $bookCtaLabel = $presentation['book_cta_label'] ?? ($presentation['cta_label'] ?? 'Book');
    $cardCtaLabel = $bookDisabled
        ? (string) $statusValue
        : $bookCtaLabel;

    $skipHighlightKeys = [$statusField];
    if ($vertical === 'automotive') {
        $skipHighlightKeys = array_merge($skipHighlightKeys, ['make', 'model', 'year', 'mileage', 'fuel_type']);
    } elseif ($vertical === 'real_estate') {
        $skipHighlightKeys = array_merge($skipHighlightKeys, ['bedrooms', 'bathrooms', 'area_sqm']);
    } elseif ($vertical === 'general_service' || $mode === 'service') {
        $skipHighlightKeys = array_merge($skipHighlightKeys, ['duration', 'availability']);
    }

    $cardId = 'listing-card-' . preg_replace('/[^a-zA-Z0-9_-]/', '', (string) ($item['id'] ?? uniqid()));

    $drawerPayload = [
        'id' => (string) ($item['id'] ?? ''),
        'title' => $displayTitle,
        'description' => (string) ($item['description'] ?? ''),
        'category' => (string) ($item['category'] ?? ''),
        'price' => isset($item['price']) ? (float) $item['price'] : null,
        'images' => $images,
        'status' => (string) $statusValue,
        'bookDisabled' => $bookDisabled,
        'inquireDisabled' => $inquireDisabled,
        'bookCtaLabel' => $bookCtaLabel,
        'inquireCtaLabel' => $inquireCtaLabel,
        'statusLabel' => (string) $statusValue,
        'highlights' => [],
    ];

    if ($vertical === 'automotive') {
        $mileage = $fieldValue($item, 'mileage');
        if ($mileage !== null) {
            $drawerPayload['highlights'][] = is_numeric($mileage)
                ? number_format((float) $mileage).' km'
                : (string) $mileage;
        }
        $fuel = $fieldValue($item, 'fuel_type');
        if ($fuel !== null) {
            $drawerPayload['highlights'][] = (string) $fuel;
        }
    } elseif ($vertical === 'real_estate') {
        $beds = $fieldValue($item, 'bedrooms');
        $baths = $fieldValue($item, 'bathrooms');
        $area = $fieldValue($item, 'area_sqm');
        $parts = [];
        if ($beds !== null) {
            $parts[] = $beds.' bed';
        }
        if ($baths !== null) {
            $parts[] = $baths.' bath';
        }
        if ($area !== null) {
            $areaLabel = is_numeric($area)
                ? rtrim(rtrim(number_format((float) $area, 1), '0'), '.')
                : $area;
            $parts[] = $areaLabel.' m²';
        }
        if ($parts !== []) {
            $drawerPayload['highlights'][] = implode(' · ', $parts);
        }
        $location = $fieldValue($item, 'location');
        if ($location !== null) {
            $drawerPayload['highlights'][] = (string) $location;
        }
        $propertyType = $fieldValue($item, 'property_type');
        if ($propertyType !== null) {
            $drawerPayload['highlights'][] = (string) $propertyType;
        }
    } elseif ($vertical === 'general_service' || $mode === 'service') {
        $duration = $fieldValue($item, 'duration');
        if ($duration !== null) {
            $drawerPayload['highlights'][] = (string) $duration;
        }
        $availability = $fieldValue($item, 'availability');
        if ($availability !== null) {
            $drawerPayload['highlights'][] = (string) $availability;
        }
    }

    foreach ($highlights as $fieldKey) {
        if (in_array($fieldKey, $skipHighlightKeys, true)) {
            continue;
        }
        $value = $fieldValue($item, $fieldKey);
        if ($value === null) {
            continue;
        }
        $label = collect($presentation['item_fields'] ?? [])->firstWhere('key', $fieldKey)['label']
            ?? ucfirst(str_replace('_', ' ', $fieldKey));
        $drawerPayload['highlights'][] = $label.': '.$value;
    }
@endphp

<div class="col-md-4 col-sm-6 mb-4" id="{{ $cardId }}">
    <div class="product-card">
        <div
            class="product-image listing-image-gallery listing-open-detail"
            data-card-id="{{ $cardId }}"
            role="button"
            tabindex="0"
            onclick="openItemDetailDrawer(@js($drawerPayload))"
            onkeydown="if(event.key==='Enter'||event.key===' '){event.preventDefault();openItemDetailDrawer(@js($drawerPayload));}"
        >
            @if(count($images) > 1)
                <div class="listing-gallery-track">
                    @foreach($images as $index => $imageUrl)
                        <div class="listing-gallery-slide @if($index === 0) active @endif" style="background-image: url('{{ $imageUrl }}');"></div>
                    @endforeach
                </div>
                <button type="button" class="gallery-nav gallery-prev" aria-label="Previous image" onclick="event.stopPropagation();listingGalleryPrev('{{ $cardId }}')">
                    <i class="fas fa-chevron-left"></i>
                </button>
                <button type="button" class="gallery-nav gallery-next" aria-label="Next image" onclick="event.stopPropagation();listingGalleryNext('{{ $cardId }}')">
                    <i class="fas fa-chevron-right"></i>
                </button>
                <div class="gallery-dots">
                    @foreach($images as $index => $imageUrl)
                        <span class="gallery-dot @if($index === 0) active @endif" onclick="event.stopPropagation();listingGalleryGo('{{ $cardId }}', {{ $index }})"></span>
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
            <div
                class="product-title listing-open-detail"
                role="button"
                tabindex="0"
                onclick="openItemDetailDrawer(@js($drawerPayload))"
                onkeydown="if(event.key==='Enter'||event.key===' '){event.preventDefault();openItemDetailDrawer(@js($drawerPayload));}"
            >{{ $displayTitle }}</div>
            <div class="product-description">{{ $item['description'] ?? '' }}</div>

            @if($vertical === 'automotive')
                <div class="product-tags mb-2">
                    @php $mileage = $fieldValue($item, 'mileage'); @endphp
                    @if($mileage !== null)
                        <span class="tag-badge">{{ is_numeric($mileage) ? number_format((float) $mileage).' km' : $mileage }}</span>
                    @endif
                    @php $fuel = $fieldValue($item, 'fuel_type'); @endphp
                    @if($fuel !== null)
                        <span class="tag-badge">{{ $fuel }}</span>
                    @endif
                    @foreach($highlights as $fieldKey)
                        @if(in_array($fieldKey, $skipHighlightKeys, true))
                            @continue
                        @endif
                        @php $value = $fieldValue($item, $fieldKey); @endphp
                        @if($value !== null)
                            <span class="tag-badge">{{ ucfirst(str_replace('_', ' ', $fieldKey)) }}: {{ $value }}</span>
                        @endif
                    @endforeach
                </div>
            @elseif($vertical === 'real_estate')
                @php
                    $beds = $fieldValue($item, 'bedrooms');
                    $baths = $fieldValue($item, 'bathrooms');
                    $area = $fieldValue($item, 'area_sqm');
                    $reParts = [];
                    if ($beds !== null) {
                        $reParts[] = $beds.' bed';
                    }
                    if ($baths !== null) {
                        $reParts[] = $baths.' bath';
                    }
                    if ($area !== null) {
                        $areaLabel = is_numeric($area)
                            ? rtrim(rtrim(number_format((float) $area, 1), '0'), '.')
                            : $area;
                        $reParts[] = $areaLabel.' m²';
                    }
                @endphp
                @if($reParts !== [])
                    <div class="listing-highlight mb-2">{{ implode(' · ', $reParts) }}</div>
                @endif
                <div class="product-tags mb-2">
                    @foreach($highlights as $fieldKey)
                        @if(in_array($fieldKey, $skipHighlightKeys, true))
                            @continue
                        @endif
                        @php $value = $fieldValue($item, $fieldKey); @endphp
                        @if($value !== null)
                            <span class="tag-badge">{{ $value }}</span>
                        @endif
                    @endforeach
                </div>
            @elseif($vertical === 'general_service' || $mode === 'service')
                <div class="product-tags mb-2">
                    @php $duration = $fieldValue($item, 'duration'); @endphp
                    @if($duration !== null)
                        <span class="tag-badge duration-badge"><i class="far fa-clock mr-1"></i>{{ $duration }}</span>
                    @endif
                    @php $availability = $fieldValue($item, 'availability'); @endphp
                    @if($availability !== null)
                        <span class="tag-badge availability-badge">{{ $availability }}</span>
                    @endif
                    @foreach($highlights as $fieldKey)
                        @if(in_array($fieldKey, $skipHighlightKeys, true))
                            @continue
                        @endif
                        @php $value = $fieldValue($item, $fieldKey); @endphp
                        @if($value !== null)
                            <span class="tag-badge">{{ ucfirst(str_replace('_', ' ', $fieldKey)) }}: {{ $value }}</span>
                        @endif
                    @endforeach
                </div>
            @elseif(!empty($highlights))
                <div class="product-tags mb-2">
                    @foreach($highlights as $fieldKey)
                        @php $value = $fieldValue($item, $fieldKey); @endphp
                        @if($value !== null && $fieldKey !== $statusField)
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
                <div class="product-price">{{ $currencySymbol ?? 'KES' }} {{ number_format((float) $item['price'], 2) }}</div>
            @endif

            <button
                type="button"
                class="btn btn-link btn-sm p-0 mb-2 align-self-start listing-view-details"
                onclick="openItemDetailDrawer(@js($drawerPayload))"
            >
                View details
            </button>

            <button
                type="button"
                class="add-to-cart-btn{{ $isUnderOffer ? ' cta-soft-disabled' : '' }}"
                style="background-color: #25D366;"
                onclick="openBookingPanel(@js($item['id']), @js($displayTitle), this, { intent: 'book' })"
                @if($bookDisabled) disabled @endif
            >
                @if($bookDisabled)
                    <i class="fas fa-ban mr-2"></i>{{ $cardCtaLabel }}
                @else
                    <i class="fas fa-calendar-check mr-2"></i>{{ $cardCtaLabel }}
                @endif
            </button>
        </div>
    </div>
</div>
