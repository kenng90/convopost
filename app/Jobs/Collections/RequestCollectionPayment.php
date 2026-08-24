<?php

namespace App\Jobs\Collections;

use App\Services\Collections\CollectionEngine;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Invoice\Models\Invoice;

class RequestCollectionPayment implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public int $invoiceId,
        public ?string $channel = null,
    ) {
    }

    public function handle(CollectionEngine $engine): void
    {
        $invoice = Invoice::query()->find($this->invoiceId);
        if (! $invoice) {
            return;
        }

        $engine->initiate($invoice, $this->channel);
    }
}
