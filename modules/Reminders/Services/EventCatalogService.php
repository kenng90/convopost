<?php

namespace Modules\Reminders\Services;

use App\Models\Company;
use Carbon\Carbon;
use Modules\Reminders\Models\Event;
use Modules\Reminders\Models\EventOccurrence;
use Modules\Reminders\Support\BookingPaymentConfig;

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
     * @return array<int, array{id: string, title: string, description: string, date_label: string, time_label: string, event_title: string, starts_at_label: string}>
     */
    public function upcomingOccurrencesAsFlowOptions(Company $company, int $limit = 10): array
    {
        return collect($this->publishedEventsForCompany($company))
            ->flatMap(fn (array $event) => collect($event['occurrences'])->map(function (array $occurrence) use ($event) {
                $payment = [
                    'payment_required' => $event['payment_required'] ?? false,
                    'payment_amount' => $event['payment_amount'] ?? null,
                    'payment_total_amount' => $event['payment_total_amount'] ?? null,
                    'payment_upfront_percent' => $event['payment_upfront_percent'] ?? 100,
                    'payment_currency' => $event['payment_currency'] ?? 'KES',
                ];

                $description = $occurrence['date_label'].' · '.$occurrence['time_label'];

                if (($occurrence['seats_remaining'] ?? null) !== null) {
                    $description .= ' · '.$occurrence['seats_remaining'].' '.__('seats left');
                }

                if ($payment['payment_required'] && $payment['payment_amount']) {
                    $description .= ' · '.__(':amount :currency', [
                        'amount' => number_format((float) $payment['payment_amount']),
                        'currency' => $payment['payment_currency'],
                    ]);
                }

                return [
                    'id' => (string) $occurrence['id'],
                    'event_id' => (string) $event['id'],
                    'title' => (string) $event['title'],
                    'description' => $description,
                    'date_label' => $occurrence['date_label'],
                    'time_label' => $occurrence['time_label'],
                    'event_title' => (string) $event['title'],
                    'starts_at_label' => $occurrence['starts_at_label'],
                    ...$payment,
                ];
            }))
            ->take($limit)
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function upcomingOccurrencesForCompany(Company $company, int $limit = 25): array
    {
        return $this->upcomingOccurrencesAsFlowOptions($company, $limit);
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
            ...BookingPaymentConfig::fromEvent($event),
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
        $endsAt = $occurrence->ends_at->timezone($timezone);

        return [
            'id' => $occurrence->id,
            'event_id' => $occurrence->event_id,
            'starts_at' => $occurrence->starts_at->toIso8601String(),
            'ends_at' => $occurrence->ends_at->toIso8601String(),
            'starts_at_label' => $startsAt->format('D j M Y, g:i A'),
            'date_label' => $startsAt->format('D, M j, Y'),
            'time_label' => $startsAt->format('g:i A').' – '.$endsAt->format('g:i A'),
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
