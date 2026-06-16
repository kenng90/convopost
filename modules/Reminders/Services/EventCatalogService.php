<?php

namespace Modules\Reminders\Services;

use App\Models\Company;
use Carbon\Carbon;
use Modules\Reminders\Models\Event;
use Modules\Reminders\Models\EventOccurrence;

class EventCatalogService
{
    public function eventsEnabled(Company $company): bool
    {
        return filter_var($company->getConfig('ENABLE_EVENTS_BOOKING', 'true'), FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function publishedEventsForCompany(Company $company, ?Carbon $from = null): array
    {
        if (! $this->eventsEnabled($company)) {
            return [];
        }

        $from = $from ?: now();

        return Event::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('is_published', true)
            ->with(['occurrences' => function ($query) use ($from) {
                $query->where('status', EventOccurrence::STATUS_PUBLISHED)
                    ->where('starts_at', '>=', $from)
                    ->orderBy('starts_at');
            }, 'host'])
            ->orderBy('sort_order')
            ->orderBy('title')
            ->get()
            ->map(fn (Event $event) => $this->formatEvent($event))
            ->filter(fn (array $event) => ! empty($event['occurrences']))
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{id: int, title: string, description: string}>
     */
    public function upcomingOccurrencesAsFlowOptions(Company $company, int $limit = 10): array
    {
        return collect($this->publishedEventsForCompany($company))
            ->flatMap(fn (array $event) => collect($event['occurrences'])->map(fn (array $occurrence) => [
                'id' => (string) $occurrence['id'],
                'title' => $event['title'].' — '.$occurrence['starts_at_label'],
                'description' => $occurrence['seats_remaining'].' '.__('seats left'),
            ]))
            ->take($limit)
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function formatEvent(Event $event): array
    {
        $timezone = $event->timezone ?: 'UTC';

        return [
            'id' => $event->id,
            'title' => $event->title,
            'description' => $event->description,
            'location' => $event->location,
            'virtual_url' => $event->virtual_url,
            'timezone' => $timezone,
            'host_name' => $event->host?->name,
            'occurrences' => $event->occurrences
                ->map(fn (EventOccurrence $occurrence) => $this->formatOccurrence($occurrence, $timezone))
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function formatOccurrence(EventOccurrence $occurrence, ?string $timezone = null): array
    {
        $timezone = $timezone ?: $occurrence->event?->timezone ?: 'UTC';
        $startsAt = $occurrence->starts_at->timezone($timezone);

        return [
            'id' => $occurrence->id,
            'event_id' => $occurrence->event_id,
            'starts_at' => $occurrence->starts_at->toIso8601String(),
            'ends_at' => $occurrence->ends_at->toIso8601String(),
            'starts_at_label' => $startsAt->format('D j M Y, H:i'),
            'capacity' => $occurrence->capacity,
            'seats_remaining' => $occurrence->seatsRemaining(),
            'is_registerable' => $occurrence->isRegisterable(),
            'status' => $occurrence->status,
        ];
    }

    public function findRegisterableOccurrence(Company $company, int $occurrenceId): ?EventOccurrence
    {
        return EventOccurrence::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('id', $occurrenceId)
            ->where('status', EventOccurrence::STATUS_PUBLISHED)
            ->whereHas('event', fn ($query) => $query->where('is_published', true))
            ->with('event')
            ->first();
    }
}
