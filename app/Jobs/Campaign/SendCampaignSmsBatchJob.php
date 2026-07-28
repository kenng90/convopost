<?php

namespace App\Jobs\Campaign;

use App\Services\Campaign\CampaignDispatchService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendCampaignSmsBatchJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 300;

    /**
     * @param  array<int, int>  $messageIds
     */
    public function __construct(public array $messageIds)
    {
        $this->onQueue(config('wpbox.campaign_send_queue', 'campaigns'));
    }

    public function handle(CampaignDispatchService $dispatchService): void
    {
        $dispatchService->dispatchSmsBatch($this->messageIds);
    }
}
