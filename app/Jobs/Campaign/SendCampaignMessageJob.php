<?php

namespace App\Jobs\Campaign;

use App\Services\Campaign\CampaignDispatchService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Wpbox\Models\Message;

class SendCampaignMessageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public int $timeout = 120;

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [10, 30, 60, 120, 300];
    }

    public function __construct(public int $messageId)
    {
        $this->onQueue(config('wpbox.campaign_send_queue', 'campaigns'));
    }

    public function uniqueId(): string
    {
        return 'campaign-message-'.$this->messageId;
    }

    public function handle(CampaignDispatchService $dispatchService): void
    {
        $message = Message::withoutGlobalScopes()->find($this->messageId);

        if (! $message || (int) $message->status !== Message::STATUS_PENDING) {
            return;
        }

        $dispatchService->sendSynchronously($message);
    }
}
