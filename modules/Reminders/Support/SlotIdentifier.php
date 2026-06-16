<?php

namespace Modules\Reminders\Support;

use Carbon\Carbon;

class SlotIdentifier
{
    public static function encode(int $appointmentStaffId, Carbon $start, int $durationMinutes): string
    {
        return implode('|', [
            $appointmentStaffId,
            $start->copy()->utc()->format('Y-m-d\TH:i:s\Z'),
            $durationMinutes,
        ]);
    }

    public static function encodeAuto(Carbon $start, int $durationMinutes): string
    {
        return implode('|', [
            'auto',
            $start->copy()->utc()->format('Y-m-d\TH:i:s\Z'),
            $durationMinutes,
        ]);
    }

    public static function isAutoAssign(string $slotId): bool
    {
        return str_starts_with($slotId, 'auto|');
    }

    /**
     * @return array{appointment_staff_id: int, staff_user_id: int|null, start: Carbon, duration_minutes: int, auto_assign: bool}|null
     */
    public static function decode(string $slotId): ?array
    {
        $parts = explode('|', $slotId, 3);
        if (count($parts) !== 3) {
            return null;
        }

        [$staffId, $startIso, $duration] = $parts;

        if (! is_numeric($duration) && $staffId !== 'auto') {
            return null;
        }

        if ($staffId !== 'auto' && ! is_numeric($staffId)) {
            return null;
        }

        try {
            $start = Carbon::parse($startIso);
        } catch (\Throwable) {
            return null;
        }

        return [
            'appointment_staff_id' => $staffId === 'auto' ? 0 : (int) $staffId,
            'staff_user_id' => null,
            'start' => $start,
            'duration_minutes' => (int) $duration,
            'auto_assign' => $staffId === 'auto',
        ];
    }
}
