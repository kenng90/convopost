<?php

namespace Tests\Unit;

use App\Services\CatalogItemFilterService;
use Tests\TestCase;

class CatalogGeoFilterTest extends TestCase
{
    public function test_nearby_filter_limits_listings_by_radius(): void
    {
        $service = app(CatalogItemFilterService::class);

        $items = [
            [
                'id' => 'near',
                'title' => 'Karen Home',
                'metadata' => ['latitude' => -1.3197, 'longitude' => 36.7080],
            ],
            [
                'id' => 'far',
                'title' => 'Mombasa Home',
                'metadata' => ['latitude' => -4.0435, 'longitude' => 39.6682],
            ],
        ];

        $filtered = $service->applyFilters($items, [
            'q' => '',
            'category' => '',
            'stock' => '',
            'status' => '',
            'location' => '',
            'tag' => '',
            'min_price' => null,
            'max_price' => null,
            'near_lat' => -1.3197,
            'near_lng' => 36.7080,
            'radius_km' => 10.0,
        ]);

        $this->assertCount(1, $filtered);
        $this->assertSame('near', $filtered[0]['id']);
    }

    public function test_map_markers_include_geocoded_items_only(): void
    {
        $service = app(CatalogItemFilterService::class);

        $markers = $service->mapMarkers([
            ['id' => 'a', 'title' => 'A', 'latitude' => -1.2, 'longitude' => 36.8],
            ['id' => 'b', 'title' => 'B'],
        ]);

        $this->assertCount(1, $markers);
        $this->assertSame('a', $markers[0]['id']);
    }
}
