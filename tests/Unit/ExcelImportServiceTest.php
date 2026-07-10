<?php

namespace Tests\Unit;

use App\Services\ExcelImportService;
use Tests\TestCase;

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
            'tags' => ['New', 'Sale'],
            'stockStatus' => 'Low Stock',
            'variants' => ['S', 'M', 'L'],
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

    public function test_transform_items_maps_real_estate_vertical_fields_into_metadata(): void
    {
        $items = [[
            'Item ID' => 'HOME_001',
            'Title' => 'Karen Apartment',
            'Price' => '15000000',
            'Location' => 'Karen',
            'Bedrooms' => '3',
            'Status' => 'Available',
        ]];

        $mapping = $this->service->buildColumnMappingFromHeaders(array_keys($items[0]), 'real_estate');
        $transformed = $this->service->transformItems($items, $mapping, 'real_estate');

        $this->assertSame('Karen', $transformed[0]['metadata']['location']);
        $this->assertSame('3', $transformed[0]['metadata']['bedrooms']);
        $this->assertSame('Available', $transformed[0]['metadata']['listing_status']);
    }

    public function test_import_template_filename_includes_mode_and_vertical(): void
    {
        $registry = app(\App\Services\Catalog\CatalogTemplateRegistry::class);

        $this->assertSame('catalog-import-commerce-retail.xlsx', $registry->importTemplateFilename('commerce', 'retail'));
        $this->assertSame('catalog-import-listing-real-estate.xlsx', $registry->importTemplateFilename('listing', 'real_estate'));
        $this->assertSame('catalog-import-service-general-service.xlsx', $registry->importTemplateFilename('service', 'general_service'));
    }

    public function test_template_spreadsheet_includes_five_sample_rows_for_each_vertical(): void
    {
        foreach (['retail', 'real_estate', 'automotive', 'general_listing', 'general_service'] as $vertical) {
            $spreadsheet = $this->service->createTemplateSpreadsheet($vertical);
            $sheet = $spreadsheet->getActiveSheet();

            $this->assertSame(
                1 + ExcelImportService::TEMPLATE_SAMPLE_ROW_COUNT,
                (int) $sheet->getHighestRow(),
                "Expected header + 5 sample rows for vertical [{$vertical}]"
            );

            $this->assertNotSame('', (string) $sheet->getCell([1, 2])->getValue());
            $this->assertNotSame('', (string) $sheet->getCell([2, 2])->getValue());
        }
    }

    public function test_template_headers_match_registry_for_listing_vertical(): void
    {
        $registry = app(\App\Services\Catalog\CatalogTemplateRegistry::class);
        $expected = $registry->excelHeadersForVertical('real_estate');
        $sheet = $this->service->createTemplateSpreadsheet('real_estate')->getActiveSheet();

        $headers = [];
        foreach ($expected as $index => $header) {
            $headers[] = (string) $sheet->getCell([$index + 1, 1])->getValue();
        }

        $this->assertSame($expected, $headers);
        $this->assertContains('Bookable service', $headers);
    }

    public function test_transform_items_maps_bookable_service_name_into_metadata(): void
    {
        $items = [[
            'Item ID' => 'SVC_001',
            'Title' => 'Home Deep Cleaning',
            'Bookable service' => 'Home Deep Cleaning',
        ]];

        $mapping = $this->service->buildColumnMappingFromHeaders(array_keys($items[0]), 'general_service');
        $transformed = $this->service->transformItems($items, $mapping, 'general_service');

        $this->assertSame('Home Deep Cleaning', $transformed[0]['metadata']['booking_source_name']);
    }

    public function test_transform_items_maps_numeric_bookable_service_to_source_id(): void
    {
        $items = [[
            'Item ID' => 'SVC_001',
            'Title' => 'Home Deep Cleaning',
            'Bookable service' => '42',
        ]];

        $mapping = $this->service->buildColumnMappingFromHeaders(array_keys($items[0]), 'general_service');
        $transformed = $this->service->transformItems($items, $mapping, 'general_service');

        $this->assertSame(42, $transformed[0]['metadata']['booking_source_id']);
        $this->assertArrayNotHasKey('booking_source_name', $transformed[0]['metadata']);
    }

    public function test_template_sample_rows_include_bookable_service_for_service_vertical(): void
    {
        $registry = app(\App\Services\Catalog\CatalogTemplateRegistry::class);
        $headers = $registry->excelHeadersForVertical('general_service');
        $bookableColumn = array_search('Bookable service', $headers, true);
        $this->assertNotFalse($bookableColumn);

        $sheet = $this->service->createTemplateSpreadsheet('general_service')->getActiveSheet();
        $sampleValue = (string) $sheet->getCell([$bookableColumn + 1, 2])->getValue();

        $this->assertSame('Home Deep Cleaning', $sampleValue);
    }
}
