<?php

namespace Modules\Reminders\Services;

use App\Models\Company;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Reminders\Jobs\SyncReservationCalendarJob;
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
        $reservation = DB::transaction(function () use ($company, $payload) {
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

            $this->queueCalendarSync($reservation->id, 'create');

            return $reservation->fresh(['contact', 'source', 'appointmentStaffMember']);
        });

        $this->staffNotifications->notifyBooked($reservation);

        app(\App\Services\Api\PublicWebhookDispatcher::class)->dispatch($company->id, 'booking.created', [
            'id' => $reservation->id,
            'contact_id' => $reservation->contact_id,
            'source_id' => $reservation->source_id,
            'start_date' => optional($reservation->start_date)?->toIso8601String(),
            'end_date' => optional($reservation->end_date)?->toIso8601String(),
        ]);

        try {
            if ($company->getConfig('outcome_booking_convert_installed', 'no') === 'yes' && $reservation->contact) {
                app(\App\Services\Outcomes\OutcomeJourneyEnroller::class)
                    ->enrollBooking($company, $reservation->contact);
            }
        } catch (\Throwable $e) {
            report($e);
        }

        return $reservation;
    }

    public function cancel(Reservation $reservation, ?string $cancellationSource = null): Reservation
    {
        $reservation = DB::transaction(function () use ($reservation, $cancellationSource) {
            if ($reservation->cancelled_at) {
                return $reservation;
            }

            $this->deletePendingReminderMessages($reservation);

            $this->queueCalendarSync(
                $reservation->id,
                'delete',
                $reservation->google_calendar_user_id,
                $reservation->google_event_id ?: $reservation->external_id,
                $reservation->google_calendar_id
            );

            $reservation->update([
                'status' => 2,
                'cancelled_at' => now(),
                'cancellation_source' => $cancellationSource,
            ]);

            return $reservation->fresh(['contact', 'source', 'appointmentStaffMember']);
        });

        $this->staffNotifications->notifyCancelled($reservation);

        app(\App\Services\Api\PublicWebhookDispatcher::class)->dispatch($reservation->company_id, 'booking.cancelled', [
            'id' => $reservation->id,
            'contact_id' => $reservation->contact_id,
            'source_id' => $reservation->source_id,
            'cancelled_at' => optional($reservation->cancelled_at)?->toIso8601String(),
        ]);

        return $reservation;
    }

    /**
     * @param  array{slot_id?: string, start_date?: string, end_date?: string, duration_minutes?: int, appointment_staff_id?: int, staff_user_id?: int}  $payload
     */
    public function reschedule(Reservation $reservation, array $payload): Reservation
    {
        $previousCalendar = [
            'user_id' => $reservation->google_calendar_user_id,
            'event_id' => $reservation->google_event_id ?: $reservation->external_id,
            'calendar_id' => $reservation->google_calendar_id,
        ];

        $reservation = DB::transaction(function () use ($reservation, $payload, $previousCalendar) {
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
                'previous_start_date' => $reservation->start_date,
                'previous_end_date' => $reservation->end_date,
                'rescheduled_at' => now(),
                'reschedule_count' => ((int) $reservation->reschedule_count) + 1,
                'start_date' => $start,
                'end_date' => $end,
                'appointment_staff_id' => $appointmentStaffId,
                'staff_user_id' => $staffUserId,
                'duration_minutes' => $durationMinutes,
            ]);

            $reservation->refresh();
            $reservation->load(['contact', 'source', 'appointmentStaffMember']);

            $this->queueCalendarSync(
                $reservation->id,
                'update',
                $previousCalendar['user_id'],
                $previousCalendar['event_id'],
                $previousCalendar['calendar_id']
            );

            $reservation->makeMessages();

            return $reservation->fresh(['contact', 'source', 'appointmentStaffMember']);
        });

        $this->staffNotifications->notifyRescheduled($reservation);

        app(\App\Services\Api\PublicWebhookDispatcher::class)->dispatch($reservation->company_id, 'booking.rescheduled', [
            'id' => $reservation->id,
            'contact_id' => $reservation->contact_id,
            'source_id' => $reservation->source_id,
            'start_date' => optional($reservation->start_date)?->toIso8601String(),
            'end_date' => optional($reservation->end_date)?->toIso8601String(),
        ]);

        return $reservation;
    }

    public function performCalendarCreate(Reservation $reservation): void
    {
        $this->syncCalendarEvent($reservation, false);
    }

    public function performCalendarUpdate(
        Reservation $reservation,
        ?int $previousCalendarUserId = null,
        ?string $previousEventId = null,
        ?string $previousCalendarId = null
    ): void {
        $member = $reservation->appointmentStaffMember;
        $source = $reservation->source;

        if (! $member || ! $source) {
            return;
        }

        $newCalendarUser = $this->resolveCalendarUserForMember($member, $reservation->company_id);
        $staffChangedCalendar = $previousCalendarUserId
            && $newCalendarUser
            && (int) $previousCalendarUserId !== (int) $newCalendarUser->id;

        if ($staffChangedCalendar && $previousEventId && $previousCalendarUserId) {
            $previousUser = User::find($previousCalendarUserId);
            if ($previousUser) {
                $deleted = $this->googleCalendarService->deleteEvent(
                    $previousUser,
                    $previousEventId,
                    $previousCalendarId
                );

                if (! $deleted) {
                    $reservation->update([
                        'google_calendar_sync_error' => __('Could not remove the previous Google Calendar event after staff reassignment.'),
                    ]);
                }
            }

            $this->syncCalendarEvent($reservation, false);

            return;
        }

        $this->syncCalendarEvent($reservation, true, $previousCalendarUserId, $previousEventId);
    }

    public function performCalendarDelete(
        ?Reservation $reservation,
        ?int $previousCalendarUserId = null,
        ?string $previousEventId = null,
        ?string $previousCalendarId = null
    ): void {
        $calendarUserId = $previousCalendarUserId ?? $reservation?->google_calendar_user_id;
        $eventId = $previousEventId ?? ($reservation?->google_event_id ?: $reservation?->external_id);
        $calendarId = $previousCalendarId ?? $reservation?->google_calendar_id;

        if (! $calendarUserId || ! $eventId) {
            return;
        }

        $calendarUser = User::find($calendarUserId);
        if (! $calendarUser) {
            return;
        }

        $deleted = $this->googleCalendarService->deleteEvent($calendarUser, $eventId, $calendarId);

        if (! $deleted && $reservation) {
            $reservation->update([
                'google_calendar_sync_error' => __('Could not delete Google Calendar event.'),
            ]);
        } elseif ($deleted && $reservation) {
            $reservation->update([
                'google_calendar_sync_error' => null,
            ]);
        }
    }

    private function queueCalendarSync(
        int $reservationId,
        string $action,
        ?int $previousCalendarUserId = null,
        ?string $previousEventId = null,
        ?string $previousCalendarId = null
    ): void {
        $dispatch = function () use (
            $reservationId,
            $action,
            $previousCalendarUserId,
            $previousEventId,
            $previousCalendarId
        ): void {
            SyncReservationCalendarJob::dispatch(
                $reservationId,
                $action,
                $previousCalendarUserId,
                $previousEventId,
                $previousCalendarId
            );
        };

        // RefreshDatabase wraps each test in a transaction, so afterCommit callbacks
        // never fire mid-test. Run the sync job immediately under PHPUnit.
        if (app()->runningUnitTests()) {
            SyncReservationCalendarJob::dispatchSync(
                $reservationId,
                $action,
                $previousCalendarUserId,
                $previousEventId,
                $previousCalendarId
            );

            return;
        }

        DB::afterCommit($dispatch);
    }

    private function resolveSource(Company $company, string|int $sourceRef): Source
    {
        $query = Source::queryForCompany($company->id)
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

    private function syncCalendarEvent(
        Reservation $reservation,
        bool $updating = false,
        ?int $fallbackCalendarUserId = null,
        ?string $fallbackEventId = null
    ): void {
        $member = $reservation->appointmentStaffMember;
        $source = $reservation->source;
        if (! $member || ! $source) {
            return;
        }

        $calendarUser = $reservation->google_calendar_user_id
            ? User::find($reservation->google_calendar_user_id)
            : null;

        if (! $calendarUser && $fallbackCalendarUserId) {
            $calendarUser = User::find($fallbackCalendarUserId);
        }

        $eventId = $reservation->google_event_id ?: $reservation->external_id ?: $fallbackEventId;

        if ($updating && $calendarUser && $eventId) {
            $attendeeEmail = $member->email && $calendarUser
                ? $this->googleCalendarService->attendeeEmailForMember($member, $calendarUser)
                : $member->email;

            // Temporarily ensure update uses the known event id when only external_id existed.
            if (! $reservation->google_event_id && $fallbackEventId) {
                $reservation->google_event_id = $fallbackEventId;
            }

            $updated = $this->googleCalendarService->updateEventForReservation(
                $calendarUser,
                $reservation,
                $source,
                $attendeeEmail
            );

            if ($updated) {
                $reservation->update([
                    'google_event_id' => $eventId,
                    'google_calendar_user_id' => $calendarUser->id,
                    'google_calendar_sync_error' => null,
                ]);

                return;
            }

            // If the existing event could not be patched, fall through and create a fresh one.
            $reservation->update([
                'google_calendar_sync_error' => __('Could not update Google Calendar event; creating a replacement.'),
            ]);
        }

        $company = Company::find($reservation->company_id);
        $result = $this->googleCalendarService->createEventForAppointmentStaff(
            $member,
            $reservation,
            $source,
            $company?->user_id ? User::find($company->user_id) : null
        );

        if ($result && ! empty($result['event_id'])) {
            $reservation->update([
                'google_event_id' => $result['event_id'],
                'google_calendar_user_id' => $result['calendar_user_id'],
                'google_calendar_id' => $result['google_calendar_id'],
                'google_calendar_sync_error' => null,
            ]);

            return;
        }

        $error = is_array($result) ? ($result['error'] ?? null) : null;
        if (! $error) {
            $syncInfo = $this->googleCalendarService->calendarSyncInfoForMember($member);
            $error = $syncInfo['connected']
                ? __('Could not create Google Calendar event.')
                : __('No Google Calendar connected for this team member.');
        }

        $reservation->update(['google_calendar_sync_error' => $error]);
    }

    private function resolveCalendarUserForMember(AppointmentStaff $member, int $companyId): ?User
    {
        $linked = $member->calendarUser();
        if ($linked && $this->googleCalendarService->isConnected($linked)) {
            return $linked;
        }

        return $this->googleCalendarService->resolveCompanyCalendarHost($companyId);
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
