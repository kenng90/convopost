<?php

namespace App\Console\Commands;

use App\Services\Campaign\CampaignCounterService;
use Illuminate\Console\Command;

class FlushCampaignCounters extends Command
{
    protected $signature = 'campaigns:flush-counters';

    protected $description = 'Flush buffered campaign send counters to the database';

    public function handle(CampaignCounterService $counters): int
    {
        $flushed = $counters->flushAll();
        $this->info("Flushed counters for {$flushed} campaign(s).");

        return self::SUCCESS;
    }
}
