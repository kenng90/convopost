<?php

namespace App\Models;

use App\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class CatalogCollection extends Model
{
    protected $fillable = [
        'company_id',
        'name',
        'slug',
        'description',
        'image_url',
        'is_active',
        'sort_order',
        'metadata',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'metadata' => 'array',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function items(): BelongsToMany
    {
        return $this->belongsToMany(CatalogItem::class, 'catalog_collection_items', 'collection_id', 'catalog_item_id')
            ->withPivot('sort_order')
            ->orderBy('catalog_collection_items.sort_order')
            ->withTimestamps();
    }
}
