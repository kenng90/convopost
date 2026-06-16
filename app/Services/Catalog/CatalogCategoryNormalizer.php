<?php

namespace App\Services\Catalog;

class CatalogCategoryNormalizer
{
    /**
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    public function normalizeItems(array $items): array
    {
        return array_map(function (array $item) {
            if (isset($item['category']) && is_string($item['category'])) {
                $item['category'] = $this->normalizeCategory($item['category']);
            }

            return $item;
        }, $items);
    }

    public function normalizeCategory(?string $category): string
    {
        $category = trim((string) $category);
        if ($category === '') {
            return '';
        }

        $category = preg_replace('/\s+/', ' ', $category) ?? $category;

        return mb_convert_case($category, MB_CASE_TITLE, 'UTF-8');
    }
}
