<?php

namespace App\Models;

use App\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CatalogCartSession extends Model
{
    protected $fillable = [
        'company_id',
        'catalog_id',
        'contact_id',
        'visitor_key',
        'items',
        'customer_phone',
        'customer_name',
        'last_activity_at',
        'abandoned_at',
        'converted_at',
    ];

    protected $casts = [
        'items' => 'array',
        'last_activity_at' => 'datetime',
        'abandoned_at' => 'datetime',
        'converted_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function catalog(): BelongsTo
    {
        return $this->belongsTo(ListCatalog::class, 'catalog_id');
    }

    public function itemCount(): int
    {
        if (! is_array($this->items)) {
            return 0;
        }

        return (int) array_sum(array_map(
            fn ($item) => (int) ($item['quantity'] ?? 0),
            $this->items
        ));
    }
}
