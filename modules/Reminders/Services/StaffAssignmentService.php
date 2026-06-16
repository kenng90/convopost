<?php

namespace Modules\Reminders\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Reminders\Models\AppointmentStaff;
use Modules\Reminders\Models\Reservation;
use Modules\Reminders\Models\Source;
use Modules\Reminders\Models\SourceStaff;

class StaffAssignmentService
{
    public function __construct(
        private readonly AvailabilityService $availabilityService
    ) {
    }

    public function assign(Source $source, Carbon $start, int $durationMinutes): AppointmentStaff
    {
        $mode = $source->staff_assignment_mode ?? Source::ASSIGNMENT_CUSTOMER_CHOICE;

        if ($mode === Source::ASSIGNMENT_CUSTOMER_CHOICE) {
            throw new \InvalidArgumentException('Auto-assignment is not enabled for this service.');
        }

        $eligible = $this->eligibleStaff($source, $start, $durationMinutes);

        if ($eligible->isEmpty()) {
            throw new \RuntimeException('No team member is available for this slot.');
        }

        return match ($mode) {
            Source::ASSIGNMENT_LEAST_BUSY => $this->pickLeastBusy($source, $eligible, $start),
            default => $this->pickRoundRobin($source, $eligible),
        };
    }

    /**
     * @return Collection<int, AppointmentStaff>
     */
    private function eligibleStaff(Source $source, Carbon $start, int $durationMinutes): Collection
    {
        return SourceStaff::query()
            ->with('appointmentStaff')
            ->where('source_id', $source->id)
            ->where('is_active', true)
            ->whereHas('appointmentStaff', fn ($query) => $query->where('is_active', true))
            ->get()
            ->filter(fn (SourceStaff $assignment) => $this->availabilityService->isSlotAvailable(
                $source,
                $assignment->appointment_staff_id,
                $start,
                $durationMinutes
            ))
            ->map(fn (SourceStaff $assignment) => $assignment->appointmentStaff)
            ->filter()
            ->values();
    }

    /**
     * @param  Collection<int, AppointmentStaff>  $eligible
     */
    private function pickRoundRobin(Source $source, Collection $eligible): AppointmentStaff
    {
        $eligibleIds = $eligible->pluck('id')->all();

        $pivot = SourceStaff::query()
            ->where('source_id', $source->id)
            ->whereIn('appointment_staff_id', $eligibleIds)
            ->orderByRaw('last_assigned_at IS NOT NULL')
            ->orderBy('last_assigned_at')
            ->orderBy('appointment_staff_id')
            ->lockForUpdate()
            ->first();

        if (! $pivot) {
            throw new \RuntimeException('No team member is available for this slot.');
        }

        $pivot->update(['last_assigned_at' => now()]);

        return AppointmentStaff::withoutGlobalScopes()->findOrFail($pivot->appointment_staff_id);
    }

    /**
     * @param  Collection<int, AppointmentStaff>  $eligible
     */
    private function pickLeastBusy(Source $source, Collection $eligible, Carbon $start): AppointmentStaff
    {
        $eligibleIds = $eligible->pluck('id')->all();
        $weekStart = $start->copy()->startOfWeek();
        $weekEnd = $start->copy()->endOfWeek();

        $counts = Reservation::withoutGlobalScopes()
            ->where('company_id', $source->company_id)
            ->where('source_id', $source->id)
            ->whereIn('appointment_staff_id', $eligibleIds)
            ->where('status', 1)
            ->whereNull('cancelled_at')
            ->where('start_date', '>=', $weekStart)
            ->where('start_date', '<=', $weekEnd)
            ->selectRaw('appointment_staff_id, COUNT(*) as booking_count')
            ->groupBy('appointment_staff_id')
            ->pluck('booking_count', 'appointment_staff_id');

        $sorted = $eligible->sortBy(function (AppointmentStaff $member) use ($counts) {
            return (int) ($counts[$member->id] ?? 0);
        })->values();

        $minCount = (int) ($counts[$sorted->first()->id] ?? 0);
        $tied = $sorted->filter(fn (AppointmentStaff $member) => (int) ($counts[$member->id] ?? 0) === $minCount);

        if ($tied->count() === 1) {
            $member = $tied->first();
            $this->touchAssignment($source, $member->id);

            return $member;
        }

        return $this->pickRoundRobin($source, $tied);
    }

    private function touchAssignment(Source $source, int $appointmentStaffId): void
    {
        DB::transaction(function () use ($source, $appointmentStaffId) {
            SourceStaff::query()
                ->where('source_id', $source->id)
                ->where('appointment_staff_id', $appointmentStaffId)
                ->lockForUpdate()
                ->update(['last_assigned_at' => now()]);
        });
    }
}
