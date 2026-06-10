<?php

namespace Tests\Unit;

use App\Services\ExcelImportService;
use PHPUnit\Framework\TestCase;

class ExcelImportServiceTest extends TestCase
{
    private ExcelImportService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ExcelImportService;
    }

    public function test_build_column_mapping_from_template_headers(): void
    {
        $mapping = $this->service->buildColumnMappingFromHeaders(ExcelImportService::TEMPLATE_HEADERS);

        $this->assertSame('Item ID', $mapping['id']);
        $this->assertSame('Title', $mapping['title']);
        $this->assertSame('Description', $mapping['description']);
        $this->assertSame('Price', $mapping['price']);
        $this->assertSame('Category', $mapping['category']);
        $this->assertSame('Image URL', $mapping['imageUrl']);
        $this->assertSame('Stock Status', $mapping['stockStatus']);
        $this->assertSame('Variants', $mapping['variants']);
        $this->assertSame('Tags', $mapping['tags']);
    }

    public function test_build_column_mapping_supports_common_aliases(): void
    {
        $headers = ['SKU', 'Product Name', 'Cost', 'Tags'];
        $mapping = $this->service->buildColumnMappingFromHeaders($headers);

        $this->assertSame('SKU', $mapping['id']);
        $this->assertSame('Product Name', $mapping['title']);
        $this->assertSame('Cost', $mapping['price']);
        $this->assertSame('Tags', $mapping['tags']);
    }

    public function test_transform_items_parses_ksh_price_values(): void
    {
        $items = [[
            'Item ID' => 'PROD_002',
            'Title' => 'Mug',
            'Price' => 'KSh 1,250.00',
        ]];

        $mapping = $this->service->buildColumnMappingFromHeaders(array_keys($items[0]));
        $transformed = $this->service->transformItems($items, $mapping);

        $this->assertSame(1250.0, $transformed[0]['price']);
    }

    public function test_transform_items_maps_all_catalog_fields(): void
    {
        $items = [[
            'Item ID' => 'PROD_001',
            'Title' => 'Blue Bowl',
            'Description' => 'A nice bowl',
            'Price' => '$24.50',
            'Category' => 'Kitchen',
            'Image URL' => 'https://example.com/bowl.jpg',
            'Stock Status' => 'Low Stock',
            'Variants' => 'S, M, L',
            'Tags' => 'New, Sale',
        ]];

        $mapping = $this->service->buildColumnMappingFromHeaders(array_keys($items[0]));
        $transformed = $this->service->transformItems($items, $mapping);

        $this->assertCount(1, $transformed);
        $this->assertSame([
            'id' => 'PROD_001',
            'title' => 'Blue Bowl',
            'description' => 'A nice bowl',
            'price' => 24.5,
            'category' => 'Kitchen',
            'imageUrl' => 'https://example.com/bowl.jpg',
            'stockStatus' => 'Low Stock',
            'variants' => ['S', 'M', 'L'],
            'tags' => ['New', 'Sale'],
        ], $transformed[0]);
    }

    public function test_transform_items_skips_rows_without_title(): void
    {
        $items = [
            ['Item ID' => 'A', 'Title' => 'Keep'],
            ['Item ID' => 'B', 'Title' => ''],
        ];

        $mapping = $this->service->buildColumnMappingFromHeaders(['Item ID', 'Title']);
        $transformed = $this->service->transformItems($items, $mapping);

        $this->assertCount(1, $transformed);
        $this->assertSame('Keep', $transformed[0]['title']);
    }

    public function test_assert_required_columns_mapped_throws_when_title_missing(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Missing required column(s): Title');

        $this->service->assertRequiredColumnsMapped(['id' => 'Item ID']);
    }

    public function test_template_headers_match_expected_columns(): void
    {
        $this->assertSame([
            'Item ID',
            'Title',
            'Description',
            'Price',
            'Category',
            'Image URL',
            'Stock Status',
            'Variants',
            'Tags',
        ], ExcelImportService::TEMPLATE_HEADERS);
    }
}
