<?php

namespace Modules\Reminders\Support;

class WorkingHours
{
    /**
     * @return array<string, array{enabled: bool, start: string, end: string}>
     */
    public static function default(): array
    {
        $weekday = ['enabled' => true, 'start' => '09:00', 'end' => '17:00'];
        $weekend = ['enabled' => false, 'start' => '09:00', 'end' => '17:00'];

        return [
            'monday' => $weekday,
            'tuesday' => $weekday,
            'wednesday' => $weekday,
            'thursday' => $weekday,
            'friday' => $weekday,
            'saturday' => $weekend,
            'sunday' => $weekend,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $hours
     * @return array<string, array{enabled: bool, start: string, end: string}>
     */
    public static function normalize(?array $hours): array
    {
        $defaults = self::default();

        if ($hours === null) {
            return $defaults;
        }

        foreach ($defaults as $day => $config) {
            if (! isset($hours[$day]) || ! is_array($hours[$day])) {
                continue;
            }

            $defaults[$day] = [
                'enabled' => (bool) ($hours[$day]['enabled'] ?? $config['enabled']),
                'start' => (string) ($hours[$day]['start'] ?? $config['start']),
                'end' => (string) ($hours[$day]['end'] ?? $config['end']),
            ];
        }

        return $defaults;
    }

    public static function dayKey(\Carbon\Carbon $date): string
    {
        return strtolower($date->englishDayOfWeek);
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, array{enabled: bool, start: string, end: string}>
     */
    public static function fromRequest(array $input): array
    {
        $hours = [];

        foreach (array_keys(self::default()) as $day) {
            $dayInput = $input[$day] ?? [];
            $hours[$day] = [
                'enabled' => isset($dayInput['enabled']),
                'start' => (string) ($dayInput['start'] ?? '09:00'),
                'end' => (string) ($dayInput['end'] ?? '17:00'),
            ];
        }

        return self::normalize($hours);
    }
}
