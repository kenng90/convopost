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

    public const SOURCE_FLOW = 'flow';

    public const SOURCE_CATALOG = 'catalog';

    public const SOURCE_BOOKING = 'booking';

    public const SOURCE_INVOICE = 'invoice';

    protected $table = 'invoices';

    protected $fillable = [
        'company_id',
        'catalog_id',
        'public_uuid',
        'invoice_number',
        'customer_name',
        'customer_phone',
        'customer_email',
        'delivery_address',
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

        app(\App\Services\Api\PublicWebhookDispatcher::class)->dispatch($this->company_id, 'payment.completed', [
            'invoice_id' => $this->id,
            'public_uuid' => $this->public_uuid,
            'amount' => (float) $this->amount,
            'currency' => $this->currency,
            'customer_phone' => $this->customer_phone,
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
     * Determine how this invoice was created for reporting.
     */
    public function getPaymentSource(): string
    {
        $notes = is_array($this->notes) ? $this->notes : [];

        if (($notes['source'] ?? null) === self::SOURCE_FLOW) {
            return self::SOURCE_FLOW;
        }

        if (($notes['source'] ?? null) === self::SOURCE_BOOKING) {
            return self::SOURCE_BOOKING;
        }

        if ($this->catalog_id) {
            return self::SOURCE_CATALOG;
        }

        return self::SOURCE_INVOICE;
    }

    public function getPaymentSourceLabel(): string
    {
        return match ($this->getPaymentSource()) {
            self::SOURCE_FLOW => 'Flow STK Push',
            self::SOURCE_CATALOG => 'Catalog',
            self::SOURCE_BOOKING => 'Booking',
            default => 'Invoice',
        };
    }

    public function isFlowPayment(): bool
    {
        return $this->getPaymentSource() === self::SOURCE_FLOW;
    }

    /**
     * Create a lightweight invoice and pending payment for a flow STK push.
     *
     * @return array{invoice: self, payment: InvoicePayment}
     */
    public static function createForFlowStkPush(
        Company $company,
        string $customerName,
        string $customerPhone,
        int $flowId,
        string $nodeId,
        int $contactId,
        float $amount,
        string $transactionDesc,
        string $accountReference
    ): array {
        $invoice = self::create([
            'company_id' => $company->id,
            'invoice_number' => self::generateInvoiceNumber($company),
            'customer_name' => $customerName ?: 'Flow Contact',
            'customer_phone' => $customerPhone,
            'amount' => $amount,
            'currency' => 'KES',
            'status' => 'sent',
            'description' => $transactionDesc,
            'items' => [
                [
                    'title' => $transactionDesc,
                    'quantity' => 1,
                    'price' => $amount,
                    'total' => $amount,
                ],
            ],
            'notes' => [
                'source' => self::SOURCE_FLOW,
                'flow_id' => $flowId,
                'contact_id' => $contactId,
                'node_id' => $nodeId,
                'account_reference' => $accountReference,
            ],
            'sent_at' => now(),
        ]);

        $payment = InvoicePayment::create([
            'invoice_id' => $invoice->id,
            'payment_method' => 'mpesa',
            'amount' => $amount,
            'status' => 'pending',
        ]);

        return [
            'invoice' => $invoice,
            'payment' => $payment,
        ];
    }

    /**
     * Create a lightweight invoice and pending payment for a booking STK push.
     *
     * @param  array<string, mixed>  $bookingPayload
     * @return array{invoice: self, payment: InvoicePayment}
     */
    public static function createForBookingPayment(
        Company $company,
        string $customerName,
        string $customerPhone,
        string $bookingType,
        array $bookingPayload,
        float $amount,
        string $currency,
        string $transactionDesc,
        string $accountReference,
        ?array $flowContext = null,
        ?float $paymentTotalAmount = null,
        ?int $paymentUpfrontPercent = null
    ): array {
        $invoice = self::create([
            'company_id' => $company->id,
            'invoice_number' => self::generateInvoiceNumber($company),
            'customer_name' => $customerName ?: 'Booking Contact',
            'customer_phone' => $customerPhone,
            'amount' => $amount,
            'currency' => strtoupper($currency),
            'status' => 'sent',
            'description' => $transactionDesc,
            'items' => [
                [
                    'title' => $transactionDesc,
                    'quantity' => 1,
                    'price' => $amount,
                    'total' => $amount,
                ],
            ],
            'notes' => [
                'source' => self::SOURCE_BOOKING,
                'booking_type' => $bookingType,
                'booking_payload' => $bookingPayload,
                'account_reference' => $accountReference,
                'fulfilled_at' => null,
                'reservation_id' => null,
                'event_registration_id' => null,
                'flow_id' => $flowContext['flow_id'] ?? null,
                'flow_node_id' => $flowContext['flow_node_id'] ?? null,
                'contact_id' => $flowContext['contact_id'] ?? null,
                'payment_total_amount' => $paymentTotalAmount ?? $amount,
                'payment_upfront_percent' => $paymentUpfrontPercent ?? 100,
                'payment_upfront_amount' => $amount,
            ],
            'sent_at' => now(),
        ]);

        $payment = InvoicePayment::create([
            'invoice_id' => $invoice->id,
            'payment_method' => 'mpesa',
            'amount' => $amount,
            'status' => 'pending',
        ]);

        return [
            'invoice' => $invoice,
            'payment' => $payment,
        ];
    }

    public function isBookingPayment(): bool
    {
        return $this->getPaymentSource() === self::SOURCE_BOOKING;
    }

    /**
     * @return array<string, mixed>
     */
    public function bookingNotes(): array
    {
        return is_array($this->notes) ? $this->notes : [];
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
            'delivery_address' => $this->delivery_address,
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
