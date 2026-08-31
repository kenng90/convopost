<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Modules\Tiktok\Messaging\TiktokTokenRefreshService;

class RefreshTiktokTokensCommand extends Command
{
    protected $signature = 'tiktok:refresh-tokens {--company= : Limit refresh to one company id}';

    protected $description = 'Refresh TikTok Business Messaging access tokens that expire within four hours';

    public function handle(TiktokTokenRefreshService $refreshService): int
    {
        $companyId = $this->option('company');

        $stats = $refreshService->refreshDue($companyId !== null && $companyId !== '' ? (int) $companyId : null);

        $this->info(sprintf(
            'TikTok tokens: %d refreshed, %d failed, %d skipped.',
            $stats['refreshed'],
            $stats['failed'],
            $stats['skipped'],
        ));

        return $stats['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
