<?php

namespace App\Services\WhatsappFlowEndpointHandlers;

use App\Models\WhatsappFlow;
use Modules\Reminders\Models\Source;
use Modules\Reminders\Services\AvailabilityService;

/**
 * Endpoint template: user picks a date → server returns time slots on the same screen.
 */
class BookingSlotsHandler
{
    public const TEMPLATE_KEY = 'booking_slots';

    public function __construct(
        private readonly AvailabilityService $availabilityService
    ) {
    }

    public function supportsScreen(?WhatsappFlow $flow, ?string $screenId): bool
    {
        if (! $flow || ! $screenId) {
            return false;
        }

        foreach ($flow->flow_json['screens'] ?? [] as $screen) {
            if (($screen['id'] ?? '') === $screenId) {
                return ($screen['endpoint_template'] ?? '') === self::TEMPLATE_KEY;
            }
        }

        return false;
    }

    /**
     * @return array<string, mixed>
     */
    public function initData(): array
    {
        return [
            'is_dropdown_visible' => false,
            'available_slots' => [],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>|null
     */
    public function handleDataExchange(string $screenId, array $data, ?WhatsappFlow $flow = null): ?array
    {
        $date = $this->extractSelectedDate($data);

        if ($date === null) {
            return null;
        }

        $source = $this->resolveSource($flow, $data);
        if (! $source) {
            return [
                'screen' => $screenId,
                'data' => [
                    'is_dropdown_visible' => false,
                    'available_slots' => [],
                ],
            ];
        }

        $duration = isset($data['duration_minutes']) ? (int) $data['duration_minutes'] : null;
        if ($duration === null) {
            $duration = (int) ($this->screenConfig($flow, $screenId)['booking_duration_minutes'] ?? 0);
            $duration = $duration > 0 ? $duration : null;
        }

        $slots = $this->availabilityService->slotsForDate($source, $date, $duration);

        return [
            'screen' => $screenId,
            'data' => [
                'is_dropdown_visible' => count($slots) > 0,
                'available_slots' => collect($slots)->map(fn ($slot) => [
                    'id' => $slot['id'],
                    'title' => $slot['title'],
                ])->values()->all(),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function resolveSource(?WhatsappFlow $flow, array $data): ?Source
    {
        $sourceName = $data['booking_source']
            ?? $data['booking_service']
            ?? $data['service']
            ?? null;

        if (! $sourceName) {
            foreach ($data as $key => $value) {
                if (is_string($key) && str_starts_with($key, 'select_') && is_string($value) && $value !== '') {
                    $sourceName = $value;
                    break;
                }
            }
        }

        $companyId = session('company_id') ?: $flow?->company_id;

        if (! $sourceName && $flow) {
            foreach ($flow->flow_json['screens'] ?? [] as $screen) {
                if (! empty($screen['booking_source'])) {
                    $sourceName = $screen['booking_source'];
                    break;
                }
            }
        }

        if (! $sourceName || ! $companyId) {
            return null;
        }

        if ($flow?->company_id) {
            session(['company_id' => $flow->company_id]);
        }

        return Source::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('is_bookable', true)
            ->where('name', $sourceName)
            ->first();
    }

    /**
     * @return array<string, mixed>
     */
    private function screenConfig(?WhatsappFlow $flow, string $screenId): array
    {
        if (! $flow) {
            return [];
        }

        foreach ($flow->flow_json['screens'] ?? [] as $screen) {
            if (($screen['id'] ?? '') === $screenId) {
                return $screen;
            }
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function extractSelectedDate(array $data): ?string
    {
        if (($data['component_action'] ?? '') === 'update_date') {
            foreach ($data as $key => $value) {
                if (is_string($key) && str_starts_with($key, 'date_') && is_string($value) && $value !== '') {
                    return $value;
                }
            }
        }

        foreach ($data as $key => $value) {
            if (is_string($key) && str_starts_with($key, 'date_') && is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
                return $value;
            }
        }

        return null;
    }
}
