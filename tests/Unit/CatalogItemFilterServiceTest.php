<?php

namespace Tests\Unit;

use App\Services\CatalogItemFilterService;
use PHPUnit\Framework\TestCase;

class CatalogItemFilterServiceTest extends TestCase
{
    private CatalogItemFilterService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new CatalogItemFilterService;
    }

    public function test_filters_items_by_search_category_and_stock(): void
    {
        $items = [
            ['id' => '1', 'title' => 'Blue Bowl', 'category' => 'Kitchen', 'stockStatus' => 'In Stock', 'price' => 10],
            ['id' => '2', 'title' => 'Red Mug', 'category' => 'Kitchen', 'stockStatus' => 'Out of Stock', 'price' => 5],
            ['id' => '3', 'title' => 'Dog Leash', 'category' => 'Pets', 'stockStatus' => 'In Stock', 'price' => 20],
        ];

        $filtered = $this->service->applyFilters($items, $this->service->normalizeFilters([
            'q' => 'bowl',
            'category' => 'Kitchen',
            'stock' => 'In Stock',
        ]));

        $this->assertCount(1, $filtered);
        $this->assertSame('1', $filtered[0]['id']);
    }

    public function test_filters_items_by_tag_and_price_range(): void
    {
        $items = [
            ['id' => '1', 'title' => 'A', 'tags' => ['Sale'], 'price' => 100],
            ['id' => '2', 'title' => 'B', 'tags' => ['New'], 'price' => 500],
            ['id' => '3', 'title' => 'C', 'tags' => ['Sale', 'Popular'], 'price' => 250],
        ];

        $filtered = $this->service->applyFilters($items, $this->service->normalizeFilters([
            'tag' => 'Sale',
            'min_price' => 150,
            'max_price' => 300,
        ]));

        $this->assertCount(1, $filtered);
        $this->assertSame('3', $filtered[0]['id']);
    }

    public function test_sorts_items_by_price_descending(): void
    {
        $items = [
            ['id' => '1', 'title' => 'A', 'price' => 10],
            ['id' => '2', 'title' => 'B', 'price' => 30],
            ['id' => '3', 'title' => 'C', 'price' => 20],
        ];

        $sorted = $this->service->sortItems($items, 'price_desc');

        $this->assertSame(['2', '3', '1'], array_column($sorted, 'id'));
    }

    public function test_browse_paginates_filtered_results(): void
    {
        $items = [];
        for ($i = 1; $i <= 30; $i++) {
            $items[] = [
                'id' => (string) $i,
                'title' => "Product {$i}",
                'category' => $i % 2 === 0 ? 'Even' : 'Odd',
                'stockStatus' => 'In Stock',
                'price' => $i,
            ];
        }

        $result = $this->service->browse($items, [
            'category' => 'Even',
            'page' => 2,
            'per_page' => 12,
        ], 'https://example.com/catalog/1');

        $this->assertSame(15, $result['filteredTotal']);
        $this->assertSame(30, $result['totalInCatalog']);
        $this->assertCount(3, $result['items']->items());
        $this->assertSame(2, $result['items']->currentPage());
        $this->assertSame(2, $result['items']->lastPage());
    }

    public function test_filters_items_by_id_search(): void
    {
        $items = [
            ['id' => 'PROD_001', 'title' => 'Alpha'],
            ['id' => 'PROD_002', 'title' => 'Beta'],
        ];

        $filtered = $this->service->applyFilters($items, $this->service->normalizeFilters([
            'q' => 'prod_002',
        ]));

        $this->assertCount(1, $filtered);
        $this->assertSame('PROD_002', $filtered[0]['id']);
    }

    public function test_extracts_unique_categories_and_tags(): void
    {
        $options = $this->service->extractFilterOptions([
            ['category' => 'Pets', 'tags' => ['New', 'Sale']],
            ['category' => 'Kitchen', 'tags' => ['Sale']],
            ['category' => 'Pets', 'tags' => []],
        ]);

        $this->assertSame(['Kitchen', 'Pets'], $options['categories']);
        $this->assertSame(['New', 'Sale'], $options['tags']);
    }
}
