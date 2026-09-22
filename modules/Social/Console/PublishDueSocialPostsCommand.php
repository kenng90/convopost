<?php

namespace Modules\Social\Console;

use Illuminate\Console\Command;
use Modules\Social\Jobs\PublishSocialPostJob;
use Modules\Social\Models\SocialPost;

class PublishDueSocialPostsCommand extends Command
{
    protected $signature = 'social:publish-due {--limit=50 : Max posts to dispatch} {--sync : Run publish inline instead of queueing}';

    protected $description = 'Dispatch publish jobs for due scheduled social posts';

    public function handle(): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $sync = (bool) $this->option('sync');

        $posts = SocialPost::withoutGlobalScopes()
            ->where('status', 'scheduled')
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<=', now())
            ->orderBy('scheduled_at')
            ->limit($limit)
            ->get(['id']);

        if ($posts->isEmpty()) {
            $this->info('No due social posts to publish.');

            return self::SUCCESS;
        }

        foreach ($posts as $post) {
            if ($sync) {
                PublishSocialPostJob::dispatchSync($post->id);
            } else {
                PublishSocialPostJob::dispatch($post->id);
            }
        }

        $this->info(sprintf(
            'Dispatched %d social post(s) for publishing%s.',
            $posts->count(),
            $sync ? ' (sync)' : ''
        ));

        return self::SUCCESS;
    }
}
