<?php

namespace App\Models;

use App\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Modules\Invoice\Models\Invoice;

class CatalogOrder extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_PAID = 'paid';

    public const STATUS_FULFILLING = 'fulfilling';

    public const STATUS_SHIPPED = 'shipped';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    public const CHANNEL_WHATSAPP = 'whatsapp';

    public const CHANNEL_INVOICE = 'invoice';

    protected $fillable = [
        'company_id',
        'catalog_id',
        'contact_id',
        'invoice_id',
        'flow_id',
        'order_number',
        'public_uuid',
        'status',
        'checkout_channel',
        'customer_name',
        'customer_phone',
        'delivery_address',
        'notes',
        'total_amount',
        'currency',
        'metadata',
    ];

    protected $casts = [
        'total_amount' => 'float',
        'metadata' => 'array',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope);

        static::creating(function (self $order): void {
            if (empty($order->public_uuid)) {
                $order->public_uuid = (string) Str::uuid();
            }
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function catalog(): BelongsTo
    {
        return $this->belongsTo(ListCatalog::class, 'catalog_id');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(CatalogOrderItem::class);
    }
}
