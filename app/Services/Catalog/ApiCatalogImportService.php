<?php

namespace App\Services\Catalog;

use App\Models\ListCatalog;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class ApiCatalogImportService
{
    public function __construct(
        protected CatalogItemRepository $itemRepository,
        protected CatalogReimportService $reimportService,
        protected CatalogItemPayloadService $catalogItemPayloadService,
        protected CatalogTemplateRegistry $catalogTemplateRegistry,
        protected CatalogItemImageService $catalogItemImageService,
    ) {
    }

    /**
     * @return array{items: list<array<string, mixed>>, total: int}
     */
    public function fetchFromConfig(array $apiConfig, ?ListCatalog $catalog = null): array
    {
        $url = $apiConfig['url'] ?? null;
        if (! $url) {
            throw new RuntimeException('API URL is required in api_config.');
        }

        $method = strtoupper($apiConfig['method'] ?? 'GET');
        $headers = $apiConfig['headers'] ?? [];
        $params = $apiConfig['params'] ?? [];
        $dataPath = $apiConfig['data_path'] ?? 'data';

        $request = Http::timeout((int) ($apiConfig['timeout'] ?? 30))->withHeaders($headers);

        $response = match ($method) {
            'POST' => $request->post($url, $params),
            'PUT' => $request->put($url, $params),
            default => $request->get($url, $params),
        };

        if (! $response->successful()) {
            throw new RuntimeException('API request failed with status '.$response->status());
        }

        $payload = $response->json();
        $rows = $this->extractByPath($payload, $dataPath);

        if (! is_array($rows)) {
            throw new RuntimeException("Data at path '{$dataPath}' is not an array.");
        }

        $mapping = $apiConfig['column_mapping'] ?? [];
        if ($mapping === [] && $catalog !== null && ! $catalog->isCommerce()) {
            $mapping = $this->catalogTemplateRegistry->defaultApiColumnMapping($catalog->resolvedVertical());
        }

        $items = array_map(
            fn ($row) => $this->mapRow(is_array($row) ? $row : (array) $row, $mapping, $catalog),
            $rows
        );

        return [
            'items' => array_values(array_filter($items, fn ($item) => ($item['title'] ?? '') !== '')),
            'total' => count($items),
        ];
    }

    public function importIntoCatalog(ListCatalog $catalog, bool $replaceMissing = false): array
    {
        $config = $catalog->api_config;
        if (! is_array($config) || empty($config['url'])) {
            throw new RuntimeException('Catalog has no API configuration.');
        }

        $fetched = $this->fetchFromConfig($config, $catalog);
        $merge = $this->reimportService->mergeByItemId(
            $this->itemRepository->getItemsArray($catalog),
            $fetched['items'],
            $replaceMissing
        );

        $this->itemRepository->replaceAllFromArray($catalog, $merge['items']);

        $catalog->update([
            'source' => 'api',
            'metadata' => array_merge($catalog->metadata ?? [], [
                'last_api_import_at' => now()->toIso8601String(),
                'api_import_stats' => [
                    'added' => $merge['added'],
                    'updated' => $merge['updated'],
                ],
            ]),
        ]);

        return $merge;
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, string>  $mapping
     * @return array<string, mixed>
     */
    private function mapRow(array $row, array $mapping, ?ListCatalog $catalog = null): array
    {
        $get = function (string $field, array $fallbacks = []) use ($row, $mapping) {
            $key = $mapping[$field] ?? $field;
            if (isset($row[$key])) {
                return $row[$key];
            }
            foreach ($fallbacks as $fallback) {
                if (isset($row[$fallback])) {
                    return $row[$fallback];
                }
            }

            return null;
        };

        $input = [
            'id' => (string) ($get('id', ['sku', 'product_id', 'ID', 'reference']) ?: uniqid('api_')),
            'title' => (string) ($get('title', ['name', 'product_name']) ?? ''),
            'description' => (string) ($get('description', ['desc', 'summary']) ?? ''),
            'price' => (float) ($get('price', ['amount', 'cost']) ?? 0),
            'category' => (string) ($get('category', ['type', 'group']) ?? ''),
            'imageUrl' => (string) ($get('imageUrl', ['image', 'image_url', 'thumbnail']) ?? ''),
            'stockStatus' => (string) ($get('stockStatus', ['stock_status', 'availability']) ?? 'In Stock'),
            'quantityAvailable' => $get('quantityAvailable', ['stock', 'quantity', 'inventory_quantity']),
            'variants' => $get('variants') ?? [],
            'tags' => $get('tags') ?? [],
        ];

        $images = $get('images', ['gallery', 'photos', 'pictures']);
        if (is_array($images)) {
            $input['images'] = array_values(array_filter(array_map('strval', $images)));
        } elseif (is_string($images) && $images !== '') {
            $input['imagesText'] = $images;
        }

        if ($catalog !== null && ! $catalog->isCommerce()) {
            foreach ($catalog->presentation()['item_fields'] ?? [] as $field) {
                $key = (string) ($field['key'] ?? '');
                if ($key === '') {
                    continue;
                }

                $value = $get($key);
                if ($value !== null && $value !== '') {
                    $input[$key] = $value;
                }
            }

            return $this->catalogItemPayloadService->buildPayload($input, $catalog);
        }

        $images = $this->catalogItemImageService->normalize($input);
        $input['images'] = $images;
        $input['imageUrl'] = $this->catalogItemImageService->primaryUrl($images);

        return $input;
    }

    private function extractByPath(mixed $data, string $path): mixed
    {
        foreach (explode('.', $path) as $segment) {
            if (! is_array($data) || ! array_key_exists($segment, $data)) {
                return null;
            }
            $data = $data[$segment];
        }

        return $data;
    }
}
