<?php

namespace Modules\Flowmaker\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Flowmaker\Models\Flow;
use Modules\Wpbox\Models\Message;

class ProcessFlowMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 120;

    public function __construct(
        public int $flowId,
        public int $messageId,
    ) {
    }

    public function handle(): void
    {
        $flow = Flow::withoutGlobalScopes()->find($this->flowId);
        $message = Message::withoutGlobalScopes()->with('contact')->find($this->messageId);

        if ($flow && $message) {
            $flow->processMessage($message);
        }
    }
}
