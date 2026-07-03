<?php

namespace Modules\Reminders\Services;

use App\Models\Company;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Reminders\Models\AppointmentStaff;
use Modules\Reminders\Models\Reservation;
use Modules\Reminders\Models\Source;
use Modules\Reminders\Support\SlotIdentifier;
use Modules\Wpbox\Models\Message;
use Modules\Wpbox\Traits\Contacts;

class ReservationBookingService
{
    use Contacts;

    public function __construct(
        private readonly AvailabilityService $availabilityService,
        private readonly GoogleCalendarService $googleCalendarService,
        private readonly AppointmentStaffNotificationService $staffNotifications,
        private readonly StaffAssignmentService $staffAssignmentService
    ) {
    }

    /**
     * @param  array{phone: string, name: string, source: string|int, slot_id?: string, start_date?: string, end_date?: string, duration_minutes?: int, appointment_staff_id?: int, staff_user_id?: int, external_id?: string|null}  $payload
     */
    public function book(Company $company, array $payload): Reservation
    {
        return DB::transaction(function () use ($company, $payload) {
            session(['company_id' => $company->id]);

            $source = $this->resolveSource($company, $payload['source']);
            $contact = $this->getOrMakeBookingContact($payload['phone'], $company, $payload['name']);

            [$start, $end, $appointmentStaffId, $staffUserId, $durationMinutes] = $this->resolveBookingWindow(
                $source,
                $payload
            );

            if (! $this->availabilityService->isSlotAvailable($source, $appointmentStaffId, $start, $durationMinutes)) {
                throw new \RuntimeException('Selected slot is no longer available.');
            }

            $reservation = Reservation::create([
                'company_id' => $company->id,
                'contact_id' => $contact->id,
                'source_id' => $source->id,
                'appointment_staff_id' => $appointmentStaffId,
                'staff_user_id' => $staffUserId,
                'start_date' => $start,
                'end_date' => $end,
                'duration_minutes' => $durationMinutes,
                'status' => 1,
                'external_id' => $payload['external_id'] ?? null,
                'flow_id' => $payload['flow_id'] ?? null,
                'flow_node_id' => $payload['flow_node_id'] ?? null,
                'booking_source' => $payload['booking_source'] ?? null,
                'voice_call_id' => $payload['voice_call_id'] ?? null,
            ]);

            $this->syncCalendarEvent($reservation->fresh(['contact', 'source', 'appointmentStaffMember']));

            $reservation = $reservation->fresh(['contact', 'source', 'appointmentStaffMember']);
            $this->staffNotifications->notifyBooked($reservation);

            return $reservation;
        });
    }

    public function cancel(Reservation $reservation): Reservation
    {
        return DB::transaction(function () use ($reservation) {
            if ($reservation->cancelled_at) {
                return $reservation;
            }

            $this->deletePendingReminderMessages($reservation);
            $this->deleteCalendarEvent($reservation);

            $reservation->update([
                'status' => 2,
                'cancelled_at' => now(),
            ]);

            $reservation = $reservation->fresh(['contact', 'source', 'appointmentStaffMember']);
            $this->staffNotifications->notifyCancelled($reservation);

            return $reservation;
        });
    }

    /**
     * @param  array{slot_id?: string, start_date?: string, end_date?: string, duration_minutes?: int, appointment_staff_id?: int, staff_user_id?: int}  $payload
     */
    public function reschedule(Reservation $reservation, array $payload): Reservation
    {
        return DB::transaction(function () use ($reservation, $payload) {
            if ($reservation->cancelled_at || (int) $reservation->status !== 1) {
                throw new \RuntimeException('Cancelled reservations cannot be rescheduled.');
            }

            $source = $reservation->source ?? Source::findOrFail($reservation->source_id);

            [$start, $end, $appointmentStaffId, $staffUserId, $durationMinutes] = $this->resolveBookingWindow(
                $source,
                array_merge($payload, [
                    'appointment_staff_id' => $payload['appointment_staff_id'] ?? $reservation->appointment_staff_id,
                    'staff_user_id' => $payload['staff_user_id'] ?? $reservation->staff_user_id,
                ])
            );

            if (! $this->availabilityService->isSlotAvailable(
                $source,
                $appointmentStaffId,
                $start,
                $durationMinutes,
                $reservation->id
            )) {
                throw new \RuntimeException('Selected slot is no longer available.');
            }

            $this->deletePendingReminderMessages($reservation);

            $reservation->update([
                'start_date' => $start,
                'end_date' => $end,
                'appointment_staff_id' => $appointmentStaffId,
                'staff_user_id' => $staffUserId,
                'duration_minutes' => $durationMinutes,
            ]);

            $reservation->refresh();
            $reservation->load(['contact', 'source', 'appointmentStaffMember']);

            $this->syncCalendarEvent($reservation, true);
            $reservation->makeMessages();
            $this->staffNotifications->notifyRescheduled($reservation);

            return $reservation->fresh(['contact', 'source', 'appointmentStaffMember']);
        });
    }

    private function resolveSource(Company $company, string|int $sourceRef): Source
    {
        $query = Source::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('is_bookable', true);

        if (is_numeric($sourceRef)) {
            return $query->where('id', (int) $sourceRef)->firstOrFail();
        }

        return $query->where('name', $sourceRef)->firstOrFail();
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{0: Carbon, 1: Carbon, 2: int, 3: int|null, 4: int}
     */
    private function resolveBookingWindow(Source $source, array $payload): array
    {
        if (! empty($payload['slot_id'])) {
            $decoded = SlotIdentifier::decode((string) $payload['slot_id']);
            if (! $decoded) {
                throw new \InvalidArgumentException('Invalid slot_id.');
            }

            $start = $decoded['start']->timezone($source->timezone ?: 'UTC');

            if ($decoded['auto_assign'] || $decoded['appointment_staff_id'] === 0) {
                $member = $this->staffAssignmentService->assign($source, $start, $decoded['duration_minutes']);
            } else {
                $member = AppointmentStaff::withoutGlobalScopes()->findOrFail($decoded['appointment_staff_id']);
            }

            return [
                $start,
                $start->copy()->addMinutes($decoded['duration_minutes']),
                $member->id,
                $member->user_id,
                $decoded['duration_minutes'],
            ];
        }

        if (empty($payload['start_date']) || empty($payload['end_date'])) {
            throw new \InvalidArgumentException('Either slot_id or start_date/end_date is required.');
        }

        $appointmentStaffId = (int) ($payload['appointment_staff_id'] ?? 0);
        if (! $appointmentStaffId) {
            throw new \InvalidArgumentException('appointment_staff_id is required when booking with explicit dates.');
        }

        $member = AppointmentStaff::withoutGlobalScopes()->findOrFail($appointmentStaffId);
        $start = Carbon::parse($payload['start_date'], $source->timezone ?: 'UTC');
        $end = Carbon::parse($payload['end_date'], $source->timezone ?: 'UTC');
        $duration = (int) ($payload['duration_minutes'] ?? $start->diffInMinutes($end));

        return [$start, $end, $member->id, $member->user_id, $duration];
    }

    private function syncCalendarEvent(Reservation $reservation, bool $updating = false): void
    {
        $member = $reservation->appointmentStaffMember;
        $source = $reservation->source;
        if (! $member || ! $source) {
            return;
        }

        $calendarUser = $reservation->google_calendar_user_id
            ? User::find($reservation->google_calendar_user_id)
            : null;

        if ($updating && $calendarUser && ($reservation->google_event_id || $reservation->external_id)) {
            $this->googleCalendarService->updateEventForReservation(
                $calendarUser,
                $reservation,
                $source,
                $member->calendarUser() ? null : $member->email
            );

            return;
        }

        $company = Company::find($reservation->company_id);
        $result = $this->googleCalendarService->createEventForAppointmentStaff(
            $member,
            $reservation,
            $source,
            $company?->user_id ? User::find($company->user_id) : null
        );

        if ($result) {
            $reservation->update([
                'google_event_id' => $result['event_id'],
                'google_calendar_user_id' => $result['calendar_user_id'],
            ]);
        }
    }

    private function deleteCalendarEvent(Reservation $reservation): void
    {
        if (! $reservation->google_calendar_user_id) {
            return;
        }

        $calendarUser = User::find($reservation->google_calendar_user_id);
        if ($calendarUser) {
            $this->googleCalendarService->deleteEventForReservation($calendarUser, $reservation);
        }
    }

    private function deletePendingReminderMessages(Reservation $reservation): void
    {
        Message::withoutGlobalScopes()
            ->where('company_id', $reservation->company_id)
            ->where('extra', $reservation->id)
            ->where('status', 0)
            ->delete();
    }
}
