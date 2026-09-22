<?php

namespace Modules\Social\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Social\Models\SocialPost;
use Modules\Social\Services\SocialPostPublishService;

class PublishSocialPostJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [30, 120, 600];
    }

    public function __construct(public int $socialPostId)
    {
        $this->onQueue('social');
    }

    public function handle(SocialPostPublishService $publisher): void
    {
        $post = SocialPost::withoutGlobalScopes()->find($this->socialPostId);

        if (! $post) {
            return;
        }

        $publisher->publish($post);
    }
}
