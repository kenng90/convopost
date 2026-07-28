<?php

namespace App\Console\Commands;

use App\Services\Campaign\CampaignAnalyticsService;
use App\Services\Campaign\CampaignMetricsService;
use App\Services\Campaign\CampaignWebhookDispatcher;
use Illuminate\Console\Command;
use Modules\Wpbox\Models\Campaign;

class CheckCampaignCompletion extends Command
{
    protected $signature = 'campaigns:check-completion';

    protected $description = 'Mark campaigns as completed and fire webhooks when all messages are processed';

    public function handle(
        CampaignWebhookDispatcher $webhooks,
        CampaignAnalyticsService $analytics,
        CampaignMetricsService $metrics,
    ): int {
        $candidates = Campaign::withoutGlobalScopes()
            ->whereIn('status', [Campaign::STATUS_SENDING, Campaign::STATUS_SCHEDULED])
            ->where('send_to', '>', 0)
            ->get();

        $pendingCounts = $metrics->pendingCountsByCampaign($candidates->pluck('id')->all());
        $completed = 0;

        foreach ($candidates as $campaign) {
            $pending = $pendingCounts[$campaign->id] ?? 0;

            if ($pending > 0) {
                if ($campaign->status !== Campaign::STATUS_SENDING) {
                    $campaign->update(['status' => Campaign::STATUS_SENDING]);
                }

                continue;
            }

            $campaign->update([
                'status' => Campaign::STATUS_COMPLETED,
                'completed_at' => now(),
            ]);

            $webhooks->dispatchCampaignCompleted($campaign);
            $completed++;
        }

        $this->info("Marked {$completed} campaign(s) as completed.");

        return self::SUCCESS;
    }
}
