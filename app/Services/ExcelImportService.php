<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ExcelImportService
{
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
    public function buildColumnMappingFromHeaders(array $headers): array
    {
        $mapping = [];

        foreach ($headers as $header) {
            if ($header === '') {
                continue;
            }

            $field = $this->resolveFieldFromHeader($header);
            if ($field !== null && ! isset($mapping[$field])) {
                $mapping[$field] = $header;
            }
        }

        return $mapping;
    }

    /**
     * @throws \Exception
     */
    public function assertRequiredColumnsMapped(array $columnMapping): void
    {
        $missing = [];
        if (! isset($columnMapping['id'])) {
            $missing[] = 'Item ID';
        }
        if (! isset($columnMapping['title'])) {
            $missing[] = 'Title';
        }

        if ($missing !== []) {
            throw new \Exception(
                'Missing required column(s): '.implode(', ', $missing).'. '.
                'Use the catalog import template with headers: '.implode(', ', self::TEMPLATE_HEADERS)
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
    public function transformItems(array $items, array $columnMapping): array
    {
        try {
            $this->assertRequiredColumnsMapped($columnMapping);

            $transformed = array_map(function ($item) use ($columnMapping) {
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
                    'stockStatus' => $this->parseStockStatus($this->cellValue($item, $columnMapping, 'stockStatus')),
                    'variants' => $this->parseListCell($this->cellValue($item, $columnMapping, 'variants')),
                    'tags' => $this->parseListCell($this->cellValue($item, $columnMapping, 'tags')),
                ];

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

    public function createTemplateSpreadsheet(): Spreadsheet
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Catalog Items');

        foreach (self::TEMPLATE_HEADERS as $index => $header) {
            $sheet->setCellValue([$index + 1, 1], $header);
        }

        $example = [
            'PROD_001',
            'Blue Dog Bowl',
            'Durable ceramic bowl for pets',
            '19.99',
            'Pet Supplies',
            'https://example.com/images/bowl.jpg',
            'In Stock',
            'S,M,L',
            'New,Sale',
        ];

        foreach ($example as $index => $value) {
            $sheet->setCellValue([$index + 1, 2], $value);
        }

        return $spreadsheet;
    }

    public function writeTemplateToPath(string $filePath): void
    {
        $writer = new Xlsx($this->createTemplateSpreadsheet());
        $writer->save($filePath);
    }

    private function resolveFieldFromHeader(string $header): ?string
    {
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
