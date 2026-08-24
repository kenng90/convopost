<?php

namespace App\Services\Collections;

use App\Enums\CollectionStatus;
use App\Services\Api\PublicWebhookDispatcher;
use Illuminate\Support\Facades\Log;
use Modules\Flowmaker\Jobs\ResumeFlowFromMpesa;
use Modules\Flowmaker\Jobs\ResumeFlowFromPayment;
use Modules\Invoice\Models\Invoice;
use Modules\Invoice\Models\InvoicePayment;
use Modules\Reminders\Models\EventRegistration;
use Modules\Reminders\Models\Reservation;
use Modules\Reminders\Services\BookingPaymentService;
use Modules\Reminders\Support\BookingPaymentConfig;

class CollectionFulfillment
{
    public function onPaid(Invoice $invoice, InvoicePayment $payment): void
    {
        $invoice->refresh();

        if ($invoice->isBookingPayment() && class_exists(BookingPaymentService::class)) {
            try {
                app(BookingPaymentService::class)->fulfillSuccessfulPayment($payment);
            } catch (\Throwable $e) {
                Log::warning('Collection fulfillment: booking paid handler failed', [
                    'invoice_id' => $invoice->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if ($payment->payment_method === 'paystack' || $payment->paid_via === 'paystack') {
            $this->resumeFlows($invoice, 'success');
        }

        if (! in_array($invoice->collection_status, [
            CollectionStatus::Fulfilled->value,
            CollectionStatus::Paid->value,
        ], true)) {
            $invoice->forceFill(['collection_status' => CollectionStatus::Paid->value])->save();
        }
    }

    public function onTerminalFailure(Invoice $invoice, InvoicePayment $payment, string $reason = ''): void
    {
        $invoice->refresh();

        if ($invoice->status !== 'cancelled' && $invoice->status !== 'paid') {
            $invoice->cancel();
        }

        $invoice->forceFill([
            'collection_status' => CollectionStatus::Cancelled->value,
            'next_chase_at' => null,
        ])->save();

        $this->releaseBookingHold($invoice);
        $this->resumeFlows($invoice, 'failed');

        app(PublicWebhookDispatcher::class)->dispatch($invoice->company_id, 'payment.failed', [
            'invoice_id' => $invoice->id,
            'public_uuid' => $invoice->public_uuid,
            'amount' => (float) $invoice->amount,
            'currency' => $invoice->currency,
            'customer_phone' => $invoice->customer_phone,
            'payment_id' => $payment->id,
            'reason' => $reason !== '' ? $reason : ($payment->result_description ?? 'Payment failed'),
        ]);
    }

    public function releaseBookingHold(Invoice $invoice): void
    {
        if (! $invoice->isBookingPayment()) {
            return;
        }

        $notes = $invoice->bookingNotes();

        $reservationId = (int) ($notes['pending_reservation_id'] ?? $notes['reservation_id'] ?? 0);
        if ($reservationId > 0 && class_exists(Reservation::class)) {
            $reservation = Reservation::withoutGlobalScopes()->find($reservationId);
            if ($reservation && (int) $reservation->company_id === (int) $invoice->company_id) {
                $reservation->update([
                    'cancelled_at' => now(),
                    'status' => 2,
                    'payment_status' => BookingPaymentConfig::STATUS_FAILED,
                    'payment_hold_expires_at' => null,
                ]);
            }
        }

        $registrationId = (int) ($notes['pending_registration_id'] ?? $notes['event_registration_id'] ?? 0);
        if ($registrationId > 0 && class_exists(EventRegistration::class)) {
            $registration = EventRegistration::withoutGlobalScopes()->find($registrationId);
            if ($registration && (int) $registration->company_id === (int) $invoice->company_id) {
                $registration->update([
                    'payment_status' => BookingPaymentConfig::STATUS_FAILED,
                    'payment_hold_expires_at' => null,
                ]);
            }
        }
    }

    public function resumeFlows(Invoice $invoice, string $status): void
    {
        $notes = is_array($invoice->notes) ? $invoice->notes : [];
        $flowId = (int) ($notes['flow_id'] ?? 0);
        $contactId = (int) ($notes['contact_id'] ?? 0);

        if ($flowId <= 0 || $contactId <= 0) {
            return;
        }

        if (class_exists(ResumeFlowFromPayment::class)) {
            ResumeFlowFromPayment::dispatch($flowId, $contactId, $status)->onQueue('flows');
        }

        if ($status === 'failed' && class_exists(ResumeFlowFromMpesa::class)) {
            ResumeFlowFromMpesa::dispatch($flowId, $contactId)->onQueue('flows');
        }
    }
}
