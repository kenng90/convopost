<?php

namespace Modules\Reminders\Services;

use App\Models\Company;
use App\Services\MpesaService;
use Illuminate\Support\Facades\Log;
use Modules\Invoice\Models\Invoice;
use Modules\Invoice\Models\InvoicePayment;
use Modules\Reminders\Models\EventRegistration;
use Modules\Reminders\Models\Reservation;
use Modules\Reminders\Models\Source;
use Modules\Reminders\Support\BookingPaymentConfig;

class BookingPaymentService
{
    public const BOOKING_TYPE_APPOINTMENT = 'appointment';

    public const BOOKING_TYPE_EVENT = 'event';

    public function __construct(
        private readonly ReservationBookingService $reservationBookingService,
        private readonly EventRegistrationService $eventRegistrationService,
        private readonly EventCatalogService $eventCatalogService
    ) {
    }

    /**
     * @param  array{phone: string, name: string, source: string, slot_id?: string, start_date?: string, end_date?: string, duration_minutes?: int, staff_user_id?: int, external_id?: string|null}  $payload
     * @return array{payment: InvoicePayment, invoice: Invoice, checkout_request_id: string|null, requires_action: bool}
     */
    public function initiateAppointmentPayment(Company $company, array $payload): array
    {
        session(['company_id' => $company->id]);

        $source = Source::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('is_bookable', true)
            ->where('name', $payload['source'])
            ->firstOrFail();

        $paymentConfig = BookingPaymentConfig::fromSource($source);

        if (! $paymentConfig['payment_required']) {
            $reservation = $this->reservationBookingService->book($company, $payload);
            $reservation->update([
                'payment_status' => BookingPaymentConfig::STATUS_NOT_REQUIRED,
            ]);

            return [
                'payment' => null,
                'invoice' => null,
                'checkout_request_id' => null,
                'requires_action' => false,
                'reservation' => $reservation->fresh(['contact', 'source', 'appointmentStaffMember']),
            ];
        }

        return $this->initiateStk(
            company: $company,
            bookingType: self::BOOKING_TYPE_APPOINTMENT,
            bookingPayload: $payload,
            amount: (float) $paymentConfig['payment_amount'],
            currency: $paymentConfig['payment_currency'],
            customerName: $payload['name'],
            customerPhone: $payload['phone'],
            transactionDesc: substr($source->name, 0, 13),
            accountReference: 'BK-APT',
            flowContext: $payload['flow_context'] ?? null,
            paymentTotalAmount: $paymentConfig['payment_total_amount'],
            paymentUpfrontPercent: $paymentConfig['payment_upfront_percent'],
        );
    }

    /**
     * @param  array{phone: string, name: string, source: string, slot_id: string, duration_minutes?: int, flow_context?: array<string, int|string>}  $payload
     * @return array<string, mixed>
     */
    public function initiateAppointmentPaymentForFlow(Company $company, array $payload): array
    {
        return $this->initiateAppointmentPayment($company, $payload);
    }

    /**
     * @param  array{occurrence_id: int, phone: string, name: string, party_size?: int, external_id?: string|null}  $payload
     * @return array<string, mixed>
     */
    public function initiateEventPayment(Company $company, array $payload): array
    {
        session(['company_id' => $company->id]);

        if (! $this->eventCatalogService->eventsEnabled($company)) {
            throw new \RuntimeException('Events booking is disabled for this company.');
        }

        $occurrence = $this->eventCatalogService->findRegisterableOccurrence($company, (int) $payload['occurrence_id']);

        if (! $occurrence || ! $occurrence->isRegisterable()) {
            throw new \RuntimeException('This event session is no longer open for registration.');
        }

        $event = $occurrence->event;
        $paymentConfig = BookingPaymentConfig::fromEvent($event);

        if (! $paymentConfig['payment_required']) {
            $registration = $this->eventRegistrationService->register($company, $payload);
            $registration->update([
                'payment_status' => BookingPaymentConfig::STATUS_NOT_REQUIRED,
            ]);

            return [
                'payment' => null,
                'invoice' => null,
                'checkout_request_id' => null,
                'requires_action' => false,
                'registration' => $registration->fresh(['event', 'occurrence', 'contact']),
            ];
        }

        return $this->initiateStk(
            company: $company,
            bookingType: self::BOOKING_TYPE_EVENT,
            bookingPayload: $payload,
            amount: (float) $paymentConfig['payment_amount'],
            currency: $paymentConfig['payment_currency'],
            customerName: $payload['name'],
            customerPhone: $payload['phone'],
            transactionDesc: substr($event->title, 0, 13),
            accountReference: 'BK-EVT',
            flowContext: $payload['flow_context'] ?? null,
            paymentTotalAmount: $paymentConfig['payment_total_amount'],
            paymentUpfrontPercent: $paymentConfig['payment_upfront_percent'],
        );
    }

    /**
     * @param  array{occurrence_id: int, phone: string, name: string, party_size?: int, flow_context?: array<string, int|string>}  $payload
     * @return array<string, mixed>
     */
    public function initiateEventPaymentForFlow(Company $company, array $payload): array
    {
        return $this->initiateEventPayment($company, $payload);
    }

    /**
     * @return array<string, mixed>
     */
    public function paymentStatus(Company $company, string $invoicePublicUuid): array
    {
        $invoice = Invoice::query()
            ->where('company_id', $company->id)
            ->where('public_uuid', $invoicePublicUuid)
            ->firstOrFail();

        abort_unless($invoice->isBookingPayment(), 404);

        $payment = $invoice->payments()->latest('id')->first();
        $notes = $invoice->bookingNotes();

        $response = [
            'status' => $payment?->status ?? 'pending',
            'invoice_public_uuid' => $invoice->public_uuid,
            'payment_id' => $payment?->id,
            'amount' => (float) $invoice->amount,
            'currency' => $invoice->currency,
            'booking_type' => $notes['booking_type'] ?? null,
            'fulfilled' => ! empty($notes['fulfilled_at']),
            'reservation_id' => $notes['reservation_id'] ?? null,
            'event_registration_id' => $notes['event_registration_id'] ?? null,
        ];

        if (! empty($notes['reservation_id'])) {
            $reservation = Reservation::withoutGlobalScopes()
                ->where('company_id', $company->id)
                ->find($notes['reservation_id']);

            if ($reservation) {
                $response['reservation'] = $reservation->load(['contact', 'source', 'appointmentStaffMember']);
            }
        }

        if (! empty($notes['event_registration_id'])) {
            $registration = EventRegistration::withoutGlobalScopes()
                ->where('company_id', $company->id)
                ->find($notes['event_registration_id']);

            if ($registration) {
                $response['registration'] = $registration->load(['event', 'occurrence', 'contact']);
            }
        }

        return $response;
    }

    public function fulfillSuccessfulPayment(InvoicePayment $payment): void
    {
        $payment->loadMissing('invoice');
        $invoice = $payment->invoice;

        if (! $invoice || ! $invoice->isBookingPayment()) {
            return;
        }

        $notes = $invoice->bookingNotes();

        if (! empty($notes['fulfilled_at'])) {
            return;
        }

        $company = Company::find($invoice->company_id);

        if (! $company) {
            Log::error('Booking payment fulfillment: company not found', ['invoice_id' => $invoice->id]);

            return;
        }

        session(['company_id' => $company->id]);

        $payload = $notes['booking_payload'] ?? [];
        $bookingType = $notes['booking_type'] ?? null;

        try {
            if ($bookingType === self::BOOKING_TYPE_APPOINTMENT) {
                $reservation = $this->reservationBookingService->book($company, $payload);
                $reservation->update([
                    'payment_status' => BookingPaymentConfig::STATUS_PAID,
                    'payment_amount' => $payment->amount,
                    'payment_total_amount' => $notes['payment_total_amount'] ?? $payment->amount,
                    'payment_currency' => $invoice->currency,
                    'invoice_payment_id' => $payment->id,
                ]);

                $invoice->update([
                    'notes' => array_merge($notes, [
                        'fulfilled_at' => now()->toIso8601String(),
                        'reservation_id' => $reservation->id,
                    ]),
                ]);

                Log::info('Booking payment fulfilled: appointment', [
                    'invoice_id' => $invoice->id,
                    'payment_id' => $payment->id,
                    'reservation_id' => $reservation->id,
                ]);

                return;
            }

            if ($bookingType === self::BOOKING_TYPE_EVENT) {
                $registration = $this->eventRegistrationService->register($company, $payload);
                $registration->update([
                    'payment_status' => BookingPaymentConfig::STATUS_PAID,
                    'payment_amount' => $payment->amount,
                    'payment_total_amount' => $notes['payment_total_amount'] ?? $payment->amount,
                    'payment_currency' => $invoice->currency,
                    'invoice_payment_id' => $payment->id,
                ]);

                $invoice->update([
                    'notes' => array_merge($notes, [
                        'fulfilled_at' => now()->toIso8601String(),
                        'event_registration_id' => $registration->id,
                    ]),
                ]);

                Log::info('Booking payment fulfilled: event', [
                    'invoice_id' => $invoice->id,
                    'payment_id' => $payment->id,
                    'event_registration_id' => $registration->id,
                ]);
            }
        } catch (\Throwable $exception) {
            Log::error('Booking payment fulfillment failed', [
                'invoice_id' => $invoice->id,
                'payment_id' => $payment->id,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    public function mpesaConfigured(Company $company): bool
    {
        return (new MpesaService($company))->isConfigured();
    }

    /**
     * @param  array<string, mixed>  $bookingPayload
     * @return array<string, mixed>
     */
    private function initiateStk(
        Company $company,
        string $bookingType,
        array $bookingPayload,
        float $amount,
        string $currency,
        string $customerName,
        string $customerPhone,
        string $transactionDesc,
        string $accountReference,
        ?array $flowContext = null,
        ?float $paymentTotalAmount = null,
        ?int $paymentUpfrontPercent = null
    ): array {
        $storedPayload = $bookingPayload;
        unset($storedPayload['flow_context']);

        $records = Invoice::createForBookingPayment(
            company: $company,
            customerName: $customerName,
            customerPhone: $customerPhone,
            bookingType: $bookingType,
            bookingPayload: $storedPayload,
            amount: $amount,
            currency: $currency,
            transactionDesc: $transactionDesc,
            accountReference: $accountReference,
            flowContext: $flowContext,
            paymentTotalAmount: $paymentTotalAmount,
            paymentUpfrontPercent: $paymentUpfrontPercent,
        );
        $mpesaService = new MpesaService($company);

        if (! $mpesaService->isConfigured()) {
            throw new \RuntimeException('M-Pesa is not configured for this business. Please contact support.');
        }

        /** @var Invoice $invoice */
        $invoice = $records['invoice'];
        /** @var InvoicePayment $payment */
        $payment = $records['payment'];

        $result = $mpesaService->initiateStk(
            phone: $customerPhone,
            amount: $amount,
            accountReference: $accountReference.'-'.$invoice->id,
            transactionDesc: $transactionDesc,
            callbackUrl: route('reminders.booking.mpesa.callback', [], true),
        );

        if (! $result['success']) {
            $payment->markAsFailed($result['error'] ?? 'STK push failed');
            $invoice->cancel();

            throw new \RuntimeException($result['error'] ?? 'Could not start M-Pesa payment.');
        }

        $payment->update([
            'mpesa_checkout_request_id' => $result['checkout_request_id'],
            'mpesa_merchant_request_id' => $result['merchant_request_id'] ?? '',
            'initiated_at' => now(),
        ]);

        return [
            'payment' => $payment->fresh(),
            'invoice' => $invoice->fresh(),
            'checkout_request_id' => $result['checkout_request_id'],
            'requires_action' => true,
            'message' => __('Check your phone to complete the M-Pesa payment.'),
        ];
    }
}
