<?php

namespace App\Console\Commands;

use App\Services\Billing\SyncCreditActions;
use Illuminate\Console\Command;

class SyncCreditActionsCommand extends Command
{
    protected $signature = 'credits:sync-actions {--force : Update metadata on existing actions}';

    protected $description = 'Register credit billable actions from config and modules';

    public function handle(SyncCreditActions $sync): int
    {
        $created = $sync->sync(onlyMissing: ! $this->option('force'));

        $this->info(sprintf('Credit actions synced. %d new action(s) registered.', $created));

        return self::SUCCESS;
    }
}
