<?php

namespace Modules\Social\Services;

use App\Models\Company;
use Carbon\Carbon;
use Modules\Social\Models\SocialPost;
use Modules\Social\Models\SocialQueueSlot;

class SocialQueueSlotService
{
    /**
     * Recommended weekday/time pairs used when seeding a new company queue.
     *
     * @return list<array{weekday: int, time: string}>
     */
    public function recommendedSlots(): array
    {
        $slots = [];

        foreach ([1, 2, 3, 4, 5] as $weekday) {
            foreach (['09:00', '12:30', '17:00'] as $time) {
                $slots[] = ['weekday' => $weekday, 'time' => $time];
            }
        }

        $slots[] = ['weekday' => 6, 'time' => '10:00'];

        return $slots;
    }

    /**
     * @return \Illuminate\Support\Collection<int, SocialQueueSlot>
     */
    public function activeSlotsFor(Company $company)
    {
        return SocialQueueSlot::query()
            ->where('company_id', $company->id)
            ->where('is_active', true)
            ->orderBy('weekday')
            ->orderBy('time')
            ->get();
    }

    /**
     * Seed recommended slots when the company has none.
     *
     * @return list<SocialQueueSlot>
     */
    public function seedRecommended(Company $company): array
    {
        $timezone = (string) config('app.timezone', 'UTC');
        $created = [];

        foreach ($this->recommendedSlots() as $slot) {
            $created[] = SocialQueueSlot::query()->firstOrCreate(
                [
                    'company_id' => $company->id,
                    'weekday' => $slot['weekday'],
                    'time' => $slot['time'],
                ],
                [
                    'timezone' => $timezone,
                    'is_active' => true,
                ]
            );
        }

        return $created;
    }

    /**
     * Next free queue datetime after $after (defaults to now), in app timezone.
     */
    public function nextAvailableAt(Company $company, ?Carbon $after = null): ?Carbon
    {
        $slots = $this->activeSlotsFor($company);

        if ($slots->isEmpty()) {
            return null;
        }

        $appTimezone = (string) config('app.timezone', 'UTC');
        $after = ($after ?? now())->copy()->timezone($appTimezone);

        // Search up to 8 weeks ahead.
        for ($offset = 0; $offset < 56; $offset++) {
            $day = $after->copy()->startOfDay()->addDays($offset);
            $weekday = (int) $day->dayOfWeek;

            $daySlots = $slots->where('weekday', $weekday)->sortBy('time');

            foreach ($daySlots as $slot) {
                $slotTimezone = (string) ($slot->timezone ?: $appTimezone);
                $candidate = Carbon::createFromFormat(
                    'Y-m-d H:i',
                    $day->format('Y-m-d').' '.$slot->time,
                    $slotTimezone
                )->timezone($appTimezone);

                if ($candidate->lessThanOrEqualTo($after)) {
                    continue;
                }

                if ($this->isOccupied($company, $candidate)) {
                    continue;
                }

                return $candidate;
            }
        }

        return null;
    }

    public function isOccupied(Company $company, Carbon $at): bool
    {
        $start = $at->copy()->second(0);
        $end = $start->copy()->addMinute()->subSecond();

        return SocialPost::query()
            ->where('company_id', $company->id)
            ->where('status', 'scheduled')
            ->whereBetween('scheduled_at', [$start, $end])
            ->exists();
    }
}
