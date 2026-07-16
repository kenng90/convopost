<?php

namespace Modules\Reminders\Services;

use App\Models\Company;
use App\Models\WhatsappFlow;
use Carbon\Carbon;
use Modules\Reminders\Models\Source;

/**
 * Supplies live booking options for WhatsApp Form data_exchange / INIT.
 */
class BookingFlowDataExchangeService
{
    public function __construct(
        private readonly BookingCatalogService $catalog,
        private readonly AvailabilityService $availability
    ) {
    }

    /**
     * @param  array<string, mixed>  $data  Submitted form fields from Meta
     * @return array{screen: string, data: object}|null
     */
    public function resolve(?WhatsappFlow $flow, string $screen, string $endpointTemplate, array $data): ?array
    {
        if (! $flow || ! $flow->company_id) {
            return null;
        }

        $company = Company::find($flow->company_id);
        if (! $company) {
            return null;
        }

        return match ($endpointTemplate) {
            'booking_catalog' => $this->resolveCatalogExchange($flow, $screen, $company, $data),
            'booking_slots' => [
                'screen' => $screen,
                'data' => (object) [
                    'slot_options' => $this->slotOptionsForRequest($company, $data),
                ],
            ],
            'booking_occurrences' => [
                'screen' => $screen,
                'data' => (object) [
                    'occurrence_options' => $this->occurrenceOptions($company),
                ],
            ],
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{screen: string, data: object}
     */
    private function resolveCatalogExchange(WhatsappFlow $flow, string $screen, Company $company, array $data): array
    {
        $serviceRaw = trim((string) ($data['service']
            ?? $data['select_service']
            ?? $data['department']
            ?? ''));
        $dateRaw = trim((string) ($data['preferred_date']
            ?? $data['date']
            ?? $data['appointment_date']
            ?? ''));

        // Footer / date submit on the catalog screen → load live slots on the next screen.
        if ($serviceRaw !== '' && $dateRaw !== '') {
            $slots = $this->slotOptionsForRequest($company, $data);
            $nextScreen = $this->nextScreenId($flow, $screen) ?? 'PICK_SLOT';

            if ($slots === []) {
                return [
                    'screen' => $screen,
                    'data' => (object) [
                        'service_options' => $this->catalog->bookableServicesAsFlowOptions($company),
                        'error_message' => 'No available times for that date. Try another day.',
                    ],
                ];
            }

            return [
                'screen' => $nextScreen,
                'data' => (object) [
                    'slot_options' => $slots,
                ],
            ];
        }

        return [
            'screen' => $screen,
            'data' => (object) [
                'service_options' => $this->catalog->bookableServicesAsFlowOptions($company),
            ],
        ];
    }

    private function nextScreenId(WhatsappFlow $flow, string $currentScreenId): ?string
    {
        $screens = $flow->flow_json['screens'] ?? [];
        if (! is_array($screens) || $screens === []) {
            return null;
        }

        $ids = collect($screens)->pluck('id')->filter()->values();
        $index = $ids->search($currentScreenId);

        if ($index === false) {
            return null;
        }

        return $ids->get($index + 1);
    }

    /**
     * Enrich INIT payload when the first screen uses a booking endpoint template.
     *
     * @return array<string, mixed>
     */
    public function initPayload(Company $company, string $endpointTemplate): array
    {
        return match ($endpointTemplate) {
            'booking_catalog' => [
                'service_options' => $this->catalog->bookableServicesAsFlowOptions($company),
            ],
            'booking_occurrences' => [
                'occurrence_options' => $this->occurrenceOptions($company),
            ],
            default => [],
        };
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<array{id: string, title: string}>
     */
    private function slotOptionsForRequest(Company $company, array $data): array
    {
        $serviceRaw = (string) ($data['service']
            ?? $data['select_service']
            ?? $data['department']
            ?? $data['service_options']
            ?? '');
        $dateRaw = (string) ($data['preferred_date']
            ?? $data['date']
            ?? $data['appointment_date']
            ?? $data['date_11']
            ?? '');

        if ($serviceRaw === '' || $dateRaw === '') {
            return [];
        }

        $source = Source::queryForCompany($company->id)
            ->where('is_bookable', true)
            ->where(function ($query) use ($serviceRaw) {
                $query->where('name', $serviceRaw)
                    ->orWhereRaw('LOWER(name) = ?', [mb_strtolower($serviceRaw)]);

                if (ctype_digit($serviceRaw)) {
                    $query->orWhere('id', (int) $serviceRaw);
                }
            })
            ->first();

        if (! $source) {
            // Match by option id → common clinic names
            $titleMap = [
                'general' => 'General Practice',
                'dental' => 'Dental',
                'lab' => 'Lab Tests',
            ];
            $mapped = $titleMap[mb_strtolower($serviceRaw)] ?? null;
            if ($mapped) {
                $source = Source::queryForCompany($company->id)
                    ->where('is_bookable', true)
                    ->whereRaw('LOWER(name) = ?', [mb_strtolower($mapped)])
                    ->first();
            }
        }

        if (! $source) {
            return [];
        }

        try {
            $date = Carbon::parse($dateRaw)->toDateString();
        } catch (\Throwable) {
            return [];
        }

        $duration = (int) ($source->default_duration_minutes ?: 30);
        $slots = $this->availability->slotsForDate($source, $date, $duration);

        return collect($slots)->map(fn (array $slot) => [
            'id' => (string) $slot['id'],
            'title' => (string) $slot['title'],
        ])->values()->all();
    }

    /**
     * @return list<array{id: string, title: string}>
     */
    private function occurrenceOptions(Company $company): array
    {
        if (! app(EventCatalogService::class)->eventsEnabled($company)) {
            return [];
        }

        return collect(app(EventCatalogService::class)->upcomingOccurrencesAsFlowOptions($company, 25))
            ->map(fn (array $row) => [
                'id' => (string) $row['id'],
                'title' => trim(($row['title'] ?? 'Event').' — '.($row['date_label'] ?? '').' '.($row['time_label'] ?? '')),
            ])
            ->values()
            ->all();
    }
}
