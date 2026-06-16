<?php

namespace Modules\Reminders\Services;

use App\Models\Company;
use Modules\Reminders\Models\EventRegistration;
use Modules\Reminders\Models\Reservation;

class BookingChatPanelService
{
    public function __construct(
        private readonly EventCatalogService $eventCatalogService
    ) {
    }

    /**
     * @return array{appointments: array<int, array<string, mixed>>, event_registrations: array<int, array<string, mixed>>, events_enabled: bool}
     */
    public function forContact(Company $company, int $contactId): array
    {
        $appointments = Reservation::query()
            ->with(['source', 'appointmentStaffMember'])
            ->where('contact_id', $contactId)
            ->orderByDesc('start_date')
            ->get()
            ->map(fn (Reservation $reservation) => $this->formatAppointment($reservation))
            ->values()
            ->all();

        $eventRegistrations = [];
        $eventsEnabled = $this->eventCatalogService->eventsEnabled($company);

        if ($eventsEnabled) {
            $eventRegistrations = EventRegistration::query()
                ->with(['event', 'occurrence'])
                ->where('contact_id', $contactId)
                ->orderByDesc('registered_at')
                ->get()
                ->map(fn (EventRegistration $registration) => $this->formatEventRegistration($registration))
                ->values()
                ->all();
        }

        return [
            'appointments' => $appointments,
            'event_registrations' => $eventRegistrations,
            'events_enabled' => $eventsEnabled,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function formatAppointment(Reservation $reservation): array
    {
        return [
            'id' => $reservation->id,
            'external_id' => $reservation->external_id,
            'start_date' => $reservation->start_date?->toIso8601String(),
            'end_date' => $reservation->end_date?->toIso8601String(),
            'duration_minutes' => $reservation->duration_minutes,
            'status_label' => $reservation->displayStatusLabel(),
            'status_badge_class' => $reservation->displayStatusBadgeClass(),
            'service_name' => $reservation->source?->name,
            'team_member_name' => $reservation->appointmentStaffMember?->name,
            'show_url' => route('reminders.reservations.show', ['reservation' => $reservation->id]),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function formatEventRegistration(EventRegistration $registration): array
    {
        return [
            'id' => $registration->id,
            'external_id' => $registration->external_id,
            'event_title' => $registration->event?->title,
            'starts_at' => $registration->occurrence?->starts_at?->toIso8601String(),
            'ends_at' => $registration->occurrence?->ends_at?->toIso8601String(),
            'party_size' => $registration->party_size,
            'status_label' => $registration->displayStatusLabel(),
            'status_badge_class' => $registration->displayStatusBadgeClass(),
            'show_url' => route('reminders.event-registrations.show', ['eventRegistration' => $registration->id]),
        ];
    }
}
