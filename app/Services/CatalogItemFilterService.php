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
     * @param  array<string, mixed>|null  $presentation
     * @return array{
     *     items: LengthAwarePaginator,
     *     filterOptions: array{categories: list<string>, tags: list<string>, statuses: list<string>, locations: list<string>},
     *     filters: array<string, mixed>,
     *     totalInCatalog: int,
     *     filteredTotal: int,
     *     mapMarkers: list<array{id: string, title: string, lat: float, lng: float, price: float|null}>
     * }
     */
    public function browse(array $items, array $filters, string $path, ?array $presentation = null): array
    {
        $normalizedFilters = $this->normalizeFilters($filters, $presentation);
        $filterOptions = $this->extractFilterOptions($items, $presentation);
        $filtered = $this->applyFilters($items, $normalizedFilters, $presentation);
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
            'mapMarkers' => $this->mapMarkers($sorted),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @param  array<string, mixed>|null  $presentation
     * @return array{categories: list<string>, tags: list<string>, statuses: list<string>, locations: list<string>}
     */
    public function extractFilterOptions(array $items, ?array $presentation = null): array
    {
        $categories = [];
        $tags = [];
        $statuses = [];
        $locations = [];
        $statusField = $presentation['status_field'] ?? 'stockStatus';

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

            $status = trim((string) $this->itemFieldValue($item, $statusField));
            if ($status !== '') {
                $statuses[$status] = $status;
            }

            $location = trim((string) $this->itemFieldValue($item, 'location'));
            if ($location !== '') {
                $locations[$location] = $location;
            }
        }

        $categoryList = array_values($categories);
        $tagList = array_values($tags);
        $statusList = array_values($statuses);
        $locationList = array_values($locations);
        sort($categoryList, SORT_NATURAL | SORT_FLAG_CASE);
        sort($tagList, SORT_NATURAL | SORT_FLAG_CASE);
        sort($statusList, SORT_NATURAL | SORT_FLAG_CASE);
        sort($locationList, SORT_NATURAL | SORT_FLAG_CASE);

        return [
            'categories' => $categoryList,
            'tags' => $tagList,
            'statuses' => $statusList,
            'locations' => $locationList,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @param  array<string, mixed>  $filters
     * @param  array<string, mixed>|null  $presentation
     * @return list<array<string, mixed>>
     */
    public function applyFilters(array $items, array $filters, ?array $presentation = null): array
    {
        $statusField = $presentation['status_field'] ?? 'stockStatus';

        return array_values(array_filter($items, function (array $item) use ($filters, $statusField) {
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
                $stockStatus = (string) $this->itemFieldValue($item, 'stockStatus');
                if ($stockStatus !== $filters['stock']) {
                    return false;
                }
            }

            if ($filters['status'] !== '') {
                $itemStatus = (string) $this->itemFieldValue($item, $statusField);
                if ($itemStatus !== $filters['status']) {
                    return false;
                }
            }

            if ($filters['location'] !== '') {
                $itemLocation = (string) $this->itemFieldValue($item, 'location');
                if (strcasecmp($itemLocation, $filters['location']) !== 0) {
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

            if ($filters['near_lat'] !== null && $filters['near_lng'] !== null) {
                $coords = $this->itemCoordinates($item);
                if ($coords === null) {
                    return false;
                }

                $distance = $this->haversineKm(
                    $filters['near_lat'],
                    $filters['near_lng'],
                    $coords['lat'],
                    $coords['lng']
                );

                if ($distance > $filters['radius_km']) {
                    return false;
                }
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
     * @param  array<string, mixed>|null  $presentation
     * @return array<string, mixed>
     */
    public function normalizeFilters(array $filters, ?array $presentation = null): array
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

        $statusOptions = $presentation['status_options'] ?? [];

        return [
            'q' => trim((string) ($filters['q'] ?? '')),
            'category' => trim((string) ($filters['category'] ?? '')),
            'stock' => trim((string) ($filters['stock'] ?? '')),
            'status' => trim((string) ($filters['status'] ?? '')),
            'location' => trim((string) ($filters['location'] ?? '')),
            'tag' => trim((string) ($filters['tag'] ?? '')),
            'min_price' => $minPrice,
            'max_price' => $maxPrice,
            'near_lat' => isset($filters['near_lat']) && $filters['near_lat'] !== '' && $filters['near_lat'] !== null
                ? (float) $filters['near_lat']
                : null,
            'near_lng' => isset($filters['near_lng']) && $filters['near_lng'] !== '' && $filters['near_lng'] !== null
                ? (float) $filters['near_lng']
                : null,
            'radius_km' => isset($filters['radius_km']) && $filters['radius_km'] !== '' && $filters['radius_km'] !== null
                ? max(1.0, min(500.0, (float) $filters['radius_km']))
                : 25.0,
            'sort' => in_array($filters['sort'] ?? 'default', [
                'default', 'price_asc', 'price_desc', 'title_asc', 'title_desc',
            ], true) ? ($filters['sort'] ?? 'default') : 'default',
            'page' => max(1, (int) ($filters['page'] ?? 1)),
            'per_page' => (int) ($filters['per_page'] ?? self::DEFAULT_PER_PAGE),
            'status_options' => $statusOptions,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function queryParamsForPagination(array $filters): array
    {
        $params = [];

        foreach (['q', 'category', 'stock', 'status', 'location', 'tag', 'sort', 'per_page'] as $key) {
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

        if ($filters['near_lat'] !== null) {
            $params['near_lat'] = $filters['near_lat'];
            $params['near_lng'] = $filters['near_lng'];
            $params['radius_km'] = $filters['radius_km'];
        }

        return $params;
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return list<array{id: string, title: string, lat: float, lng: float, price: float|null}>
     */
    public function mapMarkers(array $items): array
    {
        $markers = [];

        foreach ($items as $item) {
            $coords = $this->itemCoordinates($item);
            if ($coords === null) {
                continue;
            }

            $markers[] = [
                'id' => (string) ($item['id'] ?? ''),
                'title' => (string) ($item['title'] ?? 'Listing'),
                'lat' => $coords['lat'],
                'lng' => $coords['lng'],
                'price' => isset($item['price']) ? (float) $item['price'] : null,
            ];
        }

        return $markers;
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array{lat: float, lng: float}|null
     */
    private function itemCoordinates(array $item): ?array
    {
        $lat = $this->itemFieldValue($item, 'latitude');
        $lng = $this->itemFieldValue($item, 'longitude');

        if ($lat === null || $lng === null || $lat === '' || $lng === '') {
            return null;
        }

        if (! is_numeric($lat) || ! is_numeric($lng)) {
            return null;
        }

        $lat = (float) $lat;
        $lng = (float) $lng;

        if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
            return null;
        }

        return ['lat' => $lat, 'lng' => $lng];
    }

    private function haversineKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return $earthRadius * (2 * atan2(sqrt($a), sqrt(1 - $a)));
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function itemFieldValue(array $item, string $fieldKey): mixed
    {
        $metadata = is_array($item['metadata'] ?? null) ? $item['metadata'] : [];

        if (array_key_exists($fieldKey, $metadata) && $metadata[$fieldKey] !== null && $metadata[$fieldKey] !== '') {
            return $metadata[$fieldKey];
        }

        return $item[$fieldKey] ?? null;
    }
}
