<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CatalogSyncState extends Model
{
    protected $fillable = [
        'catalog_id',
        'company_id',
        'store_type',
        'direction',
        'status',
        'last_pulled_at',
        'last_pushed_at',
        'last_error',
        'webhook_ids',
        'metadata',
    ];

    protected $casts = [
        'last_pulled_at' => 'datetime',
        'last_pushed_at' => 'datetime',
        'webhook_ids' => 'array',
        'metadata' => 'array',
    ];

    public function catalog(): BelongsTo
    {
        return $this->belongsTo(ListCatalog::class, 'catalog_id');
    }
}
