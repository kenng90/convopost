<?php

namespace App\Services\VoiceBooking;

use App\Models\Company;

class VoiceBookingSettingsService
{
    public const SOURCE_VOICE_AI = 'voice_ai';

    public function isEnabled(Company $company): bool
    {
        return filter_var($company->getConfig('whatsapp_ai_booking_enabled', false), FILTER_VALIDATE_BOOLEAN);
    }

    public function appointmentsEnabled(Company $company): bool
    {
        if (! $this->isEnabled($company)) {
            return false;
        }

        return filter_var($company->getConfig('whatsapp_ai_booking_appointments', true), FILTER_VALIDATE_BOOLEAN);
    }

    public function eventsEnabled(Company $company): bool
    {
        if (! $this->isEnabled($company)) {
            return false;
        }

        return filter_var($company->getConfig('whatsapp_ai_booking_events', true), FILTER_VALIDATE_BOOLEAN);
    }

    public function sendPaymentLinkAfterBooking(Company $company): bool
    {
        return filter_var($company->getConfig('whatsapp_ai_booking_send_payment_link', true), FILTER_VALIDATE_BOOLEAN);
    }

    public function holdMinutes(Company $company): int
    {
        $minutes = (int) $company->getConfig('whatsapp_ai_booking_hold_minutes', 15);

        return max(5, min(60, $minutes));
    }

    /**
     * @return array{enabled: bool, appointments: bool, events: bool, send_payment_link: bool, hold_minutes: int}
     */
    public function snapshot(Company $company): array
    {
        return [
            'enabled' => $this->isEnabled($company),
            'appointments' => $this->appointmentsEnabled($company),
            'events' => $this->eventsEnabled($company),
            'send_payment_link' => $this->sendPaymentLinkAfterBooking($company),
            'hold_minutes' => $this->holdMinutes($company),
        ];
    }
}
