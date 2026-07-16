<?php

namespace Modules\Reminders\Services;

use App\Models\Company;
use Carbon\Carbon;
use Modules\Flowmaker\Models\Contact;
use Modules\Reminders\Models\Source;

class BookingFormBridgeService
{
    public function __construct(
        private readonly AvailabilityService $availability
    ) {
    }

    /**
     * Resolve a bookable Source + slot from WhatsApp Form contact-state variables.
     *
     * @param  array<string, mixed>  $settings  BookAppointment node settings
     * @return array{
     *     status: 'ready'|'needs_slot_pick'|'unavailable'|'error',
     *     source?: Source,
     *     slot_id?: string,
     *     duration_minutes?: int,
     *     date?: string,
     *     slots?: list<array<string, mixed>>,
     *     message?: string
     * }
     */
    public function resolve(Contact $contact, Company $company, int $flowId, array $settings): array
    {
        $source = $this->resolveSource($contact, $company, $flowId, $settings);
        if (! $source) {
            return [
                'status' => 'error',
                'message' => __('Could not match your form answers to a bookable service. Please contact us.'),
            ];
        }

        $duration = $this->resolveDuration($source, $settings, $contact, $flowId);
        $explicitSlot = $this->readFormValue($contact, $flowId, $settings['formFieldMap']['slotField'] ?? [
            'form_slot', 'slot', 'slot_id', 'form_slot_id',
        ]);

        if (is_string($explicitSlot) && $explicitSlot !== '') {
            return [
                'status' => 'ready',
                'source' => $source,
                'slot_id' => $explicitSlot,
                'duration_minutes' => $duration,
            ];
        }

        $preferredDate = $this->resolvePreferredDate($contact, $flowId, $settings);
        if (! $preferredDate) {
            return [
                'status' => 'error',
                'message' => __('Please include a preferred date on the form so we can book your appointment.'),
            ];
        }

        $slots = $this->availability->slotsForDate($source, $preferredDate, $duration);
        if ($slots !== []) {
            if (count($slots) === 1) {
                return [
                    'status' => 'ready',
                    'source' => $source,
                    'slot_id' => (string) $slots[0]['id'],
                    'duration_minutes' => $duration,
                    'date' => $preferredDate,
                ];
            }

            return [
                'status' => 'needs_slot_pick',
                'source' => $source,
                'duration_minutes' => $duration,
                'date' => $preferredDate,
                'slots' => $slots,
            ];
        }

        $from = Carbon::parse($preferredDate)->addDay()->startOfDay();
        $to = Carbon::parse($preferredDate)->addDays(7)->endOfDay();
        $availableDates = $this->availability->availableDates($source, $from, $to, $duration);

        foreach ($availableDates as $date) {
            $nextSlots = $this->availability->slotsForDate($source, $date, $duration);
            if ($nextSlots === []) {
                continue;
            }

            return [
                'status' => 'needs_slot_pick',
                'source' => $source,
                'duration_minutes' => $duration,
                'date' => $date,
                'slots' => $nextSlots,
                'message' => __('No openings on :date. Pick a time on the next available day (:next).', [
                    'date' => $preferredDate,
                    'next' => $date,
                ]),
            ];
        }

        return [
            'status' => 'unavailable',
            'source' => $source,
            'message' => __('No available appointment slots in the next week. Please try another day or contact us.'),
        ];
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    public function hasFormIntakeSignals(Contact $contact, int $flowId, array $settings): bool
    {
        $service = $this->readFormValue($contact, $flowId, $settings['formFieldMap']['serviceField'] ?? [
            'form_select_3', 'select_3', 'form_department', 'department',
        ]);
        $date = $this->resolvePreferredDate($contact, $flowId, $settings);
        $slot = $this->readFormValue($contact, $flowId, $settings['formFieldMap']['slotField'] ?? [
            'form_slot', 'slot_id',
        ]);

        return ($service !== null && $service !== '') || $date !== null || ($slot !== null && $slot !== '');
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    private function resolveSource(Contact $contact, Company $company, int $flowId, array $settings): ?Source
    {
        if (! empty($settings['source_id']) && $settings['source_id'] !== 'none') {
            return Source::queryForCompany($company->id)
                ->where('is_bookable', true)
                ->where('id', $settings['source_id'])
                ->first();
        }

        $serviceRaw = $this->readFormValue($contact, $flowId, $settings['formFieldMap']['serviceField'] ?? [
            'form_select_3', 'select_3', 'form_department', 'department', 'form_service', 'service',
        ]);

        $optionMap = $settings['serviceOptionMap'] ?? [];
        if (is_string($optionMap)) {
            $decoded = json_decode($optionMap, true);
            $optionMap = is_array($decoded) ? $decoded : [];
        }
        if (is_array($optionMap) && is_string($serviceRaw) && $serviceRaw !== '' && isset($optionMap[$serviceRaw])) {
            $mapped = $optionMap[$serviceRaw];
            if (is_numeric($mapped)) {
                return Source::queryForCompany($company->id)
                    ->where('is_bookable', true)
                    ->where('id', (int) $mapped)
                    ->first();
            }

            return Source::queryForCompany($company->id)
                ->where('is_bookable', true)
                ->whereRaw('LOWER(name) = ?', [mb_strtolower((string) $mapped)])
                ->first();
        }

        if (is_string($serviceRaw) && $serviceRaw !== '') {
            $byName = Source::queryForCompany($company->id)
                ->where('is_bookable', true)
                ->whereRaw('LOWER(name) = ?', [mb_strtolower($serviceRaw)])
                ->first();
            if ($byName) {
                return $byName;
            }

            // Match option titles like "General Practice" when form stores id "general"
            $titleMap = [
                'general' => 'General Practice',
                'dental' => 'Dental',
                'lab' => 'Lab Tests',
            ];
            if (isset($titleMap[mb_strtolower($serviceRaw)])) {
                $byTitle = Source::queryForCompany($company->id)
                    ->where('is_bookable', true)
                    ->whereRaw('LOWER(name) = ?', [mb_strtolower($titleMap[mb_strtolower($serviceRaw)])])
                    ->first();
                if ($byTitle) {
                    return $byTitle;
                }
            }
        }

        $fixed = trim((string) ($settings['source_name'] ?? ''));
        if ($fixed !== '') {
            return Source::queryForCompany($company->id)
                ->where('is_bookable', true)
                ->whereRaw('LOWER(name) = ?', [mb_strtolower($fixed)])
                ->first();
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    private function resolveDuration(Source $source, array $settings, Contact $contact, int $flowId): int
    {
        if (! empty($settings['duration_minutes'])) {
            return (int) $settings['duration_minutes'];
        }

        $fromForm = $this->readFormValue($contact, $flowId, [
            'form_duration', 'duration_minutes', 'form_duration_minutes',
        ]);
        if (is_numeric($fromForm)) {
            return (int) $fromForm;
        }

        $options = $source->durationOptions();
        if ($options !== []) {
            return (int) $options[0];
        }

        return (int) ($source->default_duration_minutes ?: 30);
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    private function resolvePreferredDate(Contact $contact, int $flowId, array $settings): ?string
    {
        $raw = $this->readFormValue($contact, $flowId, $settings['formFieldMap']['dateField'] ?? [
            'form_date_4', 'date_4', 'form_preferred_date', 'preferred_date', 'appointment_date', 'form_appointment_date',
        ]);

        if (! is_string($raw) || trim($raw) === '') {
            return null;
        }

        try {
            return Carbon::parse(trim($raw))->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param  string|list<string>  $keys
     */
    private function readFormValue(Contact $contact, int $flowId, string|array $keys): ?string
    {
        $keys = is_array($keys) ? $keys : [$keys];

        foreach ($keys as $key) {
            if (! is_string($key) || $key === '') {
                continue;
            }

            $candidates = [$key];
            if (! str_starts_with($key, 'form_')) {
                $candidates[] = 'form_'.$key;
            }

            foreach ($candidates as $candidate) {
                $value = $contact->getContactStateValue($flowId, $candidate);
                if ($value !== null && $value !== '') {
                    return is_array($value) ? json_encode($value) : (string) $value;
                }
            }
        }

        return null;
    }
}
