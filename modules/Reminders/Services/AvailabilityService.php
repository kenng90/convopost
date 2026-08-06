<?php

namespace Modules\Reminders\Services;

use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;
use Modules\Reminders\Models\Reservation;
use Modules\Reminders\Models\Source;
use Modules\Reminders\Models\SourceStaff;
use Modules\Reminders\Support\SlotIdentifier;
use Modules\Reminders\Support\WorkingHours;

class AvailabilityService
{
    public function __construct(
        private readonly GoogleCalendarService $googleCalendarService,
        private readonly BookingClosureService $closureService
    ) {
    }

    /**
     * @param  array<int, array<int, array{start: Carbon, end: Carbon}>>|null  $prefetchedBusyByUserId
     * @return array<int, array{id: string, title: string, start: string, end: string, appointment_staff_id: int|null, staff_user_id: int|null, staff_name: string|null, duration_minutes: int}>
     */
    public function slotsForDate(
        Source $source,
        string $date,
        ?int $durationMinutes = null,
        ?array $prefetchedBusyByUserId = null
    ): array {
        $source->loadMissing('department');

        $durationMinutes = $this->resolveDuration($source, $durationMinutes);
        $timezone = $source->timezone ?: 'UTC';
        $day = Carbon::parse($date, $timezone)->startOfDay();

        if (! $this->isDateBookable($source, $day)) {
            return [];
        }

        $staffMembers = $this->activeStaffForSource($source);
        if ($staffMembers->isEmpty()) {
            return [];
        }

        $slots = collect();

        foreach ($staffMembers as $staff) {
            $staffSlots = $this->slotsForStaff(
                $source,
                $staff,
                $day,
                $durationMinutes,
                $prefetchedBusyByUserId
            );
            $slots = $slots->merge($staffSlots);
        }

        $slots = $slots->sortBy('start')->values();

        if ($source->usesAutoStaffAssignment()) {
            return $this->aggregateSlotsForAutoAssignment($slots->all());
        }

        return $slots->all();
    }

    /**
     * @return array<int, string> ISO date strings that have at least one slot
     */
    public function availableDates(Source $source, Carbon $from, Carbon $to, ?int $durationMinutes = null): array
    {
        $source->loadMissing('department');

        $durationMinutes = $this->resolveDuration($source, $durationMinutes);
        $staffMembers = $this->activeStaffForSource($source);
        if ($staffMembers->isEmpty()) {
            return [];
        }

        $timezone = $source->timezone ?: 'UTC';
        $prefetchedBusyByUserId = $this->prefetchBusyBlocksForRange(
            $staffMembers,
            $from->copy()->startOfDay(),
            $to->copy()->endOfDay(),
            $timezone
        );

        $dates = [];
        $period = CarbonPeriod::create($from->copy()->startOfDay(), $to->copy()->startOfDay());

        foreach ($period as $day) {
            if ($this->slotsForDate($source, $day->toDateString(), $durationMinutes, $prefetchedBusyByUserId)) {
                $dates[] = $day->toDateString();
            }
        }

        return $dates;
    }

    public function isSlotAvailable(
        Source $source,
        int $appointmentStaffId,
        Carbon $start,
        int $durationMinutes,
        ?int $ignoreReservationId = null
    ): bool {
        $source->loadMissing('department');

        $end = $start->copy()->addMinutes($durationMinutes);
        $staff = SourceStaff::query()
            ->with('appointmentStaff.user')
            ->where('source_id', $source->id)
            ->where('appointment_staff_id', $appointmentStaffId)
            ->where('is_active', true)
            ->first();

        if (! $staff || ! $staff->appointmentStaff || ! $staff->appointmentStaff->is_active) {
            return false;
        }

        $timezone = $source->timezone ?: 'UTC';
        $day = $start->copy()->timezone($timezone)->startOfDay();

        if (! $this->isDateBookable($source, $day)) {
            return false;
        }

        $hours = $this->workingHoursForStaff($source, $staff, $day);
        if (! $hours['enabled']) {
            return false;
        }

        $dayStart = $day->copy()->setTimeFromTimeString($hours['start']);
        $dayEnd = $day->copy()->setTimeFromTimeString($hours['end']);

        if ($start->lt($dayStart) || $end->gt($dayEnd)) {
            return false;
        }

        if ($this->hasReservationConflict($source, $appointmentStaffId, $start, $end, $ignoreReservationId)) {
            return false;
        }

        $calendarUser = $staff->appointmentStaff->calendarUser();
        if ($calendarUser && $this->hasGoogleConflict($calendarUser, $start, $end, $source->timezone)) {
            return false;
        }

        return true;
    }

    public function resolveDuration(Source $source, ?int $durationMinutes): int
    {
        $options = $source->durationOptions();

        if ($durationMinutes !== null && in_array($durationMinutes, $options, true)) {
            return $durationMinutes;
        }

        return $source->default_duration_minutes ?: $options[0];
    }

    /**
     * @return Collection<int, SourceStaff>
     */
    private function activeStaffForSource(Source $source): Collection
    {
        return SourceStaff::query()
            ->with('appointmentStaff')
            ->where('source_id', $source->id)
            ->where('is_active', true)
            ->whereHas('appointmentStaff', fn ($query) => $query->where('is_active', true))
            ->get();
    }

    /**
     * Prefetch Google freeBusy once per calendar user for the whole range.
     *
     * @param  Collection<int, SourceStaff>  $staffMembers
     * @return array<int, array<int, array{start: Carbon, end: Carbon}>>
     */
    private function prefetchBusyBlocksForRange(
        Collection $staffMembers,
        Carbon $rangeStart,
        Carbon $rangeEnd,
        string $timezone
    ): array {
        $busyByUserId = [];

        foreach ($staffMembers as $staff) {
            $calendarUser = $staff->appointmentStaff?->calendarUser();
            if (! $calendarUser || isset($busyByUserId[$calendarUser->id])) {
                continue;
            }

            $busyByUserId[$calendarUser->id] = $this->googleCalendarService->busyBlocks(
                $calendarUser,
                $rangeStart->copy()->timezone($timezone),
                $rangeEnd->copy()->timezone($timezone),
                $timezone
            );
        }

        return $busyByUserId;
    }

    /**
     * @param  array<int, array<int, array{start: Carbon, end: Carbon}>>|null  $prefetchedBusyByUserId
     * @return array<int, array{id: string, title: string, start: string, end: string, appointment_staff_id: int, staff_user_id: int|null, staff_name: string, duration_minutes: int}>
     */
    private function slotsForStaff(
        Source $source,
        SourceStaff $staff,
        Carbon $day,
        int $durationMinutes,
        ?array $prefetchedBusyByUserId = null
    ): array {
        $timezone = $source->timezone ?: 'UTC';
        $hours = $this->workingHoursForStaff($source, $staff, $day);

        if (! $hours['enabled']) {
            return [];
        }

        $member = $staff->appointmentStaff;
        if (! $member) {
            return [];
        }

        $slotStart = $day->copy()->timezone($timezone)->setTimeFromTimeString($hours['start']);
        $dayEnd = $day->copy()->timezone($timezone)->setTimeFromTimeString($hours['end']);
        $step = $durationMinutes + (int) $source->buffer_minutes;
        $slots = [];

        $calendarUser = $member->calendarUser();
        $googleBusy = [];
        if ($calendarUser) {
            if ($prefetchedBusyByUserId !== null && array_key_exists($calendarUser->id, $prefetchedBusyByUserId)) {
                $googleBusy = $prefetchedBusyByUserId[$calendarUser->id];
            } else {
                $googleBusy = $this->googleCalendarService->busyBlocks(
                    $calendarUser,
                    $slotStart->copy()->timezone($timezone),
                    $dayEnd->copy()->timezone($timezone),
                    $timezone
                );
            }
        }

        while ($slotStart->copy()->addMinutes($durationMinutes)->lte($dayEnd)) {
            $slotEnd = $slotStart->copy()->addMinutes($durationMinutes);

            if ($slotStart->gte(now($timezone)->addHours((int) $source->min_notice_hours))) {
                $blocked = $this->hasReservationConflict($source, $member->id, $slotStart, $slotEnd)
                    || $this->overlapsBusy($slotStart, $slotEnd, $googleBusy);

                if (! $blocked) {
                    $slots[] = [
                        'id' => SlotIdentifier::encode($member->id, $slotStart, $durationMinutes),
                        'title' => $slotStart->format('H:i').' — '.$member->name,
                        'start' => $slotStart->toIso8601String(),
                        'end' => $slotEnd->toIso8601String(),
                        'appointment_staff_id' => $member->id,
                        'staff_user_id' => $member->user_id,
                        'staff_name' => $member->name,
                        'duration_minutes' => $durationMinutes,
                    ];
                }
            }

            $slotStart->addMinutes(max($step, 1));
        }

        return $slots;
    }

    /**
     * @param  array<int, array{id: string, title: string, start: string, end: string, appointment_staff_id: int, staff_user_id: int|null, staff_name: string, duration_minutes: int}>  $slots
     * @return array<int, array{id: string, title: string, start: string, end: string, appointment_staff_id: int|null, staff_user_id: int|null, staff_name: string|null, duration_minutes: int}>
     */
    private function aggregateSlotsForAutoAssignment(array $slots): array
    {
        return collect($slots)
            ->groupBy(fn (array $slot) => $slot['start'].'|'.$slot['duration_minutes'])
            ->map(function (Collection $group) {
                $slot = $group->first();
                $start = Carbon::parse($slot['start']);

                return [
                    'id' => SlotIdentifier::encodeAuto($start, $slot['duration_minutes']),
                    'title' => $start->format('H:i'),
                    'start' => $slot['start'],
                    'end' => $slot['end'],
                    'appointment_staff_id' => null,
                    'staff_user_id' => null,
                    'staff_name' => null,
                    'duration_minutes' => $slot['duration_minutes'],
                ];
            })
            ->sortBy('start')
            ->values()
            ->all();
    }

    /**
     * @return array{enabled: bool, start: string, end: string}
     */
    private function workingHoursForStaff(Source $source, SourceStaff $staff, Carbon $day): array
    {
        $hours = WorkingHours::normalize(
            $staff->working_hours
                ?: $staff->appointmentStaff?->working_hours
                ?: $source->working_hours
                ?: $source->department?->working_hours
        );
        $dayKey = WorkingHours::dayKey($day);

        return $hours[$dayKey] ?? ['enabled' => false, 'start' => '09:00', 'end' => '17:00'];
    }

    private function isDateBookable(Source $source, Carbon $day): bool
    {
        $timezone = $source->timezone ?: 'UTC';
        $today = now($timezone)->startOfDay();
        $maxDate = $today->copy()->addDays((int) $source->max_advance_days);

        if (! $day->betweenIncluded($today, $maxDate)) {
            return false;
        }

        return ! $this->closureService->blocksDate($source, $day);
    }

    private function hasReservationConflict(
        Source $source,
        int $appointmentStaffId,
        Carbon $start,
        Carbon $end,
        ?int $ignoreReservationId = null
    ): bool {
        $query = Reservation::withoutGlobalScopes()
            ->where('company_id', $source->company_id)
            ->where('source_id', $source->id)
            ->where('appointment_staff_id', $appointmentStaffId)
            ->where('status', 1)
            ->whereNull('cancelled_at')
            ->where('start_date', '<', $end)
            ->where('end_date', '>', $start);

        if ($ignoreReservationId) {
            $query->where('id', '!=', $ignoreReservationId);
        }

        return $query->exists();
    }

    private function hasGoogleConflict(User $user, Carbon $start, Carbon $end, string $timezone): bool
    {
        $busy = $this->googleCalendarService->busyBlocks($user, $start, $end, $timezone);

        return $this->overlapsBusy($start, $end, $busy);
    }

    /**
     * @param  array<int, array{start: Carbon, end: Carbon}>  $busyBlocks
     */
    private function overlapsBusy(Carbon $start, Carbon $end, array $busyBlocks): bool
    {
        foreach ($busyBlocks as $block) {
            if ($start->lt($block['end']) && $end->gt($block['start'])) {
                return true;
            }
        }

        return false;
    }
}
