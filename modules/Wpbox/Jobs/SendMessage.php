<?php

namespace Modules\Wpbox\Jobs;

use App\Jobs\Campaign\SendCampaignMessageJob;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Wpbox\Models\Message;

class SendMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(protected Message $message)
    {
    }

    public function handle(): void
    {
        SendCampaignMessageJob::dispatch($this->message->id);
    }
}
