<?php

namespace App\Services;

use App\Services\Catalog\CatalogTemplateRegistry;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ExcelImportService
{
    public const TEMPLATE_SAMPLE_ROW_COUNT = 10;

    /**
     * Canonical spreadsheet column headers (row 1 of the import template).
     *
     * @var list<string>
     */
    public const TEMPLATE_HEADERS = [
        'Item ID',
        'Title',
        'Description',
        'Price',
        'Category',
        'Image URL',
        'Stock Status',
        'Variants',
        'Tags',
    ];

    /**
     * @var array<string, list<string>>
     */
    private const HEADER_ALIASES = [
        'id' => ['itemid', 'id', 'sku', 'productid', 'item_id', 'product_id', 'code', 'reference'],
        'title' => ['title', 'name', 'product', 'productname', 'itemname', 'item'],
        'description' => ['description', 'desc', 'details', 'notes', 'remarks'],
        'price' => ['price', 'cost', 'amount', 'rate', 'fee', 'value'],
        'category' => ['category', 'type', 'class', 'group'],
        'imageUrl' => ['imageurl', 'image', 'imgurl', 'picture', 'photo', 'img'],
        'stockStatus' => ['stockstatus', 'stock', 'availability', 'instock'],
        'variants' => ['variants', 'variant', 'sizes', 'options', 'size'],
        'tags' => ['tags', 'tag', 'labels', 'label'],
    ];

    private const ALLOWED_STOCK_STATUSES = ['In Stock', 'Out of Stock', 'Low Stock'];

    /**
     * Parse Excel file and return structured data
     */
    public function parseExcel($filePath): array
    {
        try {
            $spreadsheet = IOFactory::load($filePath);
            $worksheet = $spreadsheet->getActiveSheet();

            $rows = [];
            foreach ($worksheet->getRowIterator() as $row) {
                $cellIterator = $row->getCellIterator();
                $cellIterator->setIterateOnlyExistingCells(false);

                $rowData = [];
                foreach ($cellIterator as $cell) {
                    $rowData[] = $cell->getValue();
                }

                if (! empty(array_filter($rowData, fn ($value) => $value !== null && $value !== ''))) {
                    $rows[] = $rowData;
                }
            }

            if (empty($rows)) {
                throw new \Exception('Excel file is empty');
            }

            $headers = array_shift($rows);
            $headers = array_map(fn ($header) => is_string($header) ? trim($header) : trim((string) $header), $headers);

            $items = array_map(function ($row) use ($headers) {
                $padded = array_pad(array_slice($row, 0, count($headers)), count($headers), null);

                return array_combine($headers, $padded);
            }, $rows);

            $columnMapping = $this->buildColumnMappingFromHeaders($headers);

            return [
                'items' => $items,
                'columns' => [
                    'detected' => $columnMapping,
                    'available' => $headers,
                ],
                'column_mapping' => $columnMapping,
                'total_count' => count($items),
                'headers' => $headers,
            ];

        } catch (\Exception $e) {
            Log::error('Error parsing Excel file', [
                'error' => $e->getMessage(),
                'file' => $filePath,
            ]);
            throw new \Exception('Failed to parse Excel file: '.$e->getMessage());
        }
    }

    /**
     * Map internal field names to the spreadsheet header labels found in the file.
     *
     * @param  list<string>  $headers
     * @return array<string, string>
     */
    public function buildColumnMappingFromHeaders(array $headers, ?string $vertical = null): array
    {
        $mapping = [];

        foreach ($headers as $header) {
            if ($header === '') {
                continue;
            }

            $field = $this->resolveFieldFromHeader($header, $vertical);
            if ($field !== null && ! isset($mapping[$field])) {
                $mapping[$field] = $header;
            }
        }

        return $mapping;
    }

    /**
     * @throws \Exception
     */
    public function assertRequiredColumnsMapped(array $columnMapping, ?string $vertical = null): void
    {
        $missing = [];
        if (! isset($columnMapping['id'])) {
            $missing[] = 'Item ID';
        }
        if (! isset($columnMapping['title'])) {
            $missing[] = 'Title';
        }

        if ($missing !== []) {
            $headers = app(CatalogTemplateRegistry::class)->excelHeadersForVertical($vertical);
            throw new \Exception(
                'Missing required column(s): '.implode(', ', $missing).'. '.
                'Use the catalog import template with headers: '.implode(', ', $headers)
            );
        }
    }

    /**
     * Transform items using column mapping
     *
     * @param  list<array<string, mixed>>  $items
     * @param  array<string, string>  $columnMapping
     * @return list<array<string, mixed>>
     */
    public function transformItems(array $items, array $columnMapping, ?string $vertical = null): array
    {
        try {
            $this->assertRequiredColumnsMapped($columnMapping, $vertical);
            $registry = app(CatalogTemplateRegistry::class);
            $verticalConfig = $vertical && $registry->verticalExists($vertical)
                ? $registry->vertical($vertical)
                : null;
            $isListingLike = $verticalConfig && ($verticalConfig['mode'] ?? '') !== 'commerce';

            $transformed = array_map(function ($item) use ($columnMapping, $verticalConfig, $isListingLike) {
                $title = $this->cellValue($item, $columnMapping, 'title');
                if ($title === '') {
                    return null;
                }

                $row = [
                    'id' => $this->cellValue($item, $columnMapping, 'id') ?: uniqid('item_'),
                    'title' => $title,
                    'description' => $this->cellValue($item, $columnMapping, 'description'),
                    'price' => $this->parsePrice($this->cellValue($item, $columnMapping, 'price')),
                    'category' => $this->cellValue($item, $columnMapping, 'category'),
                    'imageUrl' => $this->cellValue($item, $columnMapping, 'imageUrl'),
                    'tags' => $this->parseListCell($this->cellValue($item, $columnMapping, 'tags')),
                ];

                $galleryCell = $this->cellValue($item, $columnMapping, 'images');
                if ($galleryCell !== '') {
                    $row['images'] = $this->parseListCell($galleryCell);
                    if (($row['imageUrl'] ?? '') === '' && $row['images'] !== []) {
                        $row['imageUrl'] = $row['images'][0];
                    }
                }

                if ($isListingLike) {
                    $metadata = [];
                    foreach ($verticalConfig['item_fields'] ?? [] as $field) {
                        $key = (string) ($field['key'] ?? '');
                        if ($key === '') {
                            continue;
                        }

                        $value = $this->cellValue($item, $columnMapping, $key);
                        if ($value !== '') {
                            if ($key === 'booking_source_id' && is_numeric($value)) {
                                $metadata[$key] = (int) $value;
                            } elseif ($key === 'booking_source_name' && is_numeric($value)) {
                                $metadata['booking_source_id'] = (int) $value;
                            } else {
                                $metadata[$key] = $value;
                            }
                        }
                    }

                    if ($metadata !== []) {
                        $row['metadata'] = $metadata;
                        foreach ($metadata as $key => $value) {
                            $row[$key] = $value;
                        }
                    }

                    $row['stockStatus'] = 'In Stock';
                    $row['variants'] = [];
                } else {
                    $row['stockStatus'] = $this->parseStockStatus($this->cellValue($item, $columnMapping, 'stockStatus'));
                    $row['variants'] = $this->parseListCell($this->cellValue($item, $columnMapping, 'variants'));
                }

                return $row;
            }, $items);

            return array_values(array_filter($transformed, fn ($row) => $row !== null));

        } catch (\Exception $e) {
            Log::error('Error transforming items', [
                'error' => $e->getMessage(),
                'mapping' => $columnMapping,
            ]);
            throw new \Exception('Failed to transform items: '.$e->getMessage());
        }
    }

    /**
     * Validate transformed items
     */
    public function validateItems(array $items): bool
    {
        if (! is_array($items) || empty($items)) {
            throw new \Exception('Items must be a non-empty array');
        }

        foreach ($items as $idx => $item) {
            if (! isset($item['title']) || $item['title'] === '') {
                throw new \Exception("Item #{$idx}: missing or empty title field");
            }

            if (! isset($item['id']) || $item['id'] === '') {
                throw new \Exception("Item #{$idx}: missing or empty id field");
            }
        }

        return true;
    }

    /**
     * @return list<string>
     */
    public function getColumnsFromItems(array $items): array
    {
        if (empty($items)) {
            return [];
        }

        return array_keys($items[0]);
    }

    /**
     * Preview items with pagination
     */
    public function previewItems(array $items, int $limit = 5): array
    {
        $slice = array_slice($items, 0, $limit);

        return [
            'items' => $slice,
            'total_count' => count($items),
            'showing' => count($slice),
            'has_more' => count($items) > $limit,
        ];
    }

    public function createTemplateSpreadsheet(?string $vertical = null): Spreadsheet
    {
        $registry = app(CatalogTemplateRegistry::class);
        $headers = $registry->excelHeadersForVertical($vertical);

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Catalog Items');

        foreach ($headers as $index => $header) {
            $sheet->setCellValue([$index + 1, 1], $header);
        }

        $exampleRows = $this->exampleRowsForVertical($vertical, $headers);
        foreach ($exampleRows as $rowIndex => $row) {
            foreach ($row as $columnIndex => $value) {
                $sheet->setCellValue([$columnIndex + 1, $rowIndex + 2], $value);
            }
        }

        return $spreadsheet;
    }

    public function writeTemplateToPath(string $filePath, ?string $vertical = null): void
    {
        $writer = new Xlsx($this->createTemplateSpreadsheet($vertical));
        $writer->save($filePath);
    }

    /**
     * @param  list<string>  $headers
     * @return list<list<string>>
     */
    private function exampleRowsForVertical(?string $vertical, array $headers): array
    {
        $verticalKey = $vertical ?: 'retail';
        $samples = $this->sampleItemsByVertical()[$verticalKey]
            ?? $this->sampleItemsByVertical()['retail'];

        $rows = [];
        foreach (array_slice($samples, 0, self::TEMPLATE_SAMPLE_ROW_COUNT) as $sample) {
            $rows[] = array_map(fn (string $header) => (string) ($sample[$header] ?? ''), $headers);
        }

        while (count($rows) < self::TEMPLATE_SAMPLE_ROW_COUNT) {
            $rows[] = array_map(fn (string $header) => '', $headers);
        }

        return $rows;
    }

    /**
     * @return array<string, list<array<string, string>>>
     */
    private function sampleItemsByVertical(): array
    {
        return [
            'retail' => [
                [
                    'Item ID' => 'PROD_001',
                    'Title' => 'Stainless Steel Dog Bowl',
                    'Description' => 'Non-slip base, dishwasher safe, ideal for medium breeds',
                    'Price' => '2499',
                    'Category' => 'Pet Supplies',
                    'Image URL' => 'https://images.unsplash.com/photo-1589924691995-400dc9ecc119?auto=format&fit=crop&w=800',
                    'Stock Status' => 'In Stock',
                    'Variants' => 'S, M, L',
                    'Tags' => 'Featured, Pets',
                ],
                [
                    'Item ID' => 'PROD_002',
                    'Title' => 'Ceramic Pour-Over Coffee Mug',
                    'Description' => '350ml matte finish mug with ergonomic handle',
                    'Price' => '899',
                    'Category' => 'Kitchen',
                    'Image URL' => 'https://images.unsplash.com/photo-1514228742587-6b1558fcca3d?auto=format&fit=crop&w=800',
                    'Stock Status' => 'In Stock',
                    'Variants' => 'White, Black, Sage',
                    'Tags' => 'Kitchen, Gift',
                ],
                [
                    'Item ID' => 'PROD_003',
                    'Title' => 'Organic Cotton T-Shirt',
                    'Description' => 'Unisex crew neck, pre-shrunk fabric',
                    'Price' => '1299',
                    'Category' => 'Apparel',
                    'Image URL' => 'https://images.unsplash.com/photo-1521572163474-6864f9cf17ab?auto=format&fit=crop&w=800',
                    'Stock Status' => 'Low Stock',
                    'Variants' => 'S, M, L, XL',
                    'Tags' => 'Sale, Sustainable',
                ],
                [
                    'Item ID' => 'PROD_004',
                    'Title' => 'Wireless Noise-Cancelling Earbuds',
                    'Description' => 'Bluetooth 5.3, 32hr battery with charging case',
                    'Price' => '4999',
                    'Category' => 'Electronics',
                    'Image URL' => 'https://images.unsplash.com/photo-1590658268037-6bf12165a8df?auto=format&fit=crop&w=800',
                    'Stock Status' => 'In Stock',
                    'Variants' => 'Black, White',
                    'Tags' => 'Featured, Audio',
                ],
                [
                    'Item ID' => 'PROD_005',
                    'Title' => 'Premium Yoga Mat 6mm',
                    'Description' => 'Non-slip TPE mat with carry strap',
                    'Price' => '2199',
                    'Category' => 'Fitness',
                    'Image URL' => 'https://images.unsplash.com/photo-1601925260368-ae2f83cf8b7f?auto=format&fit=crop&w=800',
                    'Stock Status' => 'Out of Stock',
                    'Variants' => 'Blue, Purple, Grey',
                    'Tags' => 'Fitness',
                ],
                [
                    'Item ID' => 'PROD_006',
                    'Title' => 'Leather Crossbody Handbag',
                    'Description' => 'Genuine leather with adjustable strap and inner pockets',
                    'Price' => '8500',
                    'Category' => 'Fashion',
                    'Image URL' => 'https://images.unsplash.com/photo-1548036328-c9fa89d128fa?auto=format&fit=crop&w=800',
                    'Stock Status' => 'In Stock',
                    'Variants' => 'Tan, Black',
                    'Tags' => 'Fashion, Gift',
                ],
                [
                    'Item ID' => 'PROD_007',
                    'Title' => 'Kids School Backpack',
                    'Description' => 'Water-resistant with padded laptop sleeve',
                    'Price' => '3200',
                    'Category' => 'Back to School',
                    'Image URL' => 'https://images.unsplash.com/photo-1553062407-98eeb64c6a62?auto=format&fit=crop&w=800',
                    'Stock Status' => 'In Stock',
                    'Variants' => 'Navy, Red, Green',
                    'Tags' => 'Kids, Seasonal',
                ],
                [
                    'Item ID' => 'PROD_008',
                    'Title' => 'Arabica Coffee Beans 500g',
                    'Description' => 'Single-origin medium roast from Nyeri highlands',
                    'Price' => '1450',
                    'Category' => 'Groceries',
                    'Image URL' => 'https://images.unsplash.com/photo-1559056199-641a0ac8b55e?auto=format&fit=crop&w=800',
                    'Stock Status' => 'In Stock',
                    'Variants' => 'Whole bean, Ground',
                    'Tags' => 'Local, Organic',
                ],
                [
                    'Item ID' => 'PROD_009',
                    'Title' => 'Smart LED Desk Lamp',
                    'Description' => 'Adjustable brightness, USB charging port',
                    'Price' => '3750',
                    'Category' => 'Home Office',
                    'Image URL' => 'https://images.unsplash.com/photo-1507473885765-e6ed057f782c?auto=format&fit=crop&w=800',
                    'Stock Status' => 'Low Stock',
                    'Variants' => '',
                    'Tags' => 'Office, Smart Home',
                ],
                [
                    'Item ID' => 'PROD_010',
                    'Title' => 'Running Shoes – Road Trainer',
                    'Description' => 'Lightweight mesh upper with cushioned sole',
                    'Price' => '6900',
                    'Category' => 'Footwear',
                    'Image URL' => 'https://images.unsplash.com/photo-1542291026-7eec264c27ff?auto=format&fit=crop&w=800',
                    'Stock Status' => 'In Stock',
                    'Variants' => 'UK 7, UK 8, UK 9, UK 10',
                    'Tags' => 'Sports, Bestseller',
                ],
            ],
            'real_estate' => [
                [
                    'Item ID' => 'HOME_001',
                    'Title' => '3BR Apartment in Karen',
                    'Description' => 'Bright corner unit with shared garden and backup generator',
                    'Price' => '18500000',
                    'Category' => 'Apartment',
                    'Image URL' => 'https://images.unsplash.com/photo-1522708323590-d24dbb6b0267?auto=format&fit=crop&w=800',
                    'Image URLs' => 'https://images.unsplash.com/photo-1502672265066-1094c646d9b4?auto=format&fit=crop&w=800',
                    'Tags' => 'Featured, Family',
                    'Location' => 'Karen, Nairobi',
                    'Latitude' => '-1.3197',
                    'Longitude' => '36.7080',
                    'Bedrooms' => '3',
                    'Bathrooms' => '2',
                    'Area (m²)' => '145',
                    'Status' => 'Available',
                    'Property type' => 'Apartment',
                    'Bookable service' => 'Property Viewing',
                ],
                [
                    'Item ID' => 'HOME_002',
                    'Title' => 'Westlands Penthouse',
                    'Description' => 'Panoramic city views, ensuite master, private terrace',
                    'Price' => '32000000',
                    'Category' => 'Apartment',
                    'Image URL' => 'https://images.unsplash.com/photo-1512917774080-9991f1c4c750?auto=format&fit=crop&w=800',
                    'Image URLs' => 'https://images.unsplash.com/photo-1600607687939-ce8a6c25118c?auto=format&fit=crop&w=800',
                    'Tags' => 'Luxury, City',
                    'Location' => 'Westlands, Nairobi',
                    'Latitude' => '-1.2676',
                    'Longitude' => '36.8070',
                    'Bedrooms' => '4',
                    'Bathrooms' => '4',
                    'Area (m²)' => '220',
                    'Status' => 'Under Offer',
                    'Property type' => 'Apartment',
                ],
                [
                    'Item ID' => 'HOME_003',
                    'Title' => 'Kiambu Family House',
                    'Description' => 'Standalone maisonette on gated quarter-acre plot',
                    'Price' => '24000000',
                    'Category' => 'House',
                    'Image URL' => 'https://images.unsplash.com/photo-1564013799919-ab600027ffc6?auto=format&fit=crop&w=800',
                    'Image URLs' => 'https://images.unsplash.com/photo-1600596542815-ffad4c1539a9?auto=format&fit=crop&w=800',
                    'Tags' => 'Family, Gated',
                    'Location' => 'Kiambu',
                    'Latitude' => '-1.1715',
                    'Longitude' => '36.8356',
                    'Bedrooms' => '5',
                    'Bathrooms' => '3',
                    'Area (m²)' => '310',
                    'Status' => 'Available',
                    'Property type' => 'House',
                ],
                [
                    'Item ID' => 'HOME_004',
                    'Title' => 'Diani Beach Villa',
                    'Description' => 'Walk to beach, private pool, furnished holiday home',
                    'Price' => '45000000',
                    'Category' => 'House',
                    'Image URL' => 'https://images.unsplash.com/photo-1613490493576-7fde63acd811?auto=format&fit=crop&w=800',
                    'Image URLs' => 'https://images.unsplash.com/photo-1600585154340-be6161a56a0c?auto=format&fit=crop&w=800',
                    'Tags' => 'Coastal, Holiday',
                    'Location' => 'Diani, Kwale',
                    'Latitude' => '-4.3224',
                    'Longitude' => '39.5795',
                    'Bedrooms' => '4',
                    'Bathrooms' => '4',
                    'Area (m²)' => '380',
                    'Status' => 'Available',
                    'Property type' => 'House',
                ],
                [
                    'Item ID' => 'HOME_005',
                    'Title' => 'Industrial Plot – Ruiru Bypass',
                    'Description' => 'Serviced 0.5-acre plot with perimeter wall',
                    'Price' => '12000000',
                    'Category' => 'Land',
                    'Image URL' => 'https://images.unsplash.com/photo-1500382017468-9049fed747ef?auto=format&fit=crop&w=800',
                    'Image URLs' => '',
                    'Tags' => 'Investment, Commercial',
                    'Location' => 'Ruiru',
                    'Latitude' => '-1.1500',
                    'Longitude' => '36.9600',
                    'Bedrooms' => '0',
                    'Bathrooms' => '0',
                    'Area (m²)' => '2020',
                    'Status' => 'Available',
                    'Property type' => 'Land',
                ],
                [
                    'Item ID' => 'HOME_006',
                    'Title' => 'Kilimani Studio Apartment',
                    'Description' => 'Compact studio ideal for young professionals, gym in building',
                    'Price' => '6800000',
                    'Category' => 'Apartment',
                    'Image URL' => 'https://images.unsplash.com/photo-1502672265066-1094c646d9b4?auto=format&fit=crop&w=800',
                    'Image URLs' => '',
                    'Tags' => 'Starter, Urban',
                    'Location' => 'Kilimani, Nairobi',
                    'Latitude' => '-1.2921',
                    'Longitude' => '36.7856',
                    'Bedrooms' => '1',
                    'Bathrooms' => '1',
                    'Area (m²)' => '52',
                    'Status' => 'Available',
                    'Property type' => 'Apartment',
                ],
                [
                    'Item ID' => 'HOME_007',
                    'Title' => 'Muthaiga Townhouse',
                    'Description' => 'Three-level townhouse with staff quarters and double garage',
                    'Price' => '52000000',
                    'Category' => 'House',
                    'Image URL' => 'https://images.unsplash.com/photo-1600585154340-be6161a56a0c?auto=format&fit=crop&w=800',
                    'Image URLs' => 'https://images.unsplash.com/photo-1605276374104-dee2a0ed3cd7?auto=format&fit=crop&w=800',
                    'Tags' => 'Premium, Family',
                    'Location' => 'Muthaiga, Nairobi',
                    'Latitude' => '-1.2445',
                    'Longitude' => '36.8250',
                    'Bedrooms' => '4',
                    'Bathrooms' => '5',
                    'Area (m²)' => '420',
                    'Status' => 'Available',
                    'Property type' => 'House',
                ],
                [
                    'Item ID' => 'HOME_008',
                    'Title' => 'Mombasa Road Warehouse',
                    'Description' => '10,000 sq ft warehouse with loading bay and office mezzanine',
                    'Price' => '85000000',
                    'Category' => 'Commercial',
                    'Image URL' => 'https://images.unsplash.com/photo-1586528116311-ad8dd3c8310d?auto=format&fit=crop&w=800',
                    'Image URLs' => '',
                    'Tags' => 'Industrial, Lease',
                    'Location' => 'Mombasa Road, Nairobi',
                    'Latitude' => '-1.3400',
                    'Longitude' => '36.8900',
                    'Bedrooms' => '0',
                    'Bathrooms' => '2',
                    'Area (m²)' => '930',
                    'Status' => 'Available',
                    'Property type' => 'Commercial',
                ],
                [
                    'Item ID' => 'HOME_009',
                    'Title' => 'Naivasha Lakeview Cottage',
                    'Description' => 'Weekend cottage with lake access and fireplace',
                    'Price' => '16500000',
                    'Category' => 'House',
                    'Image URL' => 'https://images.unsplash.com/photo-1449844908441-8829872d2602?auto=format&fit=crop&w=800',
                    'Image URLs' => '',
                    'Tags' => 'Retreat, Lake',
                    'Location' => 'Naivasha',
                    'Latitude' => '-0.7167',
                    'Longitude' => '36.4333',
                    'Bedrooms' => '3',
                    'Bathrooms' => '2',
                    'Area (m²)' => '180',
                    'Status' => 'Sold',
                    'Property type' => 'House',
                ],
                [
                    'Item ID' => 'HOME_010',
                    'Title' => 'Upper Hill Office Suite',
                    'Description' => 'Furnished open-plan office on 8th floor with city views',
                    'Price' => '28000000',
                    'Category' => 'Commercial',
                    'Image URL' => 'https://images.unsplash.com/photo-1497366216548-37526070297c?auto=format&fit=crop&w=800',
                    'Image URLs' => 'https://images.unsplash.com/photo-1497366811353-6870744d04b2?auto=format&fit=crop&w=800',
                    'Tags' => 'Office, CBD',
                    'Location' => 'Upper Hill, Nairobi',
                    'Latitude' => '-1.2921',
                    'Longitude' => '36.8219',
                    'Bedrooms' => '0',
                    'Bathrooms' => '2',
                    'Area (m²)' => '265',
                    'Status' => 'Leased',
                    'Property type' => 'Commercial',
                ],
            ],
            'automotive' => [
                [
                    'Item ID' => 'AUTO_001',
                    'Title' => 'Toyota RAV4 2021',
                    'Description' => 'One owner, full service history at Toyota Kenya',
                    'Price' => '4200000',
                    'Category' => 'SUV',
                    'Image URL' => 'https://images.unsplash.com/photo-1621007947382-bcb3c7834e3f?auto=format&fit=crop&w=800',
                    'Image URLs' => 'https://images.unsplash.com/photo-1619767886555-ef069afb7d4b?auto=format&fit=crop&w=800',
                    'Tags' => 'Featured, Low mileage',
                    'Make' => 'Toyota',
                    'Model' => 'RAV4',
                    'Year' => '2021',
                    'Mileage (km)' => '45000',
                    'Fuel' => 'Petrol',
                    'Status' => 'Available',
                    'Bookable service' => 'Vehicle Test Drive',
                ],
                [
                    'Item ID' => 'AUTO_002',
                    'Title' => 'Mazda CX-5 2020',
                    'Description' => 'Leather seats, sunroof, reverse camera',
                    'Price' => '3800000',
                    'Category' => 'SUV',
                    'Image URL' => 'https://images.unsplash.com/photo-1618843479313-40f8afb4b4d8?auto=format&fit=crop&w=800',
                    'Image URLs' => '',
                    'Tags' => 'Family',
                    'Make' => 'Mazda',
                    'Model' => 'CX-5',
                    'Year' => '2020',
                    'Mileage (km)' => '62000',
                    'Fuel' => 'Diesel',
                    'Status' => 'Available',
                ],
                [
                    'Item ID' => 'AUTO_003',
                    'Title' => 'Subaru Forester 2019',
                    'Description' => 'Symmetrical AWD, excellent for upcountry travel',
                    'Price' => '3500000',
                    'Category' => 'SUV',
                    'Image URL' => 'https://images.unsplash.com/photo-1606664515524-ed2f786a0bd6?auto=format&fit=crop&w=800',
                    'Image URLs' => '',
                    'Tags' => '4WD, Adventure',
                    'Make' => 'Subaru',
                    'Model' => 'Forester',
                    'Year' => '2019',
                    'Mileage (km)' => '78000',
                    'Fuel' => 'Petrol',
                    'Status' => 'Reserved',
                ],
                [
                    'Item ID' => 'AUTO_004',
                    'Title' => 'Nissan X-Trail 2018',
                    'Description' => '7-seater family SUV with roof rails',
                    'Price' => '2900000',
                    'Category' => 'SUV',
                    'Image URL' => 'https://images.unsplash.com/photo-1519641471654-76ce0107ad1b?auto=format&fit=crop&w=800',
                    'Image URLs' => '',
                    'Tags' => 'Family, 7-seater',
                    'Make' => 'Nissan',
                    'Model' => 'X-Trail',
                    'Year' => '2018',
                    'Mileage (km)' => '95000',
                    'Fuel' => 'Diesel',
                    'Status' => 'Available',
                ],
                [
                    'Item ID' => 'AUTO_005',
                    'Title' => 'Tesla Model 3 2022',
                    'Description' => 'Long range AWD, autopilot, home charger included',
                    'Price' => '6500000',
                    'Category' => 'Sedan',
                    'Image URL' => 'https://images.unsplash.com/photo-1560958089-b8a1929cea89?auto=format&fit=crop&w=800',
                    'Image URLs' => 'https://images.unsplash.com/photo-1617788138017-80ad40651399?auto=format&fit=crop&w=800',
                    'Tags' => 'Electric, Featured',
                    'Make' => 'Tesla',
                    'Model' => 'Model 3',
                    'Year' => '2022',
                    'Mileage (km)' => '22000',
                    'Fuel' => 'Electric',
                    'Status' => 'Available',
                ],
                [
                    'Item ID' => 'AUTO_006',
                    'Title' => 'Toyota Hilux Double Cab 2020',
                    'Description' => '4x4 pickup, tonneau cover, bull bar',
                    'Price' => '5100000',
                    'Category' => 'Pickup',
                    'Image URL' => 'https://images.unsplash.com/photo-1559416523-140dd9638fbf?auto=format&fit=crop&w=800',
                    'Image URLs' => '',
                    'Tags' => 'Workhorse, 4WD',
                    'Make' => 'Toyota',
                    'Model' => 'Hilux',
                    'Year' => '2020',
                    'Mileage (km)' => '88000',
                    'Fuel' => 'Diesel',
                    'Status' => 'Available',
                ],
                [
                    'Item ID' => 'AUTO_007',
                    'Title' => 'Honda Fit 2017',
                    'Description' => 'Fuel-efficient hatchback, ideal for city commuting',
                    'Price' => '1150000',
                    'Category' => 'Hatchback',
                    'Image URL' => 'https://images.unsplash.com/photo-1549317661-bd32c8ce0db2?auto=format&fit=crop&w=800',
                    'Image URLs' => '',
                    'Tags' => 'Budget, City',
                    'Make' => 'Honda',
                    'Model' => 'Fit',
                    'Year' => '2017',
                    'Mileage (km)' => '112000',
                    'Fuel' => 'Petrol',
                    'Status' => 'Available',
                ],
                [
                    'Item ID' => 'AUTO_008',
                    'Title' => 'Mercedes-Benz C200 2019',
                    'Description' => 'AMG line package, panoramic roof, service plan active',
                    'Price' => '4800000',
                    'Category' => 'Sedan',
                    'Image URL' => 'https://images.unsplash.com/photo-1617531653332-bd46c24f2068?auto=format&fit=crop&w=800',
                    'Image URLs' => '',
                    'Tags' => 'Luxury, Executive',
                    'Make' => 'Mercedes-Benz',
                    'Model' => 'C200',
                    'Year' => '2019',
                    'Mileage (km)' => '54000',
                    'Fuel' => 'Petrol',
                    'Status' => 'Reserved',
                ],
                [
                    'Item ID' => 'AUTO_009',
                    'Title' => 'Isuzu NQR Truck 2016',
                    'Description' => '5-tonne cargo truck, refrigerated body optional',
                    'Price' => '3200000',
                    'Category' => 'Commercial',
                    'Image URL' => 'https://images.unsplash.com/photo-1601584114757-47a21b4a57a8?auto=format&fit=crop&w=800',
                    'Image URLs' => '',
                    'Tags' => 'Fleet, Logistics',
                    'Make' => 'Isuzu',
                    'Model' => 'NQR',
                    'Year' => '2016',
                    'Mileage (km)' => '185000',
                    'Fuel' => 'Diesel',
                    'Status' => 'Available',
                ],
                [
                    'Item ID' => 'AUTO_010',
                    'Title' => 'BMW X5 xDrive 2021',
                    'Description' => 'Premium SUV with adaptive cruise and harman/kardon audio',
                    'Price' => '8900000',
                    'Category' => 'SUV',
                    'Image URL' => 'https://images.unsplash.com/photo-1617531653332-bd46c24f2068?auto=format&fit=crop&w=800',
                    'Image URLs' => 'https://images.unsplash.com/photo-1555215695-3004980ad54e?auto=format&fit=crop&w=800',
                    'Tags' => 'Premium, Imported',
                    'Make' => 'BMW',
                    'Model' => 'X5',
                    'Year' => '2021',
                    'Mileage (km)' => '38000',
                    'Fuel' => 'Hybrid',
                    'Status' => 'Sold',
                ],
            ],
            'jobs' => [
                [
                    'Item ID' => 'JOB_001',
                    'Title' => 'Sales Representative',
                    'Description' => 'Drive retail sales and manage key accounts across Nairobi region',
                    'Price' => '',
                    'Category' => 'Sales',
                    'Image URL' => 'https://images.unsplash.com/photo-1556745753-b290d2f3c0f4?auto=format&fit=crop&w=800',
                    'Image URLs' => '',
                    'Tags' => 'Urgent, Entry level',
                    'Company' => 'Acme Retail Ltd',
                    'Employment type' => 'Full-time',
                    'Salary' => 'KES 45,000 - 60,000',
                    'Experience' => '2+ years in retail sales',
                    'Education' => 'Diploma or higher',
                    'Skills' => 'Communication, CRM, negotiation, target-driven',
                    'Location' => 'Nairobi',
                    'Application deadline' => '2026-08-15',
                    'Benefits' => 'Medical cover, transport allowance, sales commission',
                    'Apply to email' => 'careers@acme.example',
                    'Status' => 'Open',
                ],
                [
                    'Item ID' => 'JOB_002',
                    'Title' => 'Front Desk Receptionist',
                    'Description' => 'First point of contact for guests at a 4-star beach hotel',
                    'Price' => '',
                    'Category' => 'Hospitality',
                    'Image URL' => 'https://images.unsplash.com/photo-1566073771259-6a8506099945?auto=format&fit=crop&w=800',
                    'Image URLs' => '',
                    'Tags' => 'Hospitality',
                    'Company' => 'Sunrise Hotels',
                    'Employment type' => 'Full-time',
                    'Salary' => 'KES 35,000',
                    'Experience' => '1 year customer service',
                    'Education' => 'KCSE',
                    'Skills' => 'MS Office, phone etiquette, guest relations',
                    'Location' => 'Mombasa',
                    'Application deadline' => '2026-08-01',
                    'Benefits' => 'Meals on duty, staff transport',
                    'Apply to email' => 'hr@sunrise.example',
                    'Status' => 'Open',
                ],
                [
                    'Item ID' => 'JOB_003',
                    'Title' => 'Delivery Rider',
                    'Description' => 'Last-mile food and parcel deliveries in Westlands and Kilimani',
                    'Price' => '',
                    'Category' => 'Operations',
                    'Image URL' => 'https://images.unsplash.com/photo-1520979360970-3269122907f4?auto=format&fit=crop&w=800',
                    'Image URLs' => '',
                    'Tags' => 'Rider, Gig',
                    'Company' => 'QuickDrop Logistics',
                    'Employment type' => 'Contract',
                    'Salary' => 'Commission based',
                    'Experience' => 'Valid riding license, smartphone',
                    'Education' => '',
                    'Skills' => 'Navigation, time management, customer care',
                    'Location' => 'Nairobi',
                    'Application deadline' => '2026-07-30',
                    'Benefits' => 'Fuel subsidy on meeting targets',
                    'Apply to email' => '',
                    'Status' => 'Open',
                ],
                [
                    'Item ID' => 'JOB_004',
                    'Title' => 'Junior Software Developer',
                    'Description' => 'Build Laravel APIs and Vue frontends for fintech products',
                    'Price' => '',
                    'Category' => 'Technology',
                    'Image URL' => 'https://images.unsplash.com/photo-1498050108023-c5249f4df085?auto=format&fit=crop&w=800',
                    'Image URLs' => 'https://images.unsplash.com/photo-1522071820081-009f0129c71c?auto=format&fit=crop&w=800',
                    'Tags' => 'Tech, Remote-friendly',
                    'Company' => 'PayFlow Africa',
                    'Employment type' => 'Full-time',
                    'Salary' => 'KES 120,000 - 160,000',
                    'Experience' => '1-3 years PHP/Laravel',
                    'Education' => 'BSc Computer Science or equivalent',
                    'Skills' => 'Laravel, Vue.js, Git, REST APIs, MySQL',
                    'Location' => 'Nairobi (Hybrid)',
                    'Application deadline' => '2026-09-01',
                    'Benefits' => 'Health insurance, learning budget, flexible hours',
                    'Apply to email' => 'talent@payflow.example',
                    'Status' => 'Open',
                ],
                [
                    'Item ID' => 'JOB_005',
                    'Title' => 'Registered Nurse – ICU',
                    'Description' => 'Support critical care unit at a referral hospital',
                    'Price' => '',
                    'Category' => 'Healthcare',
                    'Image URL' => 'https://images.unsplash.com/photo-1576091160399-112ba8d25d1d?auto=format&fit=crop&w=800',
                    'Image URLs' => '',
                    'Tags' => 'Healthcare, Shift work',
                    'Company' => 'Aga Health Network',
                    'Employment type' => 'Full-time',
                    'Salary' => 'KES 80,000 - 95,000',
                    'Experience' => '3+ years ICU experience',
                    'Education' => 'BSc Nursing, valid NCK license',
                    'Skills' => 'Patient monitoring, ventilator care, teamwork',
                    'Location' => 'Kisumu',
                    'Application deadline' => '2026-08-20',
                    'Benefits' => 'NHIF, night shift allowance, continuing education',
                    'Apply to email' => 'nursing@agahealth.example',
                    'Status' => 'Open',
                ],
                [
                    'Item ID' => 'JOB_006',
                    'Title' => 'Marketing Intern',
                    'Description' => 'Support social media campaigns and content creation',
                    'Price' => '',
                    'Category' => 'Marketing',
                    'Image URL' => 'https://images.unsplash.com/photo-1552664730-d307ca884978?auto=format&fit=crop&w=800',
                    'Image URLs' => '',
                    'Tags' => 'Internship, Graduate',
                    'Company' => 'BrightBrand Agency',
                    'Employment type' => 'Internship',
                    'Salary' => 'KES 25,000 stipend',
                    'Experience' => 'Final-year student or recent graduate',
                    'Education' => 'Degree in Marketing or Communications',
                    'Skills' => 'Canva, Instagram, copywriting, analytics basics',
                    'Location' => 'Nairobi',
                    'Application deadline' => '2026-08-10',
                    'Benefits' => 'Mentorship, certificate on completion',
                    'Apply to email' => 'interns@brightbrand.example',
                    'Status' => 'Open',
                ],
                [
                    'Item ID' => 'JOB_007',
                    'Title' => 'Farm Operations Supervisor',
                    'Description' => 'Oversee greenhouse tomato production and farm labour teams',
                    'Price' => '',
                    'Category' => 'Agriculture',
                    'Image URL' => 'https://images.unsplash.com/photo-1625246333195-78d9c090ad9a?auto=format&fit=crop&w=800',
                    'Image URLs' => '',
                    'Tags' => 'Agriculture, Management',
                    'Company' => 'GreenValley Farms',
                    'Employment type' => 'Full-time',
                    'Salary' => 'KES 55,000 - 70,000',
                    'Experience' => '5 years commercial farming',
                    'Education' => 'Diploma in Agriculture',
                    'Skills' => 'Crop management, irrigation, team leadership',
                    'Location' => 'Naivasha',
                    'Application deadline' => '2026-08-25',
                    'Benefits' => 'Housing on farm, produce allowance',
                    'Apply to email' => 'jobs@greenvalley.example',
                    'Status' => 'Open',
                ],
                [
                    'Item ID' => 'JOB_008',
                    'Title' => 'Accounts Assistant',
                    'Description' => 'Process invoices, reconcile M-Pesa till, support month-end close',
                    'Price' => '',
                    'Category' => 'Finance',
                    'Image URL' => 'https://images.unsplash.com/photo-1554224155-6726b3ff858f?auto=format&fit=crop&w=800',
                    'Image URLs' => '',
                    'Tags' => 'Finance',
                    'Company' => 'Summit Distributors',
                    'Employment type' => 'Full-time',
                    'Salary' => 'KES 50,000 - 65,000',
                    'Experience' => '2 years bookkeeping',
                    'Education' => 'CPA Section 2 or ACCA equivalent',
                    'Skills' => 'QuickBooks, Excel, VAT returns, attention to detail',
                    'Location' => 'Nairobi',
                    'Application deadline' => '2026-08-05',
                    'Benefits' => 'Pension, annual bonus',
                    'Apply to email' => 'finance@summit.example',
                    'Status' => 'Closed',
                ],
                [
                    'Item ID' => 'JOB_009',
                    'Title' => 'Primary School Teacher',
                    'Description' => 'Teach Grade 4 CBC curriculum including maths and literacy',
                    'Price' => '',
                    'Category' => 'Education',
                    'Image URL' => 'https://images.unsplash.com/photo-1503676260728-1c00da094a0b?auto=format&fit=crop&w=800',
                    'Image URLs' => '',
                    'Tags' => 'Education, Term 3',
                    'Company' => 'Hillcrest Academy',
                    'Employment type' => 'Full-time',
                    'Salary' => 'KES 40,000 - 55,000',
                    'Experience' => 'TSC registered, 2+ years teaching',
                    'Education' => 'P1 or Diploma in Education',
                    'Skills' => 'Classroom management, CBC, parent engagement',
                    'Location' => 'Thika',
                    'Application deadline' => '2026-07-15',
                    'Benefits' => 'School fees discount for children',
                    'Apply to email' => 'principal@hillcrest.example',
                    'Status' => 'Filled',
                ],
                [
                    'Item ID' => 'JOB_010',
                    'Title' => 'Solar Installation Technician',
                    'Description' => 'Install residential and commercial solar systems across Central Kenya',
                    'Price' => '',
                    'Category' => 'Engineering',
                    'Image URL' => 'https://images.unsplash.com/photo-1509391366360-2e959784a276?auto=format&fit=crop&w=800',
                    'Image URLs' => '',
                    'Tags' => 'Green energy, Field work',
                    'Company' => 'SunGrid Solutions',
                    'Employment type' => 'Contract',
                    'Salary' => 'KES 35,000 + project bonus',
                    'Experience' => 'Electrical certification, rooftop install experience',
                    'Education' => 'Craft certificate in Electrical',
                    'Skills' => 'PV wiring, safety compliance, client training',
                    'Location' => 'Nairobi & Central Kenya',
                    'Application deadline' => '2026-09-15',
                    'Benefits' => 'PPE provided, transport to sites',
                    'Apply to email' => 'install@sungrid.example',
                    'Status' => 'Open',
                ],
            ],
            'general_service' => [
                [
                    'Item ID' => 'SVC_001',
                    'Title' => 'Home Deep Cleaning',
                    'Description' => 'Full apartment clean including windows, kitchen and bathrooms',
                    'Price' => '6500',
                    'Category' => 'Cleaning',
                    'Image URL' => 'https://images.unsplash.com/photo-1581578731548-c64695cc6952?auto=format&fit=crop&w=800',
                    'Image URLs' => '',
                    'Tags' => 'Popular, Home',
                    'Duration' => '4 hours',
                    'Availability' => 'Available',
                    'Bookable service' => 'Home Deep Cleaning',
                ],
                [
                    'Item ID' => 'SVC_002',
                    'Title' => 'AC Service & Gas Refill',
                    'Description' => 'Split-unit service with leak check and filter wash',
                    'Price' => '4500',
                    'Category' => 'Maintenance',
                    'Image URL' => 'https://images.unsplash.com/photo-1631545806606-10e1b9b9c835?auto=format&fit=crop&w=800',
                    'Image URLs' => '',
                    'Tags' => 'Seasonal',
                    'Duration' => '2 hours',
                    'Availability' => 'Available',
                    'Bookable service' => 'AC Service & Gas Refill',
                ],
                [
                    'Item ID' => 'SVC_003',
                    'Title' => 'Bridal Makeup Package',
                    'Description' => 'Trial session plus wedding day makeup with touch-up kit',
                    'Price' => '18000',
                    'Category' => 'Beauty',
                    'Image URL' => 'https://images.unsplash.com/photo-1487412940907-5fbf552da2e7?auto=format&fit=crop&w=800',
                    'Image URLs' => '',
                    'Tags' => 'Wedding, Premium',
                    'Duration' => '3 hours',
                    'Availability' => 'Fully Booked',
                    'Bookable service' => 'Bridal Makeup Package',
                ],
                [
                    'Item ID' => 'SVC_004',
                    'Title' => 'Website SEO Audit',
                    'Description' => 'Technical SEO, performance and keyword gap analysis with report',
                    'Price' => '25000',
                    'Category' => 'Consulting',
                    'Image URL' => 'https://images.unsplash.com/photo-1460925895917-afdab827c52f?auto=format&fit=crop&w=800',
                    'Image URLs' => '',
                    'Tags' => 'Digital, B2B',
                    'Duration' => '1 week',
                    'Availability' => 'Available',
                    'Bookable service' => 'Website SEO Audit',
                ],
                [
                    'Item ID' => 'SVC_005',
                    'Title' => 'Garden Landscaping Consultation',
                    'Description' => 'On-site assessment, plant selection and design proposal',
                    'Price' => '8000',
                    'Category' => 'Outdoor',
                    'Image URL' => 'https://images.unsplash.com/photo-1416879595882-3373a0480b5b?auto=format&fit=crop&w=800',
                    'Image URLs' => '',
                    'Tags' => 'Outdoor, Design',
                    'Duration' => '90 minutes',
                    'Availability' => 'Available',
                    'Bookable service' => 'Garden Landscaping Consultation',
                ],
                [
                    'Item ID' => 'SVC_006',
                    'Title' => 'Mobile Car Wash & Detailing',
                    'Description' => 'Exterior wash, interior vacuum and dashboard polish at your location',
                    'Price' => '3500',
                    'Category' => 'Automotive Care',
                    'Image URL' => 'https://images.unsplash.com/photo-1520340356584-701b5b3f2c49?auto=format&fit=crop&w=800',
                    'Image URLs' => '',
                    'Tags' => 'Mobile, Weekend',
                    'Duration' => '1.5 hours',
                    'Availability' => 'Available',
                    'Bookable service' => 'Mobile Car Wash',
                ],
                [
                    'Item ID' => 'SVC_007',
                    'Title' => 'Plumbing Emergency Call-out',
                    'Description' => 'Burst pipes, blocked drains and geyser faults within Nairobi',
                    'Price' => '5000',
                    'Category' => 'Repairs',
                    'Image URL' => 'https://images.unsplash.com/photo-1607472586893-edb57bdc1e38?auto=format&fit=crop&w=800',
                    'Image URLs' => '',
                    'Tags' => 'Emergency, 24/7',
                    'Duration' => '1 hour',
                    'Availability' => 'Available',
                    'Bookable service' => 'Plumbing Emergency',
                ],
                [
                    'Item ID' => 'SVC_008',
                    'Title' => 'Personal Training Session',
                    'Description' => 'One-on-one fitness coaching at your gym or home',
                    'Price' => '4000',
                    'Category' => 'Fitness',
                    'Image URL' => 'https://images.unsplash.com/photo-1571019614242-c5c5dee9f50b?auto=format&fit=crop&w=800',
                    'Image URLs' => '',
                    'Tags' => 'Health, Package deals',
                    'Duration' => '60 minutes',
                    'Availability' => 'Available',
                    'Bookable service' => 'Personal Training',
                ],
                [
                    'Item ID' => 'SVC_009',
                    'Title' => 'Event Photography – Half Day',
                    'Description' => 'Corporate event or birthday coverage with edited digital gallery',
                    'Price' => '22000',
                    'Category' => 'Events',
                    'Image URL' => 'https://images.unsplash.com/photo-1492684223066-81342ee5ff30?auto=format&fit=crop&w=800',
                    'Image URLs' => 'https://images.unsplash.com/photo-1511578314322-379afb476865?auto=format&fit=crop&w=800',
                    'Tags' => 'Events, Creative',
                    'Duration' => '4 hours',
                    'Availability' => 'Fully Booked',
                    'Bookable service' => 'Event Photography',
                ],
                [
                    'Item ID' => 'SVC_010',
                    'Title' => 'Small Business Tax Filing',
                    'Description' => 'KRA iTax filing support for sole proprietors and SMEs',
                    'Price' => '12000',
                    'Category' => 'Accounting',
                    'Image URL' => 'https://images.unsplash.com/photo-1554224155-8d04cb21cdae?auto=format&fit=crop&w=800',
                    'Image URLs' => '',
                    'Tags' => 'Tax season, SME',
                    'Duration' => '3 days',
                    'Availability' => 'Available',
                    'Bookable service' => 'Tax Filing Support',
                ],
            ],
        ];
    }

    private function resolveFieldFromHeader(string $header, ?string $vertical = null): ?string
    {
        if ($vertical !== null) {
            $registryField = app(CatalogTemplateRegistry::class)->resolveExcelFieldFromHeader($header, $vertical);
            if ($registryField !== null) {
                return $registryField;
            }
        }

        $normalized = $this->normalizeHeader($header);

        foreach (self::HEADER_ALIASES as $field => $aliases) {
            if (in_array($normalized, $aliases, true)) {
                return $field;
            }
        }

        return null;
    }

    private function normalizeHeader(string $header): string
    {
        $normalized = strtolower(trim($header));
        $normalized = preg_replace('/[^a-z0-9]+/', '', $normalized) ?? '';

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  array<string, string>  $columnMapping
     */
    private function cellValue(array $item, array $columnMapping, string $field): string
    {
        if (! isset($columnMapping[$field])) {
            return '';
        }

        $header = $columnMapping[$field];
        if (! array_key_exists($header, $item)) {
            return '';
        }

        $value = $item[$header];
        if ($value === null) {
            return '';
        }

        return trim((string) $value);
    }

    private function parsePrice(string $value): float
    {
        if ($value === '') {
            return 0.0;
        }

        $normalized = preg_replace('/^\s*(ksh|kes)\s*/i', '', trim($value)) ?? trim($value);
        $cleaned = preg_replace('/[^0-9.\-]/', '', $normalized) ?? '';

        return is_numeric($cleaned) ? (float) $cleaned : 0.0;
    }

    private function parseStockStatus(string $value): string
    {
        if ($value === '') {
            return 'In Stock';
        }

        foreach (self::ALLOWED_STOCK_STATUSES as $status) {
            if (strcasecmp($status, $value) === 0) {
                return $status;
            }
        }

        $normalized = strtolower($value);
        if (in_array($normalized, ['in stock', 'instock', 'available', 'yes'], true)) {
            return 'In Stock';
        }
        if (in_array($normalized, ['out of stock', 'outofstock', 'unavailable', 'no'], true)) {
            return 'Out of Stock';
        }
        if (in_array($normalized, ['low stock', 'lowstock', 'low'], true)) {
            return 'Low Stock';
        }

        return 'In Stock';
    }

    /**
     * @return list<string>
     */
    private function parseListCell(string $value): array
    {
        if ($value === '') {
            return [];
        }

        return array_values(array_filter(
            array_map('trim', explode(',', $value)),
            fn ($part) => $part !== ''
        ));
    }
}
