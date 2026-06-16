<?php

namespace Modules\Reminders\Services;

use Carbon\Carbon;
use Modules\Reminders\Models\BookingClosure;
use Modules\Reminders\Models\Department;
use Modules\Reminders\Models\Source;

class BookingClosureService
{
    public function blocksDate(Source $source, Carbon $day): bool
    {
        $date = $day->toDateString();
        $departmentId = $source->department_id;

        return BookingClosure::query()
            ->where('company_id', $source->company_id)
            ->where(function ($query) use ($departmentId) {
                $query->whereNull('department_id');
                if ($departmentId) {
                    $query->orWhere('department_id', $departmentId);
                }
            })
            ->where('starts_on', '<=', $date)
            ->where(function ($query) use ($date) {
                $query->whereNull('ends_on')
                    ->orWhere('ends_on', '>=', $date);
            })
            ->exists();
    }

    /**
     * @param  array<int, array{label?: string|null, starts_on: string, ends_on?: string|null}>  $closures
     */
    public function syncForDepartment(Department $department, array $closures): void
    {
        BookingClosure::query()
            ->where('department_id', $department->id)
            ->delete();

        foreach ($closures as $closure) {
            $startsOn = $closure['starts_on'] ?? null;
            if (! $startsOn) {
                continue;
            }

            BookingClosure::create([
                'company_id' => $department->company_id,
                'department_id' => $department->id,
                'label' => $closure['label'] ?? null,
                'starts_on' => $startsOn,
                'ends_on' => $closure['ends_on'] ?? $startsOn,
            ]);
        }
    }
}
