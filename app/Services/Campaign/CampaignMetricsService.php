<?php

namespace App\Services\Campaign;

use Illuminate\Support\Facades\Cache;
use Modules\Wpbox\Models\Campaign;
use Modules\Wpbox\Models\Message;

class CampaignMetricsService
{
    public function recordDispatchRun(int $sent, int $queued): void
    {
        Cache::put('campaign_dispatch:last_run', [
            'at' => now()->toIso8601String(),
            'sent_sync' => $sent,
            'queued' => $queued,
        ], now()->addDay());
    }

    /**
     * @return array<string, mixed>
     */
    public function preparationProgress(Campaign $campaign): array
    {
        $prepared = (int) ($campaign->messages_prepared_count ?? 0);
        $target = max($prepared, (int) ($campaign->send_to ?? 0));

        return [
            'status' => $campaign->status,
            'prepared' => $prepared,
            'target' => $target,
            'percent' => $target > 0 ? round(($prepared / $target) * 100, 1) : 0,
            'error' => $campaign->preparation_error,
        ];
    }

    /**
     * @return array<string, int>
     */
    public function pendingCountsByCampaign(array $campaignIds): array
    {
        if ($campaignIds === []) {
            return [];
        }

        return Message::withoutGlobalScopes()
            ->selectRaw('campaign_id, COUNT(*) as pending_total')
            ->whereIn('campaign_id', $campaignIds)
            ->where('status', Message::STATUS_PENDING)
            ->groupBy('campaign_id')
            ->pluck('pending_total', 'campaign_id')
            ->map(fn ($count) => (int) $count)
            ->all();
    }
}
