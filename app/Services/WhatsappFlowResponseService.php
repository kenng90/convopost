<?php

namespace App\Services;

use App\Models\WhatsappFlow;
use App\Models\WhatsappFlowResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class WhatsappFlowResponseService
{
    private const INTERNAL_KEYS = ['flow_token', 'version', 'action', 'screen', 'name'];

    private const INPUT_FIELD_TYPES = [
        'text', 'textarea', 'radio', 'checkbox', 'select', 'date', 'chips', 'optin', 'media', 'dropdown', 'media_upload',
    ];

    /**
     * @return array<int, array{key: string, label: string, type: string, screen_title: string, screen_order: int, options: array<int, array<string, mixed>>}>
     */
    public function getInputFieldDefinitions(?WhatsappFlow $flow): array
    {
        if (! $flow || empty($flow->flow_json['screens'] ?? [])) {
            return [];
        }

        $definitions = [];

        foreach ($flow->flow_json['screens'] as $screenIndex => $screen) {
            foreach ($screen['fields'] ?? [] as $field) {
                $type = $field['type'] ?? '';
                if (! in_array($type, self::INPUT_FIELD_TYPES, true)) {
                    continue;
                }

                $definitions[] = [
                    'key' => $this->buildFieldKey($type, $field['id']),
                    'label' => $field['label'] ?? ucfirst(str_replace('_', ' ', $type)),
                    'type' => $type,
                    'screen_title' => $screen['title'] ?? 'Screen '.($screenIndex + 1),
                    'screen_order' => $screenIndex,
                    'options' => $field['options'] ?? [],
                ];
            }
        }

        return $definitions;
    }

    /**
     * @return array<string, mixed>
     */
    public function cleanResponses(mixed $rawResponses): array
    {
        if (is_string($rawResponses)) {
            $rawResponses = json_decode($rawResponses, true) ?? [];
        }

        if (! is_array($rawResponses)) {
            return [];
        }

        return array_filter(
            $rawResponses,
            fn ($key) => ! in_array($key, self::INTERNAL_KEYS, true),
            ARRAY_FILTER_USE_KEY
        );
    }

    /**
     * @param  array<string, mixed>  $responses
     * @return array<int, array{key: string, label: string, value: mixed, display_value: string, type: ?string, screen: ?string, screen_order: int}>
     */
    public function enrichResponsesFlat(array $responses, ?WhatsappFlow $flow): array
    {
        $responses = $this->cleanResponses($responses);
        $definitions = $this->getInputFieldDefinitions($flow);
        $definitionMap = collect($definitions)->keyBy('key');

        $enriched = [];

        foreach ($responses as $key => $value) {
            $field = $definitionMap->get($key);
            $enriched[] = [
                'key' => $key,
                'label' => $field['label'] ?? $this->humanizeKey($key),
                'value' => $value,
                'display_value' => $this->formatDisplayValue($value, $field),
                'type' => $field['type'] ?? null,
                'screen' => $field['screen_title'] ?? null,
                'screen_order' => $field['screen_order'] ?? 999,
            ];
        }

        usort($enriched, fn ($a, $b) => $a['screen_order'] <=> $b['screen_order']);

        return $enriched;
    }

    /**
     * @param  array<string, mixed>  $responses
     * @return array<int, array{title: string, order: int, fields: array<int, array<string, mixed>>}>
     */
    public function groupResponsesByScreen(array $responses, ?WhatsappFlow $flow): array
    {
        $responses = $this->cleanResponses($responses);
        $flat = $this->enrichResponsesFlat($responses, $flow);
        $grouped = [];

        foreach ($flat as $item) {
            $screenTitle = $item['screen'] ?? 'Other';
            $screenOrder = $item['screen_order'];

            if (! isset($grouped[$screenTitle])) {
                $grouped[$screenTitle] = [
                    'title' => $screenTitle,
                    'order' => $screenOrder,
                    'fields' => [],
                ];
            }

            $grouped[$screenTitle]['fields'][] = $item;
        }

        $screens = array_values($grouped);
        usort($screens, fn ($a, $b) => $a['order'] <=> $b['order']);

        return $screens;
    }

    /**
     * @param  array<string, mixed>  $responses
     */
    public function formatDisplayValue(mixed $value, ?array $field = null): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        $type = $field['type'] ?? null;

        if (is_bool($value) || $type === 'optin') {
            return ($value === true || $value === 'true' || $value === 1 || $value === '1') ? 'Yes' : 'No';
        }

        if (is_array($value)) {
            $labels = array_map(
                fn ($item) => $this->resolveOptionLabel((string) $item, $field),
                $value
            );

            return implode(', ', $labels) ?: '—';
        }

        if (in_array($type, ['radio', 'select', 'chips', 'dropdown'], true)) {
            return $this->resolveOptionLabel((string) $value, $field);
        }

        if ($type === 'date') {
            return $this->formatDateValue((string) $value);
        }

        return (string) $value;
    }

    /**
     * @param  array<string, mixed>  $responses
     */
    public function buildPreviewText(array $responses, ?WhatsappFlow $flow, int $limit = 2): string
    {
        $definitions = $this->getInputFieldDefinitions($flow);
        $parts = [];

        foreach (array_slice($definitions, 0, $limit) as $definition) {
            if (! array_key_exists($definition['key'], $responses)) {
                continue;
            }

            $display = $this->formatDisplayValue($responses[$definition['key']], $definition);
            if ($display !== '—') {
                $parts[] = $display;
            }
        }

        return implode(' · ', $parts);
    }

    /**
     * @param  Collection<int, WhatsappFlowResponse>  $responses
     * @return array<int, array<string, mixed>>
     */
    public function buildTableRows(Collection $responses, WhatsappFlow $flow): array
    {
        $definitions = $this->getInputFieldDefinitions($flow);
        $rows = [];

        foreach ($responses as $response) {
            $clean = $this->cleanResponses($response->responses);
            $cells = [];

            foreach ($definitions as $definition) {
                $value = $clean[$definition['key']] ?? null;
                $cells[$definition['key']] = $this->formatDisplayValue($value, $definition);
            }

            $rows[] = [
                'id' => $response->id,
                'contact_name' => $response->contact_name ?? 'Unknown',
                'contact_phone' => $response->contact_phone,
                'contact_id' => $response->contact_id,
                'status' => $response->status,
                'sent_at' => $response->sent_at,
                'completed_at' => $response->completed_at,
                'duration_seconds' => $this->durationSeconds($response),
                'preview' => $this->buildPreviewText($clean, $flow),
                'cells' => $cells,
            ];
        }

        return $rows;
    }

    /**
     * @param  Collection<int, WhatsappFlowResponse>  $responses
     * @return array<int, array<string, mixed>>
     */
    public function computeChoiceAnalytics(Collection $responses, WhatsappFlow $flow): array
    {
        $definitions = collect($this->getInputFieldDefinitions($flow))
            ->filter(fn ($field) => in_array($field['type'], ['radio', 'select', 'chips', 'checkbox', 'dropdown'], true));

        $completed = $responses->where('status', 'completed');
        $analytics = [];

        foreach ($definitions as $definition) {
            $counts = [];

            foreach ($completed as $response) {
                $clean = $this->cleanResponses($response->responses);
                $value = $clean[$definition['key']] ?? null;

                if ($value === null || $value === '') {
                    continue;
                }

                $values = is_array($value) ? $value : [$value];

                foreach ($values as $singleValue) {
                    $resolved = $this->resolveOptionLabel((string) $singleValue, $definition);
                    $counts[$resolved] = ($counts[$resolved] ?? 0) + 1;
                }
            }

            if (empty($counts)) {
                continue;
            }

            $total = array_sum($counts);
            arsort($counts);

            $options = [];
            foreach ($counts as $label => $count) {
                $options[] = [
                    'label' => $label,
                    'count' => $count,
                    'percent' => $total > 0 ? round(($count / $total) * 100, 1) : 0,
                ];
            }

            $analytics[] = [
                'field_key' => $definition['key'],
                'label' => $definition['label'],
                'type' => $definition['type'],
                'total' => $total,
                'options' => $options,
            ];
        }

        return $analytics;
    }

    /**
     * @param  Collection<int, WhatsappFlowResponse>  $responses
     * @return array<int, array<string, mixed>>
     */
    public function computeFunnelAnalytics(Collection $responses, WhatsappFlow $flow): array
    {
        $screens = $flow->flow_json['screens'] ?? [];
        $total = $responses->count();

        if ($total === 0 || empty($screens)) {
            return [];
        }

        $funnel = [
            [
                'label' => 'Flow sent',
                'count' => $total,
                'percent' => 100,
            ],
        ];

        foreach ($screens as $screenIndex => $screen) {
            $fieldKeys = [];
            foreach ($screen['fields'] ?? [] as $field) {
                $type = $field['type'] ?? '';
                if (in_array($type, self::INPUT_FIELD_TYPES, true)) {
                    $fieldKeys[] = $this->buildFieldKey($type, $field['id']);
                }
            }

            if (empty($fieldKeys)) {
                continue;
            }

            $reached = $responses->filter(function (WhatsappFlowResponse $response) use ($fieldKeys) {
                $clean = $this->cleanResponses($response->responses);

                foreach ($fieldKeys as $key) {
                    if (array_key_exists($key, $clean) && $clean[$key] !== null && $clean[$key] !== '' && $clean[$key] !== []) {
                        return true;
                    }
                }

                return false;
            })->count();

            $funnel[] = [
                'label' => $screen['title'] ?? 'Screen '.($screenIndex + 1),
                'count' => $reached,
                'percent' => $total > 0 ? round(($reached / $total) * 100, 1) : 0,
            ];
        }

        $completed = $responses->where('status', 'completed')->count();
        $funnel[] = [
            'label' => 'Completed',
            'count' => $completed,
            'percent' => $total > 0 ? round(($completed / $total) * 100, 1) : 0,
        ];

        return $funnel;
    }

    public function averageCompletionSeconds(Collection $responses): ?int
    {
        $durations = $responses
            ->where('status', 'completed')
            ->map(fn (WhatsappFlowResponse $response) => $this->durationSeconds($response))
            ->filter();

        if ($durations->isEmpty()) {
            return null;
        }

        return (int) round($durations->avg());
    }

    public function formatDuration(?int $seconds): string
    {
        if ($seconds === null) {
            return '—';
        }

        if ($seconds < 60) {
            return $seconds.'s';
        }

        $minutes = intdiv($seconds, 60);
        $remaining = $seconds % 60;

        return $remaining > 0 ? "{$minutes}m {$remaining}s" : "{$minutes}m";
    }

    public function exportCsv(Builder $query, WhatsappFlow $flow): StreamedResponse
    {
        $definitions = $this->getInputFieldDefinitions($flow);
        $filename = $this->buildExportFilename($flow);

        $headers = [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ];

        return response()->stream(function () use ($query, $definitions) {
            $file = fopen('php://output', 'w');

            $headerRow = array_merge(
                ['Contact Name', 'Phone', 'Status', 'Sent At', 'Completed At', 'Duration'],
                array_map(fn ($definition) => $definition['label'], $definitions)
            );
            fputcsv($file, $headerRow);

            $query->orderByDesc('created_at')->chunk(200, function ($responses) use ($file, $definitions) {
                foreach ($responses as $response) {
                    $clean = $this->cleanResponses($response->responses);
                    $row = [
                        $response->contact_name ?? 'Unknown',
                        $response->contact_phone ?? '',
                        ucfirst($response->status),
                        $response->sent_at?->format('Y-m-d H:i:s') ?? '',
                        $response->completed_at?->format('Y-m-d H:i:s') ?? '',
                        $this->formatDuration($this->durationSeconds($response)),
                    ];

                    foreach ($definitions as $definition) {
                        $value = $clean[$definition['key']] ?? '';
                        $row[] = $this->formatDisplayValue($value, $definition);
                    }

                    fputcsv($file, $row);
                }
            });

            fclose($file);
        }, 200, $headers);
    }

    public function applyFieldValueFilter(Builder $query, string $fieldKey, string $displayLabel, WhatsappFlow $flow): Builder
    {
        $definitions = collect($this->getInputFieldDefinitions($flow))->keyBy('key');
        $definition = $definitions->get($fieldKey);

        if (! $definition) {
            return $query;
        }

        $rawValues = $this->resolveRawValuesForLabel($displayLabel, $definition);

        return $query->where(function (Builder $inner) use ($fieldKey, $definition, $rawValues) {
            foreach ($rawValues as $rawValue) {
                if ($definition['type'] === 'checkbox') {
                    $inner->orWhereJsonContains('responses->'.$fieldKey, $rawValue);
                } else {
                    $inner->orWhere('responses->'.$fieldKey, $rawValue);
                }
            }
        });
    }

    private function buildExportFilename(WhatsappFlow $flow): string
    {
        $slug = preg_replace('/[^a-z0-9]+/i', '-', strtolower($flow->name)) ?: 'flow';

        return trim($slug, '-').'-responses-'.now()->format('Y-m-d').'.csv';
    }

    private function buildFieldKey(string $type, mixed $id): string
    {
        return $type.'_'.$id;
    }

    private function humanizeKey(string $key): string
    {
        $stripped = preg_replace('/^(text|textarea|radio|checkbox|select|date|chips|optin|media|dropdown)_\d+_?/i', '', $key);

        return $stripped
            ? ucwords(str_replace('_', ' ', $stripped))
            : ucwords(str_replace('_', ' ', $key));
    }

    private function resolveOptionLabel(string $value, ?array $field): string
    {
        if (! $field || empty($field['options'])) {
            return $value;
        }

        foreach ($field['options'] as $option) {
            $optionValue = (string) ($option['value'] ?? $option['id'] ?? '');
            if ($optionValue === $value) {
                return (string) ($option['label'] ?? $value);
            }
        }

        return $value;
    }

    /**
     * @return array<int, string>
     */
    private function resolveRawValuesForLabel(string $label, array $definition): array
    {
        $matches = [];

        foreach ($definition['options'] ?? [] as $option) {
            $optionLabel = (string) ($option['label'] ?? '');
            $optionValue = (string) ($option['value'] ?? $option['id'] ?? '');

            if ($optionLabel === $label || $optionValue === $label) {
                $matches[] = $optionValue;
            }
        }

        return $matches ?: [$label];
    }

    private function formatDateValue(string $value): string
    {
        try {
            return \Carbon\Carbon::parse($value)->format('d M Y');
        } catch (\Throwable) {
            return $value;
        }
    }

    private function durationSeconds(WhatsappFlowResponse $response): ?int
    {
        if (! $response->sent_at || ! $response->completed_at) {
            return null;
        }

        return $response->sent_at->diffInSeconds($response->completed_at);
    }
}
