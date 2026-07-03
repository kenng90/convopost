<?php

namespace App\Services\VoiceBooking;

use App\Models\Company;
use Illuminate\Support\Facades\Log;
use Modules\Reminders\Models\EventRegistration;
use Modules\Reminders\Models\Reservation;
use Modules\Reminders\Models\Source;
use Modules\Reminders\Services\AvailabilityService;
use Modules\Reminders\Services\BookingCatalogService;
use Modules\Reminders\Services\EventCatalogService;
use Modules\Reminders\Services\EventRegistrationService;
use Modules\Reminders\Services\ReservationBookingService;
use Modules\Reminders\Support\BookingPaymentConfig;
use Modules\Whatsappcall\Models\Call as CallModel;
use Modules\Wpbox\Models\Contact;

class VoiceBookingOrchestrator
{
    public function __construct(
        protected VoiceBookingSettingsService $settings,
        protected BookingCatalogService $catalogService,
        protected EventCatalogService $eventCatalogService,
        protected AvailabilityService $availabilityService,
        protected ReservationBookingService $reservationBookingService,
        protected EventRegistrationService $eventRegistrationService,
        protected VoiceBookingPaymentSender $paymentSender,
    ) {
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    public function createAppointmentBooking(CallModel $call, Company $company, Contact $contact, array $args): array
    {
        if (! $this->settings->appointmentsEnabled($company)) {
            return ['ok' => false, 'error' => 'Appointment booking is not enabled for voice AI.'];
        }

        $name = trim((string) ($args['customer_name'] ?? $contact->name ?? 'Guest'));
        $phone = trim((string) ($args['phone'] ?? $contact->phone ?? $call->wa_user_id ?? ''));
        $serviceName = trim((string) ($args['service_name'] ?? ''));
        $slotId = trim((string) ($args['slot_id'] ?? ''));

        if ($serviceName === '' || $slotId === '') {
            return ['ok' => false, 'error' => 'service_name and slot_id are required.'];
        }

        if ($phone === '') {
            return ['ok' => false, 'error' => 'Caller phone number is required to book.'];
        }

        $source = Source::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('is_bookable', true)
            ->where('name', $serviceName)
            ->first();

        if (! $source) {
            return ['ok' => false, 'error' => 'Service not found: '.$serviceName];
        }

        $paymentConfig = BookingPaymentConfig::fromSource($source);
        $holdMinutes = $this->settings->holdMinutes($company);

        $payload = [
            'phone' => $phone,
            'name' => $name,
            'source' => $serviceName,
            'slot_id' => $slotId,
            'duration_minutes' => isset($args['duration_minutes']) ? (int) $args['duration_minutes'] : null,
            'booking_source' => VoiceBookingSettingsService::SOURCE_VOICE_AI,
            'voice_call_id' => $call->id,
        ];

        try {
            if ($paymentConfig['payment_required']) {
                $reservation = $this->reservationBookingService->book($company, $payload);
                $reservation->update([
                    'payment_status' => BookingPaymentConfig::STATUS_PENDING,
                    'payment_amount' => $paymentConfig['payment_amount'],
                    'payment_total_amount' => $paymentConfig['payment_total_amount'],
                    'payment_currency' => $paymentConfig['payment_currency'],
                    'payment_hold_expires_at' => now()->addMinutes($holdMinutes),
                    'booking_source' => VoiceBookingSettingsService::SOURCE_VOICE_AI,
                    'voice_call_id' => $call->id,
                ]);

                $payment = $this->paymentSender->sendAppointmentPaymentLink(
                    $company,
                    $contact,
                    $payload,
                    $call->id,
                    $reservation
                );

                return [
                    'ok' => true,
                    'type' => 'appointment',
                    'payment_required' => true,
                    'reservation_id' => $reservation->id,
                    'payment_url' => $payment['payment_url'] ?? null,
                    'invoice_public_uuid' => $payment['invoice_public_uuid'] ?? null,
                    'whatsapp_sent' => $payment['whatsapp_sent'] ?? false,
                    'hold_expires_minutes' => $holdMinutes,
                    'message' => 'Appointment held. Payment link sent on WhatsApp.',
                    'summary' => $this->formatReservationSummary($reservation->fresh(['source'])),
                ];
            }

            $reservation = $this->reservationBookingService->book($company, $payload);
            $reservation->update([
                'payment_status' => BookingPaymentConfig::STATUS_NOT_REQUIRED,
                'booking_source' => VoiceBookingSettingsService::SOURCE_VOICE_AI,
                'voice_call_id' => $call->id,
            ]);

            return [
                'ok' => true,
                'type' => 'appointment',
                'payment_required' => false,
                'reservation_id' => $reservation->id,
                'message' => 'Appointment confirmed.',
                'summary' => $this->formatReservationSummary($reservation->fresh(['source'])),
            ];
        } catch (\Throwable $th) {
            Log::warning('VoiceBookingOrchestrator: appointment booking failed', [
                'call_id' => $call->id,
                'error' => $th->getMessage(),
            ]);

            return ['ok' => false, 'error' => $th->getMessage()];
        }
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    public function createEventRegistration(CallModel $call, Company $company, Contact $contact, array $args): array
    {
        if (! $this->settings->eventsEnabled($company)) {
            return ['ok' => false, 'error' => 'Event registration is not enabled for voice AI.'];
        }

        $name = trim((string) ($args['customer_name'] ?? $contact->name ?? 'Guest'));
        $phone = trim((string) ($args['phone'] ?? $contact->phone ?? $call->wa_user_id ?? ''));
        $occurrenceId = (int) ($args['occurrence_id'] ?? 0);
        $partySize = max(1, (int) ($args['party_size'] ?? 1));

        if ($occurrenceId < 1) {
            return ['ok' => false, 'error' => 'occurrence_id is required.'];
        }

        if ($phone === '') {
            return ['ok' => false, 'error' => 'Caller phone number is required to register.'];
        }

        $occurrence = $this->eventCatalogService->findRegisterableOccurrence($company, $occurrenceId);
        if (! $occurrence || ! $occurrence->event) {
            return ['ok' => false, 'error' => 'Event occurrence not available for registration.'];
        }

        $paymentConfig = BookingPaymentConfig::fromEvent($occurrence->event);
        $holdMinutes = $this->settings->holdMinutes($company);

        $payload = [
            'phone' => $phone,
            'name' => $name,
            'occurrence_id' => $occurrenceId,
            'party_size' => $partySize,
            'booking_source' => VoiceBookingSettingsService::SOURCE_VOICE_AI,
            'voice_call_id' => $call->id,
        ];

        try {
            if ($paymentConfig['payment_required']) {
                $registration = $this->eventRegistrationService->register($company, $payload);
                $registration->update([
                    'payment_status' => BookingPaymentConfig::STATUS_PENDING,
                    'payment_amount' => $paymentConfig['payment_amount'],
                    'payment_total_amount' => $paymentConfig['payment_total_amount'],
                    'payment_currency' => $paymentConfig['payment_currency'],
                    'payment_hold_expires_at' => now()->addMinutes($holdMinutes),
                    'booking_source' => VoiceBookingSettingsService::SOURCE_VOICE_AI,
                    'voice_call_id' => $call->id,
                ]);

                $payment = $this->paymentSender->sendEventPaymentLink(
                    $company,
                    $contact,
                    $payload,
                    $call->id,
                    $registration
                );

                return [
                    'ok' => true,
                    'type' => 'event',
                    'payment_required' => true,
                    'registration_id' => $registration->id,
                    'payment_url' => $payment['payment_url'] ?? null,
                    'invoice_public_uuid' => $payment['invoice_public_uuid'] ?? null,
                    'whatsapp_sent' => $payment['whatsapp_sent'] ?? false,
                    'hold_expires_minutes' => $holdMinutes,
                    'message' => 'Registration held. Payment link sent on WhatsApp.',
                    'summary' => $this->formatEventSummary($registration->fresh(['event', 'occurrence'])),
                ];
            }

            $registration = $this->eventRegistrationService->register($company, $payload);
            $registration->update([
                'payment_status' => BookingPaymentConfig::STATUS_NOT_REQUIRED,
                'booking_source' => VoiceBookingSettingsService::SOURCE_VOICE_AI,
                'voice_call_id' => $call->id,
            ]);

            return [
                'ok' => true,
                'type' => 'event',
                'payment_required' => false,
                'registration_id' => $registration->id,
                'message' => 'Event registration confirmed.',
                'summary' => $this->formatEventSummary($registration->fresh(['event', 'occurrence'])),
            ];
        } catch (\Throwable $th) {
            Log::warning('VoiceBookingOrchestrator: event registration failed', [
                'call_id' => $call->id,
                'error' => $th->getMessage(),
            ]);

            return ['ok' => false, 'error' => $th->getMessage()];
        }
    }

    private function formatReservationSummary(Reservation $reservation): string
    {
        $start = $reservation->start_date?->timezone($reservation->source?->timezone ?: 'UTC');

        return trim(sprintf(
            '%s on %s',
            $reservation->source?->name ?? 'Appointment',
            $start ? $start->format('D j M Y, g:i A') : 'scheduled time'
        ));
    }

    private function formatEventSummary(EventRegistration $registration): string
    {
        $event = $registration->event;
        $occurrence = $registration->occurrence;

        return trim(sprintf(
            '%s — %s (%d guest%s)',
            $event?->title ?? 'Event',
            $occurrence?->starts_at?->format('D j M Y, g:i A') ?? '',
            $registration->party_size,
            $registration->party_size === 1 ? '' : 's'
        ));
    }
}
