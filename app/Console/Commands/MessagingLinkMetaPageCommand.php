<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Services\Messaging\MetaPageLinkService;
use Illuminate\Console\Command;

class MessagingLinkMetaPageCommand extends Command
{
    protected $signature = 'messaging:link-meta-page
                            {company : Company ID that should receive these DMs}
                            {--page= : Facebook Page ID from the webhook (recipient / page id)}
                            {--instagram= : Instagram professional account ID from the webhook (entry.id)}';

    protected $description = 'Attach a Facebook Page + Instagram account to a company so Meta DMs appear in the inbox';

    public function handle(MetaPageLinkService $linker): int
    {
        $company = Company::find($this->argument('company'));

        if (! $company) {
            $this->error('Company not found.');

            return self::FAILURE;
        }

        $pageId = (string) $this->option('page');
        $instagramId = (string) $this->option('instagram');

        if ($pageId === '') {
            $this->error('Pass --page= with the Facebook Page ID from messaging.webhook.unmatched_asset.');

            return self::FAILURE;
        }

        $result = $linker->link($company, $pageId, $instagramId !== '' ? $instagramId : null);

        if (! ($result['ok'] ?? false)) {
            $this->error($result['error'] ?? 'Failed to link Page.');

            return self::FAILURE;
        }

        $this->info('Linked Meta messaging assets for company #'.$company->id.($company->name ? ' ('.$company->name.')' : ''));
        $this->line('Page ID: '.$result['page_id']);
        $this->line('Instagram account ID: '.($result['instagram_account_id'] ?: '(none returned by Graph)'));
        if ($result['page_name']) {
            $this->line('Page name: '.$result['page_name']);
        }

        $this->comment('Send another Instagram/Messenger DM. You should see messaging.webhook.message_processed in laravel.log.');

        return self::SUCCESS;
    }
}
