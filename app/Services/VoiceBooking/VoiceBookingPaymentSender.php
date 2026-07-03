<?php

namespace App\Services\VoiceBooking;

use App\Models\Company;
use App\Services\InvoiceWhatsAppService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Modules\Reminders\Models\EventOccurrence;
use Modules\Reminders\Models\EventRegistration;
use Modules\Reminders\Models\Reservation;
use Modules\Reminders\Models\Source;
use Modules\Reminders\Services\BookingPaymentService;
use Modules\Reminders\Support\BookingPaymentConfig;
use Modules\Wpbox\Models\Contact;

class VoiceBookingPaymentSender
{
    public function __construct(
        protected BookingPaymentService $bookingPaymentService,
        protected VoiceBookingSettingsService $settings,
    ) {
    }

    /**
     * @param  array<string, mixed>  $bookingPayload
     * @return array{ok: bool, payment_url?: string, invoice_public_uuid?: string, reservation_id?: int, registration_id?: int, message?: string}
     */
    public function sendAppointmentPaymentLink(
        Company $company,
        Contact $contact,
        array $bookingPayload,
        int $voiceCallId,
        ?Reservation $pendingReservation = null,
    ): array {
        $result = $this->bookingPaymentService->createVoiceBookingPaymentLink(
            $company,
            BookingPaymentService::BOOKING_TYPE_APPOINTMENT,
            $bookingPayload,
            $contact,
            $voiceCallId,
            $pendingReservation?->id
        );

        if (! $this->settings->sendPaymentLinkAfterBooking($company)) {
            return array_merge($result, ['whatsapp_sent' => false]);
        }

        $sent = $this->sendInvoiceWhatsApp($company, $contact, $result['invoice'] ?? null, $result['payment_url'] ?? null);

        return array_merge($result, ['whatsapp_sent' => $sent]);
    }

    /**
     * @param  array<string, mixed>  $bookingPayload
     * @return array<string, mixed>
     */
    public function sendEventPaymentLink(
        Company $company,
        Contact $contact,
        array $bookingPayload,
        int $voiceCallId,
        ?EventRegistration $pendingRegistration = null,
    ): array {
        $result = $this->bookingPaymentService->createVoiceBookingPaymentLink(
            $company,
            BookingPaymentService::BOOKING_TYPE_EVENT,
            $bookingPayload,
            $contact,
            $voiceCallId,
            null,
            $pendingRegistration?->id
        );

        if (! $this->settings->sendPaymentLinkAfterBooking($company)) {
            return array_merge($result, ['whatsapp_sent' => false]);
        }

        $sent = $this->sendInvoiceWhatsApp($company, $contact, $result['invoice'] ?? null, $result['payment_url'] ?? null);

        return array_merge($result, ['whatsapp_sent' => $sent]);
    }

    private function sendInvoiceWhatsApp(Company $company, Contact $contact, $invoice, ?string $paymentUrl): bool
    {
        if (! $invoice || ! $paymentUrl) {
            return false;
        }

        try {
            return app(InvoiceWhatsAppService::class, ['company' => $company])->sendInvoice($invoice, $contact);
        } catch (\Throwable $th) {
            Log::warning('VoiceBookingPaymentSender: WhatsApp send failed', [
                'company_id' => $company->id,
                'invoice_id' => $invoice->id,
                'error' => $th->getMessage(),
            ]);

            return false;
        }
    }
}
