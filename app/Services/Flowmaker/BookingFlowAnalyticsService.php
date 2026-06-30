<?php

namespace App\Services\Flowmaker;

use Illuminate\Support\Facades\DB;
use Modules\Flowmaker\Models\FlowRunLog;

class BookingFlowAnalyticsService
{
    /** @var list<string> */
    public const FUNNEL_EVENTS = [
        'booking_wizard_started',
        'booking_service_selected',
        'booking_duration_selected',
        'booking_date_selected',
        'booking_slot_selected',
        'booking_payment_initiated',
        'booking_payment_retry',
        'booking_confirmed',
        'booking_unavailable',
        'booking_error',
        'booking_event_list_sent',
        'booking_event_selected',
        'booking_event_registered',
        'booking_event_error',
        'booking_link_sent',
        'booking_cancelled',
        'booking_rescheduled',
        'listing_inquiry_completed',
    ];

    /**
     * @return array<string, mixed>
     */
    public function summaryForFlow(int $flowId, int $days = 30): array
    {
        $since = now()->subDays($days);

        $counts = FlowRunLog::query()
            ->where('flow_id', $flowId)
            ->where('created_at', '>=', $since)
            ->whereIn('event', self::FUNNEL_EVENTS)
            ->select('event', DB::raw('count(*) as total'))
            ->groupBy('event')
            ->pluck('total', 'event')
            ->all();

        $started = (int) ($counts['booking_wizard_started'] ?? 0);
        $confirmed = (int) ($counts['booking_confirmed'] ?? 0);

        return [
            'days' => $days,
            'funnel' => $counts,
            'conversion_rate' => $started > 0 ? round(($confirmed / $started) * 100, 1) : null,
            'totals' => [
                'started' => $started,
                'confirmed' => $confirmed,
                'unavailable' => (int) ($counts['booking_unavailable'] ?? 0),
                'errors' => (int) ($counts['booking_error'] ?? 0),
            ],
        ];
    }
}
