<?php

namespace Modules\Social\Console;

use Illuminate\Console\Command;
use Modules\Social\Jobs\SyncSocialAnalyticsJob;
use Modules\Social\Services\SocialAnalyticsSyncService;

class SyncSocialAnalyticsCommand extends Command
{
    protected $signature = 'social:sync-analytics
        {--company= : Limit sync to one company id}
        {--limit=100 : Max published post-accounts to sync}
        {--sync : Run inline instead of queueing}';

    protected $description = 'Pull reach and engagement snapshots for published social posts';

    public function handle(SocialAnalyticsSyncService $sync): int
    {
        $companyId = $this->option('company');
        $companyId = $companyId !== null && $companyId !== '' ? (int) $companyId : null;
        $limit = max(1, (int) $this->option('limit'));

        if ($this->option('sync')) {
            $stats = $sync->syncDue($companyId, $limit);
            $this->info(sprintf(
                'Social analytics: %d synced, %d failed, %d skipped.',
                $stats['synced'],
                $stats['failed'],
                $stats['skipped'],
            ));

            return $stats['failed'] > 0 ? self::FAILURE : self::SUCCESS;
        }

        SyncSocialAnalyticsJob::dispatch($companyId, $limit);
        $this->info('Queued social analytics sync on the social queue.');

        return self::SUCCESS;
    }
}
