<?php

namespace App\Console\Commands\Collections;

use App\Enums\CollectionStatus;
use App\Services\Collections\CollectionEngine;
use Illuminate\Console\Command;
use Modules\Invoice\Models\Invoice;

class AdvanceCollectionsCommand extends Command
{
    protected $signature = 'collections:advance';

    protected $description = 'Advance due invoice collection retries, Paystack fallbacks, and WhatsApp chases';

    public function handle(CollectionEngine $engine): int
    {
        $query = Invoice::query()
            ->whereIn('collection_status', CollectionStatus::open())
            ->whereNotNull('next_chase_at')
            ->where('next_chase_at', '<=', now())
            ->orderBy('next_chase_at')
            ->limit(200);

        $count = 0;
        $query->each(function (Invoice $invoice) use ($engine, &$count) {
            $engine->advance($invoice);
            $count++;
        });

        $this->info("Advanced {$count} collection(s).");

        return self::SUCCESS;
    }
}
