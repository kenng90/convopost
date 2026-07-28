<?php

namespace App\Jobs\Campaign;

use App\Services\Campaign\CampaignDispatchService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class EnqueueCampaignDispatchJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public ?int $limit = null)
    {
        $this->onQueue(config('wpbox.campaign_dispatch_queue', 'campaigns'));
    }

    public function handle(CampaignDispatchService $dispatchService): void
    {
        $dispatchService->enqueuePendingBatch($this->limit);
    }
}
