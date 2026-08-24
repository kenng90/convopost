<?php

namespace App\Jobs\Collections;

use App\Services\Collections\CollectionEngine;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Invoice\Models\InvoicePayment;

class WatchStkTimeout implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(
        public int $paymentId,
    ) {
    }

    public function handle(CollectionEngine $engine): void
    {
        $payment = InvoicePayment::query()->find($this->paymentId);
        if (! $payment) {
            return;
        }

        $engine->timeoutPendingPayment($payment);
    }
}
