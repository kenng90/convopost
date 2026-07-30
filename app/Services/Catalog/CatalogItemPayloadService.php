<?php

namespace App\Services\Catalog;

use App\Models\ListCatalog;

class CatalogItemPayloadService
{
    public function __construct(
        protected CatalogTemplateRegistry $catalogTemplateRegistry,
        protected CatalogItemImageService $catalogItemImageService,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function rulesForAdd(ListCatalog $catalog): array
    {
        return array_merge([
            'id' => 'required|string|max:100',
        ], $this->sharedRules($catalog));
    }

    /**
     * @return array<string, mixed>
     */
    public function rulesForUpdate(ListCatalog $catalog): array
    {
        return $this->sharedRules($catalog);
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function buildPayload(array $input, ListCatalog $catalog, ?array $existing = null): array
    {
        $presentation = $catalog->presentation();
        $supportsInventory = (bool) ($presentation['supports_inventory'] ?? true);
        $statusField = (string) ($presentation['status_field'] ?? 'stockStatus');

        $metadata = is_array($existing['metadata'] ?? null) ? $existing['metadata'] : [];
        if (is_array($input['metadata'] ?? null)) {
            $metadata = array_merge($metadata, $input['metadata']);
        }

        foreach ($presentation['item_fields'] ?? [] as $field) {
            $key = (string) ($field['key'] ?? '');
            if ($key === '' || ! array_key_exists($key, $input)) {
                continue;
            }

            $value = $input[$key];
            if ($value !== null && $value !== '') {
                $metadata[$key] = is_string($value) ? trim($value) : $value;
            }
        }

        if (array_key_exists('booking_source_id', $input)) {
            if ($input['booking_source_id'] === null || $input['booking_source_id'] === '' || (int) $input['booking_source_id'] <= 0) {
                unset($metadata['booking_source_id']);
            } else {
                $metadata['booking_source_id'] = (int) $input['booking_source_id'];
            }
        }

        $payload = [
            'id' => (string) ($input['id'] ?? $existing['id'] ?? ''),
            'title' => (string) ($input['title'] ?? $existing['title'] ?? ''),
            'description' => (string) ($input['description'] ?? $existing['description'] ?? ''),
            'price' => (float) ($input['price'] ?? $existing['price'] ?? 0),
            'category' => (string) ($input['category'] ?? $existing['category'] ?? ''),
            'tags' => is_array($input['tags'] ?? null) ? $input['tags'] : ($existing['tags'] ?? []),
        ];

        $images = $this->catalogItemImageService->normalize($input, $existing);
        $payload['images'] = $images;
        $payload['imageUrl'] = $this->catalogItemImageService->primaryUrl($images);
        if ($images !== []) {
            $metadata['images'] = $images;
        }

        if ($supportsInventory) {
            $payload['stockStatus'] = (string) ($input['stockStatus'] ?? $existing['stockStatus'] ?? 'In Stock');
            $payload['quantityAvailable'] = array_key_exists('quantityAvailable', $input)
                ? $input['quantityAvailable']
                : ($existing['quantityAvailable'] ?? null);
            $payload['variants'] = is_array($input['variants'] ?? null)
                ? $input['variants']
                : ($existing['variants'] ?? []);
        } else {
            $payload['stockStatus'] = 'In Stock';
            $payload['variants'] = [];
            $payload['quantityAvailable'] = null;

            if ($statusField !== 'stockStatus') {
                $statusValue = $input[$statusField] ?? $metadata[$statusField] ?? $existing[$statusField] ?? 'Available';
                $metadata[$statusField] = (string) $statusValue;
            }
        }

        if ($metadata !== []) {
            $payload['metadata'] = $metadata;

            foreach ($metadata as $key => $value) {
                if ($value !== null && $value !== '' && ! array_key_exists($key, $payload)) {
                    $payload[$key] = $value;
                }
            }
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    private function sharedRules(ListCatalog $catalog): array
    {
        $presentation = $catalog->presentation();
        $rules = [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'price' => 'nullable|numeric|min:0',
            'category' => 'nullable|string|max:255',
            'imageUrl' => 'nullable|string|max:2048',
            'images' => 'nullable|array',
            'images.*' => 'nullable|string|max:2048',
            'imagesText' => 'nullable|string|max:12000',
            'tags' => 'nullable|array',
            'booking_source_id' => 'nullable|integer',
            'metadata' => 'nullable|array',
        ];

        if ($presentation['supports_inventory'] ?? true) {
            $rules['stockStatus'] = 'nullable|string|in:In Stock,Out of Stock,Low Stock';
            $rules['quantityAvailable'] = 'nullable|integer|min:0';
            $rules['variants'] = 'nullable|array';
        } else {
            foreach ($presentation['item_fields'] ?? [] as $field) {
                $key = (string) ($field['key'] ?? '');
                if ($key === '') {
                    continue;
                }

                $type = (string) ($field['type'] ?? 'text');
                if ($type === 'number') {
                    $rules[$key] = $this->numberFieldRule($key);
                } elseif ($type === 'select') {
                    $options = $field['options'] ?? [];
                    if ($options !== []) {
                        $rules[$key] = 'nullable|string|in:'.implode(',', $options);
                    } else {
                        $rules[$key] = 'nullable|string|max:255';
                    }
                } else {
                    $rules[$key] = 'nullable|string|max:255';
                }
            }
        }

        return $rules;
    }

    private function numberFieldRule(string $key): string
    {
        return match ($key) {
            'latitude' => 'nullable|numeric|min:-90|max:90',
            'longitude' => 'nullable|numeric|min:-180|max:180',
            default => 'nullable|numeric|min:0',
        };
    }
}
