<?php

namespace App\Services\Catalog;

class CatalogItemImageService
{
    public const MAX_IMAGES = 12;

    /**
     * @param  array<string, mixed>  $input
     * @param  array<string, mixed>|null  $existing
     * @return list<string>
     */
    public function normalize(array $input, ?array $existing = null): array
    {
        $images = [];

        if (isset($input['images']) && is_array($input['images'])) {
            foreach ($input['images'] as $url) {
                $url = trim((string) $url);
                if ($url !== '') {
                    $images[] = $url;
                }
            }
        }

        foreach (['imagesText', 'images_text', 'imageUrls'] as $textKey) {
            if (! isset($input[$textKey]) || ! is_string($input[$textKey])) {
                continue;
            }

            foreach (preg_split('/[\r\n,]+/', $input[$textKey]) ?: [] as $url) {
                $url = trim($url);
                if ($url !== '') {
                    $images[] = $url;
                }
            }
        }

        $primary = trim((string) ($input['imageUrl'] ?? $input['image_url'] ?? ''));
        if ($primary !== '' && ! in_array($primary, $images, true)) {
            array_unshift($images, $primary);
        }

        if ($images === [] && is_array($existing)) {
            $existingImages = $existing['images'] ?? ($existing['metadata']['images'] ?? []);
            if (is_array($existingImages)) {
                foreach ($existingImages as $url) {
                    $url = trim((string) $url);
                    if ($url !== '') {
                        $images[] = $url;
                    }
                }
            }

            $existingPrimary = trim((string) ($existing['imageUrl'] ?? ''));
            if ($existingPrimary !== '' && ! in_array($existingPrimary, $images, true)) {
                array_unshift($images, $existingPrimary);
            }
        }

        $images = array_values(array_unique($images));

        return array_slice($images, 0, self::MAX_IMAGES);
    }

    /**
     * @param  array<string, mixed>  $item
     * @return list<string>
     */
    public function forItem(array $item): array
    {
        $images = [];

        if (is_array($item['images'] ?? null)) {
            foreach ($item['images'] as $url) {
                $url = trim((string) $url);
                if ($url !== '') {
                    $images[] = $url;
                }
            }
        }

        $metadataImages = $item['metadata']['images'] ?? null;
        if (is_array($metadataImages)) {
            foreach ($metadataImages as $url) {
                $url = trim((string) $url);
                if ($url !== '' && ! in_array($url, $images, true)) {
                    $images[] = $url;
                }
            }
        }

        $primary = trim((string) ($item['imageUrl'] ?? ''));
        if ($primary !== '' && ! in_array($primary, $images, true)) {
            array_unshift($images, $primary);
        }

        return array_slice(array_values(array_unique($images)), 0, self::MAX_IMAGES);
    }

    /**
     * @param  list<string>  $images
     */
    public function primaryUrl(array $images): string
    {
        return $images[0] ?? '';
    }
}
