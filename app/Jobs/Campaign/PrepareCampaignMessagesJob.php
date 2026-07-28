<?php

namespace App\Jobs\Campaign;

use App\Services\Campaign\CampaignMessagePreparationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Wpbox\Models\Campaign;

class PrepareCampaignMessagesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 3600;

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [60, 300, 900];
    }

    public function __construct(public int $campaignId)
    {
        $this->onQueue(config('wpbox.campaign_preparation_queue', 'campaigns'));
    }

    public function handle(CampaignMessagePreparationService $preparation): void
    {
        $campaign = Campaign::withoutGlobalScopes()->find($this->campaignId);

        if (! $campaign) {
            return;
        }

        $payload = $campaign->launch_payload ?? [];

        if (! is_array($payload)) {
            $payload = [];
        }

        $preparation->prepare($campaign, $payload);
    }
}
