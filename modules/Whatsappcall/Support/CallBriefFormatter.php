<?php

namespace Modules\Whatsappcall\Support;

class CallBriefFormatter
{
    public static function toPlainText(array $payload): string
    {
        $lines = [];
        $handledBy = $payload['handled_by'] ?? 'ai';
        $duration = $payload['duration_seconds'] ?? null;
        $handoff = ! empty($payload['handoff_requested']);

        $header = '📞 '.($handledBy === 'ai' ? __('AI call') : __('Call'));
        if ($duration) {
            $header .= ' · '.self::formatDuration((int) $duration);
        }
        if ($handoff) {
            $header .= ' · '.__('Handoff requested');
        }
        $lines[] = $header;
        $lines[] = '';

        if (! empty($payload['intent']) && $payload['intent'] !== 'voice_call') {
            $lines[] = __('Intent').': '.$payload['intent'];
        }

        if (! empty($payload['summary_bullets']) && is_array($payload['summary_bullets'])) {
            $lines[] = '';
            $lines[] = __('Summary');
            foreach ($payload['summary_bullets'] as $bullet) {
                $lines[] = '• '.$bullet;
            }
        } elseif (! empty($payload['summary'])) {
            $lines[] = '';
            $lines[] = __('Summary');
            $lines[] = $payload['summary'];
        }

        if (! empty($payload['fields']) && is_array($payload['fields'])) {
            $lines[] = '';
            $lines[] = __('Captured');
            foreach ($payload['fields'] as $field) {
                $label = $field['label'] ?? $field['key'] ?? 'Field';
                $value = $field['value'] ?? '—';
                $status = $field['status'] ?? 'inferred';
                $icon = self::statusIcon($status);
                $lines[] = $icon.' '.$label.': '.$value;
                if (! empty($field['source_quote'])) {
                    $lines[] = '   "'.$field['source_quote'].'"';
                }
            }
        }

        if (! empty($payload['missing_required']) && is_array($payload['missing_required'])) {
            $missing = array_values(array_filter($payload['missing_required']));
            if ($missing !== []) {
                $lines[] = '';
                $lines[] = __('Missing').': '.implode(', ', $missing);
            }
        }

        if (! empty($payload['handoff_reason'])) {
            $lines[] = '';
            $lines[] = __('Handoff reason').': '.$payload['handoff_reason'];
        }

        if (! empty($payload['transcript_excerpt'])) {
            $lines[] = '';
            $lines[] = __('Transcript');
            $lines[] = $payload['transcript_excerpt'];
        }

        return trim(implode("\n", $lines));
    }

    public static function formatDuration(int $seconds): string
    {
        $minutes = intdiv($seconds, 60);
        $secs = $seconds % 60;

        if ($minutes > 0) {
            return sprintf('%dm %02ds', $minutes, $secs);
        }

        return sprintf('%ds', $secs);
    }

    public static function statusIcon(string $status): string
    {
        return match ($status) {
            'confirmed' => '✓',
            'corrected' => '↻',
            'missing' => '✗',
            default => '~',
        };
    }
}
