<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CatalogOrderItem extends Model
{
    protected $fillable = [
        'catalog_order_id',
        'item_id',
        'title',
        'quantity',
        'unit_price',
        'line_total',
        'variant',
        'metadata',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_price' => 'float',
        'line_total' => 'float',
        'metadata' => 'array',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(CatalogOrder::class, 'catalog_order_id');
    }
}
