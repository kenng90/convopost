<?php

namespace Modules\Social\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Social\Services\SocialCommentSyncService;

class SyncSocialCommentsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly ?int $companyId = null,
        public readonly int $limit = 50,
    ) {
        $this->onQueue('social');
    }

    public function handle(SocialCommentSyncService $sync): void
    {
        $sync->syncDue($this->companyId, $this->limit);
    }
}
