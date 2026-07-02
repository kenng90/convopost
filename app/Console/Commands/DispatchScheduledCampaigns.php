<?php

namespace App\Console\Commands;

use App\Services\Campaign\CampaignDispatchService;
use Illuminate\Console\Command;

class DispatchScheduledCampaigns extends Command
{
    protected $signature = 'campaigns:dispatch-scheduled {--limit= : Max messages to send per run}';

    protected $description = 'Dispatch pending scheduled campaign messages';

    public function handle(CampaignDispatchService $dispatchService): int
    {
        $limit = $this->option('limit') !== null ? (int) $this->option('limit') : null;
        $sent = $dispatchService->dispatchPendingBatch($limit);

        $this->info("Dispatched {$sent} campaign message(s).");

        return self::SUCCESS;
    }
}
