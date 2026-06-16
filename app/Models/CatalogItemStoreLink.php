<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CatalogItemStoreLink extends Model
{
    protected $fillable = [
        'catalog_item_id',
        'company_id',
        'store_type',
        'external_product_id',
        'external_variant_id',
        'external_inventory_item_id',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function catalogItem(): BelongsTo
    {
        return $this->belongsTo(CatalogItem::class);
    }
}
