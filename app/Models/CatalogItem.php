<?php

namespace App\Models;

use App\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CatalogItem extends Model
{
    public const STOCK_IN = 'In Stock';

    public const STOCK_OUT = 'Out of Stock';

    public const STOCK_LOW = 'Low Stock';

    protected $fillable = [
        'catalog_id',
        'company_id',
        'item_id',
        'title',
        'description',
        'price',
        'category',
        'image_url',
        'stock_status',
        'quantity_available',
        'quantity_reserved',
        'variants',
        'tags',
        'metadata',
        'is_active',
    ];

    protected $casts = [
        'price' => 'float',
        'variants' => 'array',
        'tags' => 'array',
        'metadata' => 'array',
        'is_active' => 'boolean',
        'quantity_available' => 'integer',
        'quantity_reserved' => 'integer',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope);
    }

    public function catalog(): BelongsTo
    {
        return $this->belongsTo(ListCatalog::class, 'catalog_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function storeLinks(): HasMany
    {
        return $this->hasMany(CatalogItemStoreLink::class);
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(CatalogInventoryReservation::class);
    }

    public function collections(): BelongsToMany
    {
        return $this->belongsToMany(CatalogCollection::class, 'catalog_collection_items', 'catalog_item_id', 'collection_id')
            ->withPivot('sort_order')
            ->withTimestamps();
    }

    public function availableQuantity(): ?int
    {
        if ($this->quantity_available === null) {
            return null;
        }

        return max(0, $this->quantity_available - (int) $this->quantity_reserved);
    }

    public function isPurchasable(int $quantity = 1): bool
    {
        if (! $this->is_active || $this->stock_status === self::STOCK_OUT) {
            return false;
        }

        $available = $this->availableQuantity();
        if ($available === null) {
            return true;
        }

        return $quantity <= $available;
    }

    /**
     * @return array<string, mixed>
     */
    public function toCatalogArray(): array
    {
        return [
            'id' => $this->item_id,
            'title' => $this->title,
            'description' => $this->description ?? '',
            'price' => $this->price,
            'category' => $this->category ?? '',
            'imageUrl' => $this->image_url ?? '',
            'stockStatus' => $this->stock_status,
            'quantityAvailable' => $this->availableQuantity(),
            'variants' => $this->variants ?? [],
            'tags' => $this->tags ?? [],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromCatalogArray(array $data, int $catalogId, int $companyId): array
    {
        $qty = $data['quantityAvailable'] ?? $data['quantity_available'] ?? null;

        return [
            'catalog_id' => $catalogId,
            'company_id' => $companyId,
            'item_id' => (string) ($data['id'] ?? uniqid('item_')),
            'title' => (string) ($data['title'] ?? ''),
            'description' => (string) ($data['description'] ?? ''),
            'price' => (float) ($data['price'] ?? 0),
            'category' => (string) ($data['category'] ?? ''),
            'image_url' => (string) ($data['imageUrl'] ?? $data['image_url'] ?? ''),
            'stock_status' => (string) ($data['stockStatus'] ?? self::STOCK_IN),
            'quantity_available' => $qty !== null && $qty !== '' ? (int) $qty : null,
            'variants' => $data['variants'] ?? [],
            'tags' => $data['tags'] ?? [],
            'metadata' => $data['metadata'] ?? null,
            'is_active' => (bool) ($data['is_active'] ?? true),
        ];
    }
}
