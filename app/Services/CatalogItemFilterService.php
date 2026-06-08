<?php

namespace App\Services;

use Illuminate\Pagination\LengthAwarePaginator;

class CatalogItemFilterService
{
    public const DEFAULT_PER_PAGE = 24;

    public const MAX_PER_PAGE = 48;

    /**
     * @param  list<array<string, mixed>>  $items
     * @param  array<string, mixed>  $filters
     * @return array{
     *     items: LengthAwarePaginator,
     *     filterOptions: array{categories: list<string>, tags: list<string>},
     *     filters: array<string, mixed>,
     *     totalInCatalog: int,
     *     filteredTotal: int
     * }
     */
    public function browse(array $items, array $filters, string $path): array
    {
        $normalizedFilters = $this->normalizeFilters($filters);
        $filterOptions = $this->extractFilterOptions($items);
        $filtered = $this->applyFilters($items, $normalizedFilters);
        $sorted = $this->sortItems($filtered, $normalizedFilters['sort']);
        $paginator = $this->paginateItems(
            $sorted,
            $normalizedFilters['page'],
            $normalizedFilters['per_page'],
            $path,
            $this->queryParamsForPagination($normalizedFilters)
        );

        return [
            'items' => $paginator,
            'filterOptions' => $filterOptions,
            'filters' => $normalizedFilters,
            'totalInCatalog' => count($items),
            'filteredTotal' => count($sorted),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return array{categories: list<string>, tags: list<string>}
     */
    public function extractFilterOptions(array $items): array
    {
        $categories = [];
        $tags = [];

        foreach ($items as $item) {
            $category = trim((string) ($item['category'] ?? ''));
            if ($category !== '') {
                $categories[$category] = $category;
            }

            foreach ($item['tags'] ?? [] as $tag) {
                $tag = trim((string) $tag);
                if ($tag !== '') {
                    $tags[$tag] = $tag;
                }
            }
        }

        $categoryList = array_values($categories);
        $tagList = array_values($tags);
        sort($categoryList, SORT_NATURAL | SORT_FLAG_CASE);
        sort($tagList, SORT_NATURAL | SORT_FLAG_CASE);

        return [
            'categories' => $categoryList,
            'tags' => $tagList,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @param  array<string, mixed>  $filters
     * @return list<array<string, mixed>>
     */
    public function applyFilters(array $items, array $filters): array
    {
        return array_values(array_filter($items, function (array $item) use ($filters) {
            if ($filters['q'] !== '') {
                $needle = mb_strtolower($filters['q']);
                $title = mb_strtolower((string) ($item['title'] ?? ''));
                $description = mb_strtolower((string) ($item['description'] ?? ''));
                $itemId = mb_strtolower((string) ($item['id'] ?? ''));

                if (! str_contains($title, $needle)
                    && ! str_contains($description, $needle)
                    && ! str_contains($itemId, $needle)) {
                    return false;
                }
            }

            if ($filters['category'] !== '') {
                if (strcasecmp((string) ($item['category'] ?? ''), $filters['category']) !== 0) {
                    return false;
                }
            }

            if ($filters['stock'] !== '') {
                $stockStatus = (string) ($item['stockStatus'] ?? 'In Stock');
                if ($stockStatus !== $filters['stock']) {
                    return false;
                }
            }

            if ($filters['tag'] !== '') {
                $itemTags = array_map(
                    fn ($tag) => mb_strtolower(trim((string) $tag)),
                    is_array($item['tags'] ?? null) ? $item['tags'] : []
                );

                if (! in_array(mb_strtolower($filters['tag']), $itemTags, true)) {
                    return false;
                }
            }

            $price = (float) ($item['price'] ?? 0);

            if ($filters['min_price'] !== null && $price < $filters['min_price']) {
                return false;
            }

            if ($filters['max_price'] !== null && $price > $filters['max_price']) {
                return false;
            }

            return true;
        }));
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    public function sortItems(array $items, string $sort): array
    {
        if ($sort === 'default') {
            return $items;
        }

        $sorted = $items;

        usort($sorted, function (array $a, array $b) use ($sort) {
            return match ($sort) {
                'price_asc' => ($a['price'] ?? 0) <=> ($b['price'] ?? 0),
                'price_desc' => ($b['price'] ?? 0) <=> ($a['price'] ?? 0),
                'title_asc' => strcasecmp((string) ($a['title'] ?? ''), (string) ($b['title'] ?? '')),
                'title_desc' => strcasecmp((string) ($b['title'] ?? ''), (string) ($a['title'] ?? '')),
                default => 0,
            };
        });

        return $sorted;
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @param  array<string, mixed>  $query
     */
    public function paginateItems(array $items, int $page, int $perPage, string $path, array $query = []): LengthAwarePaginator
    {
        $page = max(1, $page);
        $perPage = min(self::MAX_PER_PAGE, max(12, $perPage));
        $total = count($items);
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = min($page, $lastPage);
        $offset = ($page - 1) * $perPage;
        $slice = array_slice($items, $offset, $perPage);

        return new LengthAwarePaginator(
            $slice,
            $total,
            $perPage,
            $page,
            [
                'path' => $path,
                'query' => $query,
            ]
        );
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function normalizeFilters(array $filters): array
    {
        $minPrice = isset($filters['min_price']) && $filters['min_price'] !== '' && $filters['min_price'] !== null
            ? (float) $filters['min_price']
            : null;
        $maxPrice = isset($filters['max_price']) && $filters['max_price'] !== '' && $filters['max_price'] !== null
            ? (float) $filters['max_price']
            : null;

        if ($minPrice !== null && $maxPrice !== null && $minPrice > $maxPrice) {
            [$minPrice, $maxPrice] = [$maxPrice, $minPrice];
        }

        return [
            'q' => trim((string) ($filters['q'] ?? '')),
            'category' => trim((string) ($filters['category'] ?? '')),
            'stock' => trim((string) ($filters['stock'] ?? '')),
            'tag' => trim((string) ($filters['tag'] ?? '')),
            'min_price' => $minPrice,
            'max_price' => $maxPrice,
            'sort' => in_array($filters['sort'] ?? 'default', [
                'default', 'price_asc', 'price_desc', 'title_asc', 'title_desc',
            ], true) ? ($filters['sort'] ?? 'default') : 'default',
            'page' => max(1, (int) ($filters['page'] ?? 1)),
            'per_page' => (int) ($filters['per_page'] ?? self::DEFAULT_PER_PAGE),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function queryParamsForPagination(array $filters): array
    {
        $params = [];

        foreach (['q', 'category', 'stock', 'tag', 'sort', 'per_page'] as $key) {
            if ($filters[$key] !== '' && $filters[$key] !== null && $filters[$key] !== 'default') {
                $params[$key] = $filters[$key];
            }
        }

        if ($filters['min_price'] !== null) {
            $params['min_price'] = $filters['min_price'];
        }

        if ($filters['max_price'] !== null) {
            $params['max_price'] = $filters['max_price'];
        }

        return $params;
    }
}
