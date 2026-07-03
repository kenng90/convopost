<?php

namespace App\Services\VoiceBooking;

use App\Models\Company;
use Illuminate\Support\Facades\Log;
use Modules\Reminders\Models\EventRegistration;
use Modules\Reminders\Models\Reservation;
use Modules\Reminders\Services\EventRegistrationService;
use Modules\Reminders\Services\ReservationBookingService;
use Modules\Reminders\Support\BookingPaymentConfig;

class VoiceCallBookingService
{
    public function __construct(
        protected VoiceBookingSettingsService $settings,
        protected ReservationBookingService $reservationBookingService,
        protected EventRegistrationService $eventRegistrationService,
    ) {
    }

    /**
     * @param  array<string, mixed>  $structured
     * @return array<string, mixed>
     */
    public function mergeBookingResultsIntoStructured(array $structured): array
    {
        $bookings = $structured['voice_bookings'] ?? [];
        if (! is_array($bookings) || $bookings === []) {
            return $structured;
        }

        $structured['voice_bookings'] = $bookings;
        $bullets = $structured['summary_bullets'] ?? [];
        if (! is_array($bullets)) {
            $bullets = [];
        }

        foreach ($bookings as $booking) {
            if (! is_array($booking) || empty($booking['ok'])) {
                continue;
            }
            $summary = $booking['summary'] ?? $booking['message'] ?? null;
            if ($summary && ! in_array($summary, $bullets, true)) {
                $bullets[] = $summary;
            }
        }

        if ($bullets !== []) {
            $structured['summary_bullets'] = $bullets;
            $structured['summary'] = $bullets[0];
        }

        return $structured;
    }

    public function expirePendingHolds(): int
    {
        $count = 0;

        $reservations = Reservation::withoutGlobalScopes()
            ->where('payment_status', BookingPaymentConfig::STATUS_PENDING)
            ->whereNotNull('payment_hold_expires_at')
            ->where('payment_hold_expires_at', '<', now())
            ->whereNull('cancelled_at')
            ->where('status', 1)
            ->limit(200)
            ->get();

        foreach ($reservations as $reservation) {
            try {
                $this->reservationBookingService->cancel($reservation);
                $count++;
            } catch (\Throwable $th) {
                Log::warning('ExpireVoiceBookingHolds: reservation cancel failed', [
                    'reservation_id' => $reservation->id,
                    'error' => $th->getMessage(),
                ]);
            }
        }

        $registrations = EventRegistration::withoutGlobalScopes()
            ->where('payment_status', BookingPaymentConfig::STATUS_PENDING)
            ->whereNotNull('payment_hold_expires_at')
            ->where('payment_hold_expires_at', '<', now())
            ->where('status', EventRegistration::STATUS_CONFIRMED)
            ->whereNull('cancelled_at')
            ->limit(200)
            ->get();

        foreach ($registrations as $registration) {
            try {
                $this->eventRegistrationService->cancel($registration);
                $count++;
            } catch (\Throwable $th) {
                Log::warning('ExpireVoiceBookingHolds: registration cancel failed', [
                    'registration_id' => $registration->id,
                    'error' => $th->getMessage(),
                ]);
            }
        }

        return $count;
    }
}
