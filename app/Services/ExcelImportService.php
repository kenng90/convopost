<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ExcelImportService
{
    /**
     * Parse Excel file and return structured data
     */
    public function parseExcel($filePath)
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
                
                // Skip empty rows
                if (!empty(array_filter($rowData))) {
                    $rows[] = $rowData;
                }
            }

            if (empty($rows)) {
                throw new \Exception('Excel file is empty');
            }

            // First row is headers
            $headers = array_shift($rows);
            $headers = array_map(fn($h) => trim($h), $headers);
            
            // Convert rows to associative arrays
            $items = array_map(function($row) use ($headers) {
                return array_combine($headers, array_slice($row, 0, count($headers)));
            }, $rows);

            return [
                'items' => $items,
                'columns' => $this->detectColumns($headers),
                'total_count' => count($items),
                'headers' => $headers,
            ];

        } catch (\Exception $e) {
            Log::error('Error parsing Excel file', [
                'error' => $e->getMessage(),
                'file' => $filePath
            ]);
            throw new \Exception('Failed to parse Excel file: ' . $e->getMessage());
        }
    }

    /**
     * Detect common column mappings from headers
     */
    private function detectColumns($headers)
    {
        $commonMappings = [
            'title' => ['title', 'name', 'product', 'service', 'item', 'description'],
            'description' => ['description', 'desc', 'details', 'notes', 'remarks'],
            'price' => ['price', 'cost', 'amount', 'rate', 'fee', 'value'],
            'id' => ['id', 'sku', 'code', 'reference', 'product_id', 'item_id'],
            'category' => ['category', 'type', 'class', 'group'],
        ];

        $detected = [];
        $lowercaseHeaders = array_map('strtolower', $headers);

        foreach ($commonMappings as $fieldName => $patterns) {
            foreach ($patterns as $pattern) {
                $key = array_search(strtolower($pattern), $lowercaseHeaders);
                if ($key !== false) {
                    $detected[$fieldName] = $headers[$key];
                    break;
                }
            }
        }

        return [
            'detected' => $detected,
            'available' => $headers,
        ];
    }

    /**
     * Transform items using column mapping
     */
    public function transformItems($items, $columnMapping)
    {
        try {
            return array_map(function($item) use ($columnMapping) {
                $transformed = [
                    'id' => $item[$columnMapping['id']] ?? uniqid(),
                    'title' => $item[$columnMapping['title']] ?? '',
                    'description' => $columnMapping['description'] 
                        ? ($item[$columnMapping['description']] ?? '')
                        : '',
                ];

                // Remove empty title items
                if (empty($transformed['title'])) {
                    return null;
                }

                // Add optional fields if present
                if (isset($columnMapping['price']) && isset($item[$columnMapping['price']])) {
                    $transformed['price'] = $item[$columnMapping['price']];
                }

                if (isset($columnMapping['category']) && isset($item[$columnMapping['category']])) {
                    $transformed['category'] = $item[$columnMapping['category']];
                }

                return $transformed;
            }, $items);

            // Filter out null values
            return array_filter($items, fn($item) => $item !== null);

        } catch (\Exception $e) {
            Log::error('Error transforming items', [
                'error' => $e->getMessage(),
                'mapping' => $columnMapping
            ]);
            throw new \Exception('Failed to transform items: ' . $e->getMessage());
        }
    }

    /**
     * Validate transformed items
     */
    public function validateItems($items)
    {
        if (!is_array($items) || empty($items)) {
            throw new \Exception('Items must be a non-empty array');
        }

        foreach ($items as $idx => $item) {
            if (!isset($item['title']) || empty($item['title'])) {
                throw new \Exception("Item #{$idx}: missing or empty title field");
            }

            if (!isset($item['id']) || empty($item['id'])) {
                throw new \Exception("Item #{$idx}: missing or empty id field");
            }
        }

        return true;
    }

    /**
     * Get columns from items array
     */
    public function getColumnsFromItems($items)
    {
        if (empty($items)) {
            return [];
        }

        return array_keys($items[0]);
    }

    /**
     * Preview items with pagination
     */
    public function previewItems($items, $limit = 5)
    {
        return [
            'items' => array_slice($items, 0, $limit),
            'total_count' => count($items),
            'showing' => count(array_slice($items, 0, $limit)),
            'has_more' => count($items) > $limit,
        ];
    }
}
