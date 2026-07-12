<?php

namespace Tests\Unit;

use App\Http\Requests\PublicCatalogBrowseRequest;
use App\Services\CatalogItemFilterService;
use Illuminate\Http\Request;
use Tests\TestCase;

class CatalogVerticalFilterFacetsTest extends TestCase
{
    public function test_browse_request_includes_common_facet_filters(): void
    {
        $base = Request::create('/catalog/1', 'GET', [
            'make' => 'Toyota',
            'model' => 'RAV4',
            'year' => '2021',
            'bedrooms' => '3',
            'bathrooms' => '2',
            'property_type' => 'Apartment',
            'fuel_type' => 'Petrol',
            'availability' => 'Available',
            'duration' => '60 min',
        ]);

        $request = PublicCatalogBrowseRequest::createFrom($base);
        $request->setContainer($this->app);
        $request->validateResolved();

        $filters = $request->filters();

        $this->assertSame('Toyota', $filters['make']);
        $this->assertSame('RAV4', $filters['model']);
        $this->assertSame('2021', $filters['year']);
        $this->assertSame('3', $filters['bedrooms']);
        $this->assertSame('2', $filters['bathrooms']);
        $this->assertSame('Apartment', $filters['property_type']);
        $this->assertSame('Petrol', $filters['fuel_type']);
        $this->assertSame('Available', $filters['availability']);
        $this->assertSame('60 min', $filters['duration']);
    }

    public function test_filter_service_exposes_vertical_facet_options(): void
    {
        $service = new CatalogItemFilterService;
        $presentation = [
            'status_field' => 'listing_status',
            'item_fields' => [
                ['key' => 'make', 'label' => 'Make', 'filterable' => true],
                ['key' => 'fuel_type', 'label' => 'Fuel', 'filterable' => true],
            ],
            'filter_facets' => ['category', 'tag', 'status', 'price', 'make'],
        ];

        $options = $service->extractFilterOptions([
            [
                'id' => '1',
                'title' => 'Car A',
                'category' => 'SUV',
                'make' => 'Toyota',
                'fuel_type' => 'Petrol',
                'listing_status' => 'Available',
            ],
            [
                'id' => '2',
                'title' => 'Car B',
                'category' => 'Sedan',
                'make' => 'Honda',
                'fuel_type' => 'Hybrid',
                'listing_status' => 'Sold',
            ],
        ], $presentation);

        $this->assertSame(['Honda', 'Toyota'], $options['facets']['make']);
        $this->assertSame(['Hybrid', 'Petrol'], $options['facets']['fuel_type']);

        $filtered = $service->applyFilters([
            ['id' => '1', 'title' => 'Car A', 'make' => 'Toyota', 'listing_status' => 'Available'],
            ['id' => '2', 'title' => 'Car B', 'make' => 'Honda', 'listing_status' => 'Available'],
        ], $service->normalizeFilters(['make' => 'Toyota'], $presentation), $presentation);

        $this->assertCount(1, $filtered);
        $this->assertSame('1', $filtered[0]['id']);
    }
}
