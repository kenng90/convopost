<?php

namespace App\Services\WhatsappFlowEndpointHandlers;

use App\Models\WhatsappFlow;

/**
 * Endpoint template: user picks a date → server returns time slots on the same screen.
 *
 * @see Meta dynamic components tutorial (DatePicker on-select-action + Dropdown data-source)
 */
class BookingSlotsHandler
{
    public const TEMPLATE_KEY = 'booking_slots';

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
     * @param  array<string, mixed>  $data  Request data from Meta (form + custom payload keys)
     * @return array<string, mixed>|null Null = not a date-update request
     */
    public function handleDataExchange(string $screenId, array $data): ?array
    {
        $date = $this->extractSelectedDate($data);

        if ($date === null) {
            return null;
        }

        return [
            'screen' => $screenId,
            'data' => [
                'is_dropdown_visible' => true,
                'available_slots' => $this->slotsForDate($date),
            ],
        ];
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

    /**
     * Demo slots — replace with real calendar/booking API integration.
     *
     * @return array<int, array<string, string>>
     */
    public function slotsForDate(string $date): array
    {
        return [
            ['id' => $date.'_08', 'title' => '08:00'],
            ['id' => $date.'_09', 'title' => '09:00'],
            ['id' => $date.'_10', 'title' => '10:00'],
            ['id' => $date.'_11', 'title' => '11:00'],
            ['id' => $date.'_14', 'title' => '14:00'],
        ];
    }
}
