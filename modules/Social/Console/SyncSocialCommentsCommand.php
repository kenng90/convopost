<?php

namespace Modules\Social\Console;

use Illuminate\Console\Command;
use Modules\Social\Jobs\SyncSocialCommentsJob;
use Modules\Social\Services\SocialCommentSyncService;

class SyncSocialCommentsCommand extends Command
{
    protected $signature = 'social:sync-comments
        {--company= : Limit sync to one company id}
        {--limit=50 : Max published post-accounts to sync}
        {--sync : Run inline instead of queueing}';

    protected $description = 'Pull Facebook/Instagram comments for published social posts (read-only)';

    public function handle(SocialCommentSyncService $sync): int
    {
        $companyId = $this->option('company');
        $companyId = $companyId !== null && $companyId !== '' ? (int) $companyId : null;
        $limit = max(1, (int) $this->option('limit'));

        if ($this->option('sync')) {
            $stats = $sync->syncDue($companyId, $limit);
            $this->info(sprintf(
                'Social comments: %d posts synced, %d comments upserted, %d failed, %d skipped.',
                $stats['synced'],
                $stats['upserted'],
                $stats['failed'],
                $stats['skipped'],
            ));

            return $stats['failed'] > 0 ? self::FAILURE : self::SUCCESS;
        }

        SyncSocialCommentsJob::dispatch($companyId, $limit);
        $this->info('Queued social comment sync on the social queue.');

        return self::SUCCESS;
    }
}
