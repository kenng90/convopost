<?php

namespace Modules\Invoice\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoicePayment extends Model
{
    protected $table = 'invoice_payments';

    protected $fillable = [
        'invoice_id',
        'payment_method',
        'paid_via',
        'mpesa_checkout_request_id',
        'mpesa_merchant_request_id',
        'mpesa_receipt_number',
        'gateway_reference',
        'gateway_access_code',
        'authorization_url',
        'amount',
        'status',
        'result_description',
        'response_data',
        'initiated_at',
        'completed_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'response_data' => 'array',
        'initiated_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    /**
     * Relationship: Payment belongs to Invoice
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * Check if payment is successful
     */
    public function isSuccessful(): bool
    {
        return $this->status === 'success';
    }

    /**
     * Check if payment is pending
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Mark payment as pending
     */
    public function markAsPending(): void
    {
        $this->update([
            'status' => 'pending',
            'initiated_at' => now(),
        ]);
    }

    /**
     * Mark payment as successful
     */
    public function markAsSuccess($receiptNumber = null): void
    {
        $payload = [
            'status' => 'success',
            'completed_at' => now(),
        ];

        if ($receiptNumber) {
            if ($this->payment_method === 'paystack' || $this->paid_via === 'paystack') {
                $payload['gateway_reference'] = $this->gateway_reference ?: $receiptNumber;
                $payload['mpesa_receipt_number'] = $receiptNumber;
            } else {
                $payload['mpesa_receipt_number'] = $receiptNumber;
            }
        }

        $this->update($payload);

        // Update invoice status to paid if payment amount covers invoice amount
        if ($this->amount >= $this->invoice->amount) {
            $this->invoice->markAsPaid();
            app(\App\Services\Platform\InvoicePaidSyncService::class)->sync($this->invoice->fresh());
        } else {
            // Partial payment, update status to pending
            $this->invoice->update(['status' => 'pending']);
        }
    }

    /**
     * Mark payment as failed
     */
    public function markAsFailed($description = null): void
    {
        $this->update([
            'status' => 'failed',
            'result_description' => $description,
            'completed_at' => now(),
        ]);
    }

    /**
     * Store M-Pesa response data
     */
    public function storeResponseData(array $data): void
    {
        $this->update([
            'response_data' => $data,
        ]);
    }

    /**
     * Get M-Pesa request ID
     */
    public function getMpesaRequestId(): ?string
    {
        return $this->mpesa_checkout_request_id;
    }

    /**
     * Find payment by M-Pesa checkout request ID
     */
    public static function findByCheckoutRequestId(string $checkoutRequestId): ?self
    {
        return self::where('mpesa_checkout_request_id', $checkoutRequestId)->first();
    }
}
