<?php

namespace App\Services\Flowmaker;

use App\Models\Company;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\Reminders\Models\EventRegistration;
use Modules\Reminders\Models\Reservation;

class BookingWebhookService
{
    /**
     * @param  array<string, mixed>  $settings
     */
    public function dispatchAppointmentConfirmed(
        Company $company,
        Reservation $reservation,
        int $flowId,
        string $nodeId,
        array $settings = []
    ): void {
        $url = trim((string) ($settings['booking_webhook_url'] ?? $company->getConfig('BOOKING_WEBHOOK_URL', '')));

        if ($url === '') {
            return;
        }

        $reservation->loadMissing(['source', 'contact']);

        $payload = [
            'event' => 'booking.appointment.confirmed',
            'flow_id' => $flowId,
            'flow_node_id' => $nodeId,
            'company_id' => $company->id,
            'reservation' => [
                'id' => $reservation->id,
                'reference' => (string) $reservation->id,
                'service' => $reservation->source?->name,
                'start_date' => $reservation->start_date?->toIso8601String(),
                'end_date' => $reservation->end_date?->toIso8601String(),
                'duration_minutes' => $reservation->duration_minutes,
                'status' => $reservation->displayStatus(),
            ],
            'contact' => [
                'id' => $reservation->contact_id,
                'name' => $reservation->contact?->name,
                'phone' => $reservation->contact?->phone,
            ],
        ];

        $this->post($url, $payload);
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    public function dispatchEventRegistrationConfirmed(
        Company $company,
        EventRegistration $registration,
        int $flowId,
        string $nodeId,
        array $settings = []
    ): void {
        $url = trim((string) ($settings['booking_webhook_url'] ?? $company->getConfig('BOOKING_WEBHOOK_URL', '')));

        if ($url === '') {
            return;
        }

        $registration->loadMissing(['event', 'occurrence', 'contact']);

        $payload = [
            'event' => 'booking.event.registered',
            'flow_id' => $flowId,
            'flow_node_id' => $nodeId,
            'company_id' => $company->id,
            'registration' => [
                'id' => $registration->id,
                'reference' => (string) $registration->id,
                'event_title' => $registration->event?->title,
                'starts_at' => $registration->occurrence?->starts_at?->toIso8601String(),
                'party_size' => $registration->party_size,
                'status' => $registration->displayStatus(),
            ],
            'contact' => [
                'id' => $registration->contact_id,
                'name' => $registration->contact?->name,
                'phone' => $registration->contact?->phone,
            ],
        ];

        $this->post($url, $payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function post(string $url, array $payload): void
    {
        try {
            Http::timeout(10)->post($url, $payload);
        } catch (\Throwable $exception) {
            Log::warning('Booking webhook delivery failed', [
                'url' => $url,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
