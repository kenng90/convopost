<?php

namespace App\Services\Campaign;

use Illuminate\Support\Collection;
use Modules\Wpbox\Models\Campaign;
use Modules\Wpbox\Models\Message;

class CampaignAnalyticsService
{
    /**
     * @return array<string, mixed>
     */
    public function summarizeForChannel(Campaign $campaign): array
    {
        $summary = $this->summarize($campaign);
        $channel = $campaign->channel ?? Campaign::CHANNEL_WHATSAPP;
        $sendTo = max(1, (int) $campaign->send_to);

        $summary['channel'] = $channel;
        $summary['show_delivered_metric'] = $channel === Campaign::CHANNEL_WHATSAPP;
        $summary['show_read_metric'] = $channel === Campaign::CHANNEL_WHATSAPP;
        $summary['success_rate'] = round(((int) $summary['sent_count'] / $sendTo) * 100, 2);

        if ($channel !== Campaign::CHANNEL_WHATSAPP) {
            $summary['delivery_rate'] = $summary['success_rate'];
            $summary['read_rate'] = 0;
        }

        return $summary;
    }

    /**
     * @return array<string, mixed>
     */
    public function summarize(Campaign $campaign): array
    {
        $messages = $campaign->messages();

        $failedByReason = (clone $messages)
            ->where('status', Message::STATUS_FAILED)
            ->selectRaw('error, COUNT(*) as total')
            ->groupBy('error')
            ->pluck('total', 'error')
            ->toArray();

        $statusBreakdown = (clone $messages)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->toArray();

        $sendTo = max(1, (int) $campaign->send_to);

        return [
            'delivery_rate' => round(((int) $campaign->delivered_to / $sendTo) * 100, 2),
            'read_rate' => $campaign->delivered_to > 0
                ? round(((int) $campaign->read_by / (int) $campaign->delivered_to) * 100, 2)
                : 0,
            'failure_rate' => round(((int) ($statusBreakdown[Message::STATUS_FAILED] ?? 0) / $sendTo) * 100, 2),
            'pending_count' => (int) ($statusBreakdown[Message::STATUS_PENDING] ?? 0),
            'sent_count' => (int) ($statusBreakdown[Message::STATUS_SENT] ?? 0) + (int) ($statusBreakdown[2] ?? 0),
            'delivered_count' => (int) ($statusBreakdown[Message::STATUS_DELIVERED] ?? 0),
            'read_count' => (int) ($statusBreakdown[Message::STATUS_READ] ?? 0),
            'failed_count' => (int) ($statusBreakdown[Message::STATUS_FAILED] ?? 0),
            'failed_by_reason' => $failedByReason,
            'progress_percent' => $campaign->send_to > 0
                ? round(((int) $campaign->sended_to / (int) $campaign->send_to) * 100, 1)
                : 0,
        ];
    }

    /**
     * @return Collection<int, array{hour: string, sent: int, delivered: int, read: int}>
     */
    public function timeline(Campaign $campaign): Collection
    {
        return $campaign->messages()
            ->where('status', '>', Message::STATUS_PENDING)
            ->selectRaw("DATE_FORMAT(updated_at, '%Y-%m-%d %H:00') as hour, status, COUNT(*) as total")
            ->groupBy('hour', 'status')
            ->orderBy('hour')
            ->get()
            ->groupBy('hour')
            ->map(function ($rows, $hour) {
                return [
                    'hour' => $hour,
                    'sent' => $rows->whereIn('status', [Message::STATUS_SENT, 2])->sum('total'),
                    'delivered' => $rows->where('status', Message::STATUS_DELIVERED)->sum('total'),
                    'read' => $rows->where('status', Message::STATUS_READ)->sum('total'),
                ];
            })
            ->values();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function compareCampaigns(Collection $campaigns): array
    {
        return $campaigns->map(function (Campaign $campaign) {
            $summary = $this->summarize($campaign);

            return [
                'id' => $campaign->id,
                'name' => $campaign->name,
                'send_to' => $campaign->send_to,
                'delivery_rate' => $summary['delivery_rate'],
                'read_rate' => $summary['read_rate'],
                'failure_rate' => $summary['failure_rate'],
                'created_at' => $campaign->created_at?->toDateString(),
            ];
        })->all();
    }
}
