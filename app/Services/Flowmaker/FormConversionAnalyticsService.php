<?php

namespace App\Services\Flowmaker;

use App\Models\WhatsappFlowResponse;
use Illuminate\Support\Facades\DB;
use Modules\Flowmaker\Models\FlowRunLog;

class FormConversionAnalyticsService
{
    /** @var list<string> */
    public const FORM_EVENTS = [
        'whatsapp_form_sent',
        'whatsapp_form_completed',
        'whatsapp_form_abandoned',
    ];

    /** @var list<string> */
    public const CONVERSION_EVENTS = [
        'payment_succeeded',
        'booking_confirmed',
        'order_status_updated',
    ];

    /**
     * @return array<string, mixed>
     */
    public function summaryForForm(int $whatsappFlowId, int $days = 30): array
    {
        $since = now()->subDays($days);

        $responses = WhatsappFlowResponse::query()
            ->where('whatsapp_flow_id', $whatsappFlowId)
            ->where('created_at', '>=', $since)
            ->get(['status', 'flow_id', 'sent_at', 'completed_at']);

        $sent = $responses->count();
        $completed = $responses->where('status', 'completed')->count();
        $abandoned = $responses->where('status', 'abandoned')->count();

        $automationFlowIds = $responses->pluck('flow_id')->filter()->unique()->values()->all();
        $converted = 0;
        $eventCounts = [];

        if ($automationFlowIds !== []) {
            $eventCounts = FlowRunLog::query()
                ->whereIn('flow_id', $automationFlowIds)
                ->where('created_at', '>=', $since)
                ->whereIn('event', array_merge(self::FORM_EVENTS, self::CONVERSION_EVENTS))
                ->select('event', DB::raw('count(*) as total'))
                ->groupBy('event')
                ->pluck('total', 'event')
                ->all();

            $converted = (int) ($eventCounts['payment_succeeded'] ?? 0)
                + (int) ($eventCounts['booking_confirmed'] ?? 0);
        }

        return [
            'days' => $days,
            'totals' => [
                'sent' => $sent,
                'completed' => $completed,
                'abandoned' => $abandoned,
                'converted' => $converted,
            ],
            'completion_rate' => $sent > 0 ? round(($completed / $sent) * 100, 1) : null,
            'conversion_rate' => $completed > 0 ? round(($converted / $completed) * 100, 1) : null,
            'events' => $eventCounts,
            'funnel' => [
                ['stage' => 'Sent', 'count' => $sent],
                ['stage' => 'Completed', 'count' => $completed],
                ['stage' => 'Abandoned', 'count' => $abandoned],
                ['stage' => 'Converted (paid/booked)', 'count' => $converted],
            ],
            'automation_flow_ids' => $automationFlowIds,
        ];
    }
}
