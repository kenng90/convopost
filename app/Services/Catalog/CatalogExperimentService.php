<?php

namespace App\Services\Catalog;

use App\Models\ListCatalog;
use App\Scopes\CompanyScope;
use Illuminate\Support\Facades\Schema;

class CatalogExperimentService
{
    /**
     * Deterministically pick a catalog variant for an experiment key.
     */
    public function resolveCatalog(string $experimentKey, int $companyId, ?string $visitorKey = null): ?ListCatalog
    {
        $variants = ListCatalog::withoutGlobalScope(CompanyScope::class)
            ->where('company_id', $companyId)
            ->where('experiment_key', $experimentKey)
            ->where('publish_status', 'published')
            ->whereNull('parent_id')
            ->orderBy('id')
            ->get();

        if ($variants->isEmpty()) {
            return null;
        }

        if ($variants->count() === 1) {
            return $variants->first();
        }

        $bucket = $this->bucketForVisitor($visitorKey ?? $experimentKey, 100);
        $cursor = 0;

        foreach ($variants as $variant) {
            $weight = max(0, min(100, (int) ($variant->traffic_weight ?? 0)));
            $cursor += $weight;
            if ($bucket < $cursor) {
                return $variant;
            }
        }

        return $variants->last();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listExperiments(int $companyId): array
    {
        return ListCatalog::withoutGlobalScope(CompanyScope::class)
            ->where('company_id', $companyId)
            ->whereNotNull('experiment_key')
            ->get()
            ->groupBy('experiment_key')
            ->map(function ($group, $key) {
                return [
                    'experiment_key' => $key,
                    'variants' => $group->map(fn (ListCatalog $c) => [
                        'id' => $c->id,
                        'name' => $c->name,
                        'traffic_weight' => $c->traffic_weight,
                        'publish_status' => $c->publish_status,
                        'item_count' => Schema::hasTable('catalog_items')
                            ? $c->catalogItems()->count()
                            : count(json_decode($c->getRawOriginal('items') ?? '[]', true) ?: []),
                    ])->values()->all(),
                ];
            })
            ->values()
            ->all();
    }

    public function createVariant(ListCatalog $base, array $overrides = []): ListCatalog
    {
        $experimentKey = $base->experiment_key ?: 'exp-'.$base->id;

        $items = $this->itemRepository()->getItemsArray($base);

        $variant = ListCatalog::create([
            'company_id' => $base->company_id,
            'name' => $overrides['name'] ?? ($base->name.' (Variant)'),
            'description' => $overrides['description'] ?? $base->description,
            'experiment_key' => $experimentKey,
            'traffic_weight' => $overrides['traffic_weight'] ?? 50,
            'publish_status' => $overrides['publish_status'] ?? 'draft',
            'version' => 1,
            'items' => $items,
            'columns' => $base->columns,
            'source' => $base->source,
            'metadata' => array_merge($base->metadata ?? [], ['variant_of' => $base->id]),
        ]);

        if ($base->experiment_key === null) {
            $base->update(['experiment_key' => $experimentKey]);
        }

        $this->itemRepository()->replaceAllFromArray($variant, $items);

        return $variant->fresh();
    }

    private function bucketForVisitor(string $visitorKey, int $modulo): int
    {
        return abs(crc32($visitorKey)) % $modulo;
    }

    private function itemRepository(): CatalogItemRepository
    {
        return app(CatalogItemRepository::class);
    }
}
