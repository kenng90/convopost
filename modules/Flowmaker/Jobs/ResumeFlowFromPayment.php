<?php

namespace Modules\Flowmaker\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Flowmaker\Models\Contact;
use Modules\Flowmaker\Models\Flow;

class ResumeFlowFromPayment implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $flowId,
        public int $contactId,
        public string $status = 'success',
    ) {
    }

    public function handle(): void
    {
        $flow = Flow::withoutGlobalScopes()->find($this->flowId);
        $contact = Contact::withoutGlobalScopes()->find($this->contactId);

        if (! $flow || ! $contact) {
            return;
        }

        $contact->setContactState($this->flowId, 'payment_result_status', $this->status);
        $flow->resumeFromPaymentCallback($contact, $this->status);
    }
}
