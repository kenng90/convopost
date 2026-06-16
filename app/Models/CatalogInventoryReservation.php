<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CatalogInventoryReservation extends Model
{
    public const STATUS_RESERVED = 'reserved';

    public const STATUS_COMMITTED = 'committed';

    public const STATUS_RELEASED = 'released';

    protected $fillable = [
        'catalog_item_id',
        'company_id',
        'catalog_id',
        'quantity',
        'status',
        'reference_type',
        'reference_id',
        'expires_at',
        'committed_at',
        'released_at',
        'metadata',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'committed_at' => 'datetime',
        'released_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function catalogItem(): BelongsTo
    {
        return $this->belongsTo(CatalogItem::class);
    }

    public function catalog(): BelongsTo
    {
        return $this->belongsTo(ListCatalog::class, 'catalog_id');
    }
}
