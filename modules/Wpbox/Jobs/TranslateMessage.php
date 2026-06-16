<?php

namespace Modules\Wpbox\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Wpbox\Models\Message;

class TranslateMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 420;

    public function __construct(
        public int $messageId,
    ) {
    }

    public function handle(): void
    {
        $message = Message::withoutGlobalScopes()->with('contact')->find($this->messageId);

        if ($message) {
            $message->doTranslation(true);
        }
    }
}
