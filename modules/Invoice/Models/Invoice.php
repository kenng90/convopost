<?php

namespace Modules\Invoice\Models;

use App\Models\Company;
use App\Models\ListCatalog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Invoice extends Model
{
    use SoftDeletes;

    protected $table = 'invoices';

    protected $fillable = [
        'company_id',
        'catalog_id',
        'public_uuid',
        'invoice_number',
        'customer_name',
        'customer_phone',
        'customer_email',
        'amount',
        'currency',
        'status',
        'description',
        'items',
        'sent_at',
        'paid_at',
        'cancelled_at',
        'notes',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'items' => 'array',
        'notes' => 'array',
        'sent_at' => 'datetime',
        'paid_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    /**
     * Boot method - generate UUID on creation
     */
    protected static function booted(): void
    {
        static::creating(function ($model) {
            if (empty($model->public_uuid)) {
                $model->public_uuid = Str::uuid();
            }
        });
    }

    /**
     * Get the route key for model binding (use UUID for public routes)
     */
    public function getRouteKeyName(): string
    {
        // Check if we're in an API context - use UUID for public routes
        return 'public_uuid';
    }

    /**
     * Resolve by ID for internal use
     */
    public static function findByIdOrFail($id)
    {
        return static::where('id', $id)->firstOrFail();
    }

    /**
     * Relationship: Invoice belongs to Company
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Relationship: Invoice belongs to Catalog
     */
    public function catalog(): BelongsTo
    {
        return $this->belongsTo(ListCatalog::class, 'catalog_id');
    }

    /**
     * Relationship: Invoice has many Payments
     */
    public function payments(): HasMany
    {
        return $this->hasMany(InvoicePayment::class);
    }

    /**
     * Get the latest payment for this invoice
     */
    public function latestPayment()
    {
        return $this->hasOne(InvoicePayment::class)->latest();
    }

    /**
     * Check if invoice is fully paid
     */
    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }

    /**
     * Check if invoice has pending payment
     */
    public function hasPendingPayment(): bool
    {
        return $this->payments()
            ->where('status', 'pending')
            ->exists();
    }

    /**
     * Get total amount paid against this invoice
     */
    public function getTotalPaidAmount(): float
    {
        return (float) $this->payments()
            ->where('status', 'success')
            ->sum('amount');
    }

    /**
     * Get remaining amount to be paid
     */
    public function getRemainingAmount(): float
    {
        return (float) $this->amount - $this->getTotalPaidAmount();
    }

    /**
     * Mark invoice as sent
     */
    public function markAsSent(): void
    {
        $this->update([
            'status' => 'sent',
            'sent_at' => now(),
        ]);
    }

    /**
     * Mark invoice as paid
     */
    public function markAsPaid(): void
    {
        $this->update([
            'status' => 'paid',
            'paid_at' => now(),
        ]);
    }

    /**
     * Cancel invoice
     */
    public function cancel(): void
    {
        $this->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
        ]);
    }

    /**
     * Generate a unique invoice number
     */
    public static function generateInvoiceNumber(Company $company): string
    {
        $prefix = strtoupper(substr($company->name, 0, 3));
        $count = self::where('company_id', $company->id)->count() + 1;
        $date = now()->format('Ymd');
        return "{$prefix}-{$date}-{$count}";
    }

    /**
     * Get invoice as formatted array
     */
    public function toInvoiceArray(): array
    {
        return [
            'id' => $this->id,
            'public_uuid' => $this->public_uuid,
            'invoice_number' => $this->invoice_number,
            'customer_name' => $this->customer_name,
            'customer_phone' => $this->customer_phone,
            'customer_email' => $this->customer_email,
            'amount' => (float) $this->amount,
            'currency' => $this->currency,
            'status' => $this->status,
            'items' => $this->items,
            'total_paid' => $this->getTotalPaidAmount(),
            'remaining' => $this->getRemainingAmount(),
            'created_at' => $this->created_at->toDateTimeString(),
            'paid_at' => $this->paid_at?->toDateTimeString(),
        ];
    }
}
