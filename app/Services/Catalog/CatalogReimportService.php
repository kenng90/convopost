<?php

namespace App\Services\Catalog;

class CatalogReimportService
{
    public function __construct(
        protected CatalogCategoryNormalizer $categoryNormalizer,
    ) {
    }

    /**
     * @param  list<array<string, mixed>>  $existing
     * @param  list<array<string, mixed>>  $imported
     * @return array{items: list<array<string, mixed>>, added: int, updated: int, unchanged: int}
     */
    public function mergeByItemId(array $existing, array $imported, bool $removeMissing = false): array
    {
        $imported = $this->categoryNormalizer->normalizeItems($imported);
        $existingById = [];

        foreach ($existing as $item) {
            $id = (string) ($item['id'] ?? '');
            if ($id !== '') {
                $existingById[$id] = $item;
            }
        }

        $added = 0;
        $updated = 0;
        $unchanged = 0;

        foreach ($imported as $item) {
            $id = (string) ($item['id'] ?? '');
            if ($id === '') {
                continue;
            }

            if (! isset($existingById[$id])) {
                $existingById[$id] = $item;
                $added++;

                continue;
            }

            if ($existingById[$id] != $item) {
                $existingById[$id] = $item;
                $updated++;
            } else {
                $unchanged++;
            }
        }

        if ($removeMissing) {
            $importIds = collect($imported)->pluck('id')->filter()->map(fn ($id) => (string) $id)->all();
            $existingById = array_filter(
                $existingById,
                fn ($item) => in_array((string) ($item['id'] ?? ''), $importIds, true)
            );
        }

        return [
            'items' => array_values($existingById),
            'added' => $added,
            'updated' => $updated,
            'unchanged' => $unchanged,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $existing
     * @param  list<array<string, mixed>>  $imported
     * @return array{to_add: int, to_update: int, to_remove: int, resulting_count: int}
     */
    public function previewMerge(array $existing, array $imported): array
    {
        $result = $this->mergeByItemId($existing, $imported, false);
        $importIds = collect($imported)->pluck('id')->filter()->map(fn ($id) => (string) $id)->all();
        $existingIds = collect($existing)->pluck('id')->filter()->map(fn ($id) => (string) $id)->all();
        $toRemove = count(array_diff($existingIds, $importIds));

        return [
            'to_add' => $result['added'],
            'to_update' => $result['updated'],
            'to_remove' => $toRemove,
            'resulting_count' => count($result['items']),
        ];
    }
}
