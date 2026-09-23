<?php

namespace Modules\Social\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Social\Services\SocialAnalyticsSyncService;

class SyncSocialAnalyticsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly ?int $companyId = null,
        public readonly int $limit = 100,
    ) {
        $this->onQueue('social');
    }

    public function handle(SocialAnalyticsSyncService $sync): void
    {
        $sync->syncDue($this->companyId, $this->limit);
    }
}
