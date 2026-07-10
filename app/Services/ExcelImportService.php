<?php

namespace App\Services;

use App\Services\Catalog\CatalogTemplateRegistry;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ExcelImportService
{
    public const TEMPLATE_SAMPLE_ROW_COUNT = 5;

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
                    'Title' => 'Blue Dog Bowl',
                    'Description' => 'Durable stainless steel bowl for medium dogs',
                    'Price' => '2499',
                    'Category' => 'Pet Supplies',
                    'Image URL' => 'https://example.com/images/dog-bowl.jpg',
                    'Stock Status' => 'In Stock',
                    'Variants' => 'S, M, L',
                    'Tags' => 'Featured, New',
                ],
                [
                    'Item ID' => 'PROD_002',
                    'Title' => 'Ceramic Coffee Mug',
                    'Description' => '350ml matte finish mug',
                    'Price' => '899',
                    'Category' => 'Kitchen',
                    'Image URL' => 'https://example.com/images/mug.jpg',
                    'Stock Status' => 'In Stock',
                    'Variants' => 'White, Black',
                    'Tags' => 'Kitchen',
                ],
                [
                    'Item ID' => 'PROD_003',
                    'Title' => 'Cotton T-Shirt',
                    'Description' => 'Unisex crew neck tee',
                    'Price' => '1299',
                    'Category' => 'Apparel',
                    'Image URL' => 'https://example.com/images/tshirt.jpg',
                    'Stock Status' => 'Low Stock',
                    'Variants' => 'S, M, L, XL',
                    'Tags' => 'Sale',
                ],
                [
                    'Item ID' => 'PROD_004',
                    'Title' => 'Wireless Earbuds',
                    'Description' => 'Bluetooth 5.3 with charging case',
                    'Price' => '4999',
                    'Category' => 'Electronics',
                    'Image URL' => 'https://example.com/images/earbuds.jpg',
                    'Stock Status' => 'In Stock',
                    'Variants' => '',
                    'Tags' => 'Featured',
                ],
                [
                    'Item ID' => 'PROD_005',
                    'Title' => 'Yoga Mat',
                    'Description' => 'Non-slip 6mm exercise mat',
                    'Price' => '2199',
                    'Category' => 'Fitness',
                    'Image URL' => 'https://example.com/images/yoga-mat.jpg',
                    'Stock Status' => 'Out of Stock',
                    'Variants' => 'Blue, Purple',
                    'Tags' => 'Fitness',
                ],
            ],
            'real_estate' => [
                [
                    'Item ID' => 'HOME_001',
                    'Title' => '3BR Apartment in Karen',
                    'Description' => 'Spacious apartment with garden access',
                    'Price' => '18500000',
                    'Category' => 'Apartment',
                    'Image URL' => 'https://example.com/images/karen-apt-cover.jpg',
                    'Image URLs' => 'https://example.com/images/karen-apt-1.jpg, https://example.com/images/karen-apt-2.jpg',
                    'Tags' => 'Featured',
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
                    'Description' => 'Panoramic city views, ensuite master',
                    'Price' => '32000000',
                    'Category' => 'Apartment',
                    'Image URL' => 'https://example.com/images/westlands-cover.jpg',
                    'Image URLs' => 'https://example.com/images/westlands-1.jpg',
                    'Tags' => 'Luxury',
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
                    'Description' => 'Standalone house on quarter-acre plot',
                    'Price' => '24000000',
                    'Category' => 'House',
                    'Image URL' => 'https://example.com/images/kiambu-house.jpg',
                    'Image URLs' => '',
                    'Tags' => 'Family',
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
                    'Description' => 'Walk to beach, private pool',
                    'Price' => '45000000',
                    'Category' => 'House',
                    'Image URL' => 'https://example.com/images/diani-villa.jpg',
                    'Image URLs' => 'https://example.com/images/diani-1.jpg, https://example.com/images/diani-2.jpg',
                    'Tags' => 'Coastal',
                    'Location' => 'Diani',
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
                    'Title' => 'Industrial Plot Ruiru',
                    'Description' => 'Serviced plot near Eastern Bypass',
                    'Price' => '12000000',
                    'Category' => 'Land',
                    'Image URL' => 'https://example.com/images/ruiru-plot.jpg',
                    'Image URLs' => '',
                    'Tags' => 'Investment',
                    'Location' => 'Ruiru',
                    'Latitude' => '-1.1500',
                    'Longitude' => '36.9600',
                    'Bedrooms' => '0',
                    'Bathrooms' => '0',
                    'Area (m²)' => '1200',
                    'Status' => 'Available',
                    'Property type' => 'Land',
                ],
            ],
            'automotive' => [
                [
                    'Item ID' => 'AUTO_001',
                    'Title' => 'Toyota RAV4 2021',
                    'Description' => 'One owner, full service history',
                    'Price' => '4200000',
                    'Category' => 'SUV',
                    'Image URL' => 'https://example.com/images/rav4.jpg',
                    'Image URLs' => 'https://example.com/images/rav4-int.jpg',
                    'Tags' => 'Featured',
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
                    'Description' => 'Leather seats, sunroof',
                    'Price' => '3800000',
                    'Category' => 'SUV',
                    'Image URL' => 'https://example.com/images/cx5.jpg',
                    'Image URLs' => '',
                    'Tags' => '',
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
                    'Description' => 'AWD, excellent condition',
                    'Price' => '3500000',
                    'Category' => 'SUV',
                    'Image URL' => 'https://example.com/images/forester.jpg',
                    'Image URLs' => '',
                    'Tags' => '4WD',
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
                    'Description' => '7-seater family SUV',
                    'Price' => '2900000',
                    'Category' => 'SUV',
                    'Image URL' => 'https://example.com/images/xtrail.jpg',
                    'Image URLs' => '',
                    'Tags' => 'Family',
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
                    'Description' => 'Long range, autopilot',
                    'Price' => '6500000',
                    'Category' => 'Sedan',
                    'Image URL' => 'https://example.com/images/model3.jpg',
                    'Image URLs' => 'https://example.com/images/model3-2.jpg',
                    'Tags' => 'Electric, Featured',
                    'Make' => 'Tesla',
                    'Model' => 'Model 3',
                    'Year' => '2022',
                    'Mileage (km)' => '22000',
                    'Fuel' => 'Electric',
                    'Status' => 'Available',
                ],
            ],
            'general_listing' => [
                [
                    'Item ID' => 'LIST_001',
                    'Title' => 'Conference Room Hire',
                    'Description' => 'Boardroom for 12 with AV setup',
                    'Price' => '15000',
                    'Category' => 'Venue',
                    'Image URL' => 'https://example.com/images/boardroom.jpg',
                    'Image URLs' => '',
                    'Tags' => 'Business',
                    'Location' => 'Upper Hill, Nairobi',
                    'Latitude' => '-1.2921',
                    'Longitude' => '36.8219',
                    'Status' => 'Available',
                    'Bookable service' => 'Conference Room Hire',
                ],
                [
                    'Item ID' => 'LIST_002',
                    'Title' => 'Industrial Generator 100kVA',
                    'Description' => 'Low hours, recently serviced',
                    'Price' => '850000',
                    'Category' => 'Equipment',
                    'Image URL' => 'https://example.com/images/generator.jpg',
                    'Image URLs' => '',
                    'Tags' => 'Heavy duty',
                    'Location' => 'Industrial Area',
                    'Latitude' => '-1.3132',
                    'Longitude' => '36.8578',
                    'Status' => 'Available',
                ],
                [
                    'Item ID' => 'LIST_003',
                    'Title' => 'Pop-up Retail Kiosk',
                    'Description' => 'Portable unit with power hookup',
                    'Price' => '120000',
                    'Category' => 'Retail',
                    'Image URL' => 'https://example.com/images/kiosk.jpg',
                    'Image URLs' => '',
                    'Tags' => '',
                    'Location' => 'Kilimani',
                    'Latitude' => '-1.2920',
                    'Longitude' => '36.7850',
                    'Status' => 'Under Offer',
                ],
                [
                    'Item ID' => 'LIST_004',
                    'Title' => 'Boat Charter - Lake Naivasha',
                    'Description' => 'Half-day fishing trip for up to 6',
                    'Price' => '35000',
                    'Category' => 'Experience',
                    'Image URL' => 'https://example.com/images/boat.jpg',
                    'Image URLs' => '',
                    'Tags' => 'Tourism',
                    'Location' => 'Naivasha',
                    'Latitude' => '-0.7167',
                    'Longitude' => '36.4333',
                    'Status' => 'Available',
                    'Bookable service' => 'Boat Charter - Lake Naivasha',
                ],
                [
                    'Item ID' => 'LIST_005',
                    'Title' => 'Office Furniture Bundle',
                    'Description' => '10 desks, chairs, and filing cabinets',
                    'Price' => '180000',
                    'Category' => 'Furniture',
                    'Image URL' => 'https://example.com/images/office-bundle.jpg',
                    'Image URLs' => '',
                    'Tags' => 'Bulk',
                    'Location' => 'Mombasa Road',
                    'Latitude' => '-1.3400',
                    'Longitude' => '36.8900',
                    'Status' => 'Sold',
                ],
            ],
            'general_service' => [
                [
                    'Item ID' => 'SVC_001',
                    'Title' => 'Home Deep Cleaning',
                    'Description' => 'Full apartment clean including windows',
                    'Price' => '6500',
                    'Category' => 'Cleaning',
                    'Image URL' => 'https://example.com/images/cleaning.jpg',
                    'Image URLs' => '',
                    'Tags' => 'Popular',
                    'Duration' => '4 hours',
                    'Availability' => 'Available',
                    'Bookable service' => 'Home Deep Cleaning',
                ],
                [
                    'Item ID' => 'SVC_002',
                    'Title' => 'AC Service & Gas Refill',
                    'Description' => 'Split unit service with leak check',
                    'Price' => '4500',
                    'Category' => 'Maintenance',
                    'Image URL' => 'https://example.com/images/ac-service.jpg',
                    'Image URLs' => '',
                    'Tags' => '',
                    'Duration' => '2 hours',
                    'Availability' => 'Available',
                    'Bookable service' => 'AC Service & Gas Refill',
                ],
                [
                    'Item ID' => 'SVC_003',
                    'Title' => 'Bridal Makeup Package',
                    'Description' => 'Trial session plus wedding day makeup',
                    'Price' => '18000',
                    'Category' => 'Beauty',
                    'Image URL' => 'https://example.com/images/makeup.jpg',
                    'Image URLs' => '',
                    'Tags' => 'Wedding',
                    'Duration' => '3 hours',
                    'Availability' => 'Fully Booked',
                    'Bookable service' => 'Bridal Makeup Package',
                ],
                [
                    'Item ID' => 'SVC_004',
                    'Title' => 'Website Audit',
                    'Description' => 'SEO and performance review with report',
                    'Price' => '25000',
                    'Category' => 'Consulting',
                    'Image URL' => 'https://example.com/images/audit.jpg',
                    'Image URLs' => '',
                    'Tags' => 'Digital',
                    'Duration' => '1 week',
                    'Availability' => 'Available',
                    'Bookable service' => 'Website Audit',
                ],
                [
                    'Item ID' => 'SVC_005',
                    'Title' => 'Garden Landscaping Visit',
                    'Description' => 'On-site assessment and design proposal',
                    'Price' => '8000',
                    'Category' => 'Outdoor',
                    'Image URL' => 'https://example.com/images/garden.jpg',
                    'Image URLs' => '',
                    'Tags' => '',
                    'Duration' => '90 minutes',
                    'Availability' => 'Available',
                    'Bookable service' => 'Garden Landscaping Visit',
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
