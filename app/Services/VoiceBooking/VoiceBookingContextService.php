<?php

namespace App\Services\VoiceBooking;

use App\Models\Company;
use Modules\Reminders\Services\BookingCatalogService;
use Modules\Reminders\Services\EventCatalogService;
use Modules\Reminders\Support\BookingPaymentConfig;

class VoiceBookingContextService
{
    public function __construct(
        protected VoiceBookingSettingsService $settings,
        protected BookingCatalogService $bookingCatalog,
        protected EventCatalogService $eventCatalog,
    ) {
    }

    /**
     * @return array{booking_context: string, voice_booking: array<string, mixed>}
     */
    public function buildForCompany(Company $company): array
    {
        $snapshot = $this->settings->snapshot($company);

        if (! $snapshot['enabled']) {
            return [
                'booking_context' => '',
                'voice_booking' => $snapshot,
            ];
        }

        $lines = [
            '## Appointments & events (voice booking)',
            'You can help callers book appointments and register for events using the booking tools.',
            'Always confirm service name, date, time, and caller name before creating a booking.',
            'If payment is required, tell the caller you will send a WhatsApp payment link after confirming details.',
            'Never invent availability — use tools to check dates and slots.',
        ];

        if ($snapshot['appointments']) {
            $services = $this->bookingCatalog->bookableServicesForCompany($company);
            if ($services === []) {
                $lines[] = 'No bookable appointment services are configured.';
            } else {
                $lines[] = 'Bookable appointment services:';
                foreach (array_slice($services, 0, 12) as $service) {
                    $payment = ($service['payment_required'] ?? false)
                        ? ' (paid: '.($service['currency'] ?? 'KES').' '.number_format((float) ($service['price'] ?? 0), 2).')'
                        : ' (free)';
                    $lines[] = '- '.$service['name'].' — '.$service['default_duration_minutes'].' min'.$payment;
                }
                if (count($services) > 12) {
                    $lines[] = '- …and '.(count($services) - 12).' more (use list_bookable_services tool)';
                }
            }
        }

        if ($snapshot['events']) {
            $occurrences = $this->eventCatalog->upcomingOccurrencesForCompany($company, 8);
            if ($occurrences === []) {
                $lines[] = 'No upcoming bookable events.';
            } else {
                $lines[] = 'Upcoming events:';
                foreach ($occurrences as $occurrence) {
                    $payment = ($occurrence['payment_required'] ?? false)
                        ? ' (paid)'
                        : ' (free)';
                    $lines[] = '- '.$occurrence['event_title'].' — '.$occurrence['starts_at_label'].$payment;
                }
            }
        }

        return [
            'booking_context' => implode("\n", $lines),
            'voice_booking' => $snapshot,
        ];
    }
}
