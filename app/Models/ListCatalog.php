<?php

namespace App\Models;

use App\Scopes\CompanyScope;
use App\Services\Catalog\CatalogCategoryNormalizer;
use App\Services\Catalog\CatalogFlowUsageService;
use App\Services\Catalog\CatalogItemRepository;
use App\Services\Catalog\CatalogUrlService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class ListCatalog extends Model
{
    use HasFactory;

    protected $table = 'list_catalogs';

    protected $guarded = [];

    protected $casts = [
        'items' => 'array',
        'columns' => 'array',
        'metadata' => 'array',
        'api_config' => 'array',
        'retailer_ids' => 'array',
    ];

    protected static function booted()
    {
        static::addGlobalScope(new CompanyScope);

        static::creating(function ($model) {
            $company_id = session('company_id', null);
            if ($company_id) {
                $model->company_id = $company_id;
            }

            if (empty($model->slug) && ! empty($model->name)) {
                $model->slug = app(CatalogUrlService::class)->assignSlug($model, $model->name);
            }

            if (is_array($model->items)) {
                $model->items = app(CatalogCategoryNormalizer::class)->normalizeItems($model->items);
            }
        });

        static::updating(function ($model) {
            if ($model->isDirty('name') && empty($model->slug)) {
                $model->slug = app(CatalogUrlService::class)->assignSlug($model, $model->name);
            }

            if ($model->isDirty('items') && is_array($model->items)) {
                $model->items = app(CatalogCategoryNormalizer::class)->normalizeItems($model->items);
            }
        });

        static::created(function ($model) {
            if (! Schema::hasTable('catalog_items')) {
                return;
            }

            $rawItems = $model->getAttributes()['items'] ?? null;
            if (is_string($rawItems)) {
                $rawItems = json_decode($rawItems, true);
            }

            if (! is_array($rawItems) || $rawItems === []) {
                return;
            }

            if (! $model->catalogItems()->exists()) {
                app(CatalogItemRepository::class)->replaceAllFromArray($model, $rawItems);
            }
        });

        static::updated(function ($model) {
            if (! Schema::hasTable('catalog_items') || ! $model->wasChanged('items')) {
                return;
            }

            $rawItems = $model->getAttributes()['items'] ?? '[]';
            if (is_string($rawItems)) {
                $rawItems = json_decode($rawItems, true) ?: [];
            }

            if (is_array($rawItems)) {
                app(CatalogItemRepository::class)->replaceAllFromArray($model, $rawItems, syncJson: false);
            }
        });
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function parent()
    {
        return $this->belongsTo(ListCatalog::class, 'parent_id');
    }

    public function versions()
    {
        return $this->hasMany(ListCatalog::class, 'parent_id');
    }

    public function analyticsEvents()
    {
        return $this->hasMany(CatalogAnalyticsEvent::class, 'catalog_id');
    }

    public function catalogItems()
    {
        return $this->hasMany(CatalogItem::class, 'catalog_id');
    }

    public function syncStates()
    {
        return $this->hasMany(CatalogSyncState::class, 'catalog_id');
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getItemsAttribute($value): array
    {
        if ($this->relationLoaded('catalogItems') && $this->catalogItems->isNotEmpty()) {
            return $this->catalogItems
                ->where('is_active', true)
                ->map(fn (CatalogItem $item) => $item->toCatalogArray())
                ->values()
                ->all();
        }

        if ($this->exists && Schema::hasTable('catalog_items') && $this->catalogItems()->exists()) {
            return app(CatalogItemRepository::class)->getItemsArray($this);
        }

        $decoded = is_array($value) ? $value : (json_decode($value ?? '[]', true) ?: []);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Get the root catalog (version 1)
     */
    public function getRootCatalog()
    {
        return $this->parent_id ? $this->parent()->getRootCatalog() : $this;
    }

    /**
     * Get all versions of this catalog
     */
    public function getAllVersions()
    {
        $root = $this->getRootCatalog();

        return collect([$root])->merge($root->versions)->sortBy('version');
    }

    /**
     * Create a new version from current catalog
     */
    public function createNewVersion(array $newData)
    {
        $root = $this->getRootCatalog();
        $maxVersion = $root->getAllVersions()->max('version');

        return self::create([
            'company_id' => $this->company_id,
            'name' => $this->name,
            'slug' => app(CatalogUrlService::class)->assignSlug($this, $this->name.'-v'.($maxVersion + 1)),
            'version' => $maxVersion + 1,
            'parent_id' => $root->id,
            'description' => $newData['description'] ?? $this->description,
            'items' => $newData['items'] ?? $this->items,
            'columns' => $newData['columns'] ?? $this->columns,
            'source' => $newData['source'] ?? $this->source,
            'original_file_name' => $newData['original_file_name'] ?? $this->original_file_name,
            'metadata' => $newData['metadata'] ?? $this->metadata,
        ]);
    }

    public function publicUrl(): string
    {
        return app(CatalogUrlService::class)->publicUrl($this);
    }

    /**
     * Check if catalog is being used in any flows
     */
    public function isInUse(): bool
    {
        return app(CatalogFlowUsageService::class)->isCatalogInUse($this->id, $this->company_id);
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    public function flowsInUse(): array
    {
        return app(CatalogFlowUsageService::class)->flowsUsingCatalog($this->id, $this->company_id);
    }
}
