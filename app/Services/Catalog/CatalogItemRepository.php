<?php

namespace App\Services\Catalog;

use App\Models\CatalogItem;
use App\Models\ListCatalog;
use App\Scopes\CompanyScope;
use Illuminate\Support\Facades\DB;

class CatalogItemRepository
{
    public function __construct(
        protected CatalogCategoryNormalizer $categoryNormalizer,
    ) {
    }

    public static function relationalTableExists(): bool
    {
        static $exists = null;

        if ($exists === null) {
            $exists = \Illuminate\Support\Facades\Schema::hasTable('catalog_items');
        }

        return $exists;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getItemsArray(ListCatalog $catalog): array
    {
        if (! self::relationalTableExists()) {
            return $this->itemsFromJsonColumn($catalog);
        }
        if ($catalog->relationLoaded('catalogItems')) {
            return $catalog->catalogItems
                ->where('is_active', true)
                ->map(fn (CatalogItem $item) => $item->toCatalogArray())
                ->values()
                ->all();
        }

        $relational = CatalogItem::withoutGlobalScopes()
            ->where('catalog_id', $catalog->id)
            ->where('is_active', true)
            ->orderBy('id')
            ->get();

        if ($relational->isNotEmpty()) {
            return $relational->map(fn (CatalogItem $item) => $item->toCatalogArray())->all();
        }

        return $this->itemsFromJsonColumn($catalog);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function itemsFromJsonColumn(ListCatalog $catalog): array
    {
        $raw = $catalog->getRawOriginal('items');
        $decoded = is_array($raw) ? $raw : (json_decode($raw ?? '[]', true) ?: []);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param  list<array<string, mixed>>  $items
     */
    public function replaceAllFromArray(ListCatalog $catalog, array $items, bool $syncJson = true): void
    {
        $items = $this->categoryNormalizer->normalizeItems($items);

        if (! self::relationalTableExists()) {
            $catalog->forceFill(['items' => $items])->saveQuietly();

            return;
        }

        DB::transaction(function () use ($catalog, $items, $syncJson) {
            CatalogItem::withoutGlobalScopes()
                ->where('catalog_id', $catalog->id)
                ->delete();

            foreach ($items as $row) {
                $attrs = CatalogItem::fromCatalogArray($row, $catalog->id, $catalog->company_id);
                if ($attrs['title'] === '') {
                    continue;
                }
                CatalogItem::withoutGlobalScopes()->create($attrs);
            }

            if ($syncJson) {
                $catalog->forceFill(['items' => $items])->saveQuietly();
            }
        });
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public function upsertFromArray(ListCatalog $catalog, array $row): ?CatalogItem
    {
        $attrs = CatalogItem::fromCatalogArray(
            $this->categoryNormalizer->normalizeItems([$row])[0],
            $catalog->id,
            $catalog->company_id
        );

        if (! self::relationalTableExists()) {
            $items = $this->itemsFromJsonColumn($catalog);
            $found = false;

            foreach ($items as $index => $item) {
                if (($item['id'] ?? null) === $attrs['item_id']) {
                    $items[$index] = array_merge($item, $row);
                    $found = true;
                    break;
                }
            }

            if (! $found) {
                $items[] = $row;
            }

            $catalog->forceFill(['items' => $items])->saveQuietly();

            return null;
        }

        $item = CatalogItem::withoutGlobalScopes()->updateOrCreate(
            ['catalog_id' => $catalog->id, 'item_id' => $attrs['item_id']],
            $attrs
        );

        $this->syncJsonColumn($catalog);

        return $item;
    }

    public function deleteByItemId(ListCatalog $catalog, string $itemId): void
    {
        if (! self::relationalTableExists()) {
            $items = array_values(array_filter(
                $this->itemsFromJsonColumn($catalog),
                fn (array $item) => ($item['id'] ?? null) !== $itemId
            ));
            $catalog->forceFill(['items' => $items])->saveQuietly();

            return;
        }

        CatalogItem::withoutGlobalScopes()
            ->where('catalog_id', $catalog->id)
            ->where('item_id', $itemId)
            ->delete();

        $this->syncJsonColumn($catalog);
    }

    public function syncJsonColumn(ListCatalog $catalog): void
    {
        if (! self::relationalTableExists()) {
            return;
        }

        $items = CatalogItem::withoutGlobalScopes()
            ->where('catalog_id', $catalog->id)
            ->where('is_active', true)
            ->orderBy('id')
            ->get()
            ->map(fn (CatalogItem $item) => $item->toCatalogArray())
            ->all();

        $catalog->forceFill(['items' => $items])->saveQuietly();
    }

    public function countForCompany(int $companyId): int
    {
        if (! self::relationalTableExists()) {
            return (int) ListCatalog::withoutGlobalScope(CompanyScope::class)
                ->where('company_id', $companyId)
                ->get()
                ->sum(fn (ListCatalog $catalog) => count($this->itemsFromJsonColumn($catalog)));
        }

        return (int) CatalogItem::withoutGlobalScope(CompanyScope::class)
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->count();
    }

    public function findByCatalogItemId(ListCatalog $catalog, string $itemId): ?CatalogItem
    {
        if (! self::relationalTableExists()) {
            return null;
        }

        return CatalogItem::withoutGlobalScopes()
            ->where('catalog_id', $catalog->id)
            ->where('item_id', $itemId)
            ->first();
    }
}
