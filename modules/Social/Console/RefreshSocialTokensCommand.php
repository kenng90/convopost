<?php

namespace Modules\Social\Console;

use Illuminate\Console\Command;
use Modules\Social\Services\SocialTokenRefreshService;

class RefreshSocialTokensCommand extends Command
{
    protected $signature = 'social:refresh-tokens {--company= : Limit refresh to one company id}';

    protected $description = 'Refresh Social publishing tokens that are expired or near expiry';

    public function handle(SocialTokenRefreshService $refreshService): int
    {
        $companyId = $this->option('company');

        $stats = $refreshService->refreshDue(
            $companyId !== null && $companyId !== '' ? (int) $companyId : null
        );

        $this->info(sprintf(
            'Social tokens: %d refreshed, %d failed, %d skipped.',
            $stats['refreshed'],
            $stats['failed'],
            $stats['skipped'],
        ));

        return $stats['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
