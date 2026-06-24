<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CatalogAnalyticsEvent extends Model
{
    public const TYPE_VIEW = 'view';

    public const TYPE_CART_ADD = 'cart_add';

    public const TYPE_CHECKOUT_WHATSAPP = 'checkout_whatsapp';

    public const TYPE_CHECKOUT_INVOICE = 'checkout_invoice';

    public const TYPE_LISTING_INQUIRY = 'listing_inquiry';

    public $timestamps = false;

    protected $fillable = [
        'company_id',
        'catalog_id',
        'event_type',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    public function catalog(): BelongsTo
    {
        return $this->belongsTo(ListCatalog::class, 'catalog_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
