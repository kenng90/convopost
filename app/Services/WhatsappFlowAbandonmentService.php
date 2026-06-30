<?php

namespace App\Services;

use App\Models\WhatsappFlowResponse;
use Illuminate\Support\Facades\Log;
use Modules\Flowmaker\Models\Contact;
use Modules\Flowmaker\Models\Flow;

class WhatsappFlowAbandonmentService
{
    private int $timeoutHours;

    public function __construct()
    {
        $this->timeoutHours = (int) config('whatsapp-flows.abandonment_timeout_hours', 24);
    }

    /**
     * @return array{marked: int, resumed: int}
     */
    public function processAbandonedResponses(): array
    {
        $cutoff = now()->subHours($this->timeoutHours);
        $marked = 0;
        $resumed = 0;

        WhatsappFlowResponse::query()
            ->where('status', 'pending')
            ->whereNotNull('sent_at')
            ->where('sent_at', '<', $cutoff)
            ->orderBy('id')
            ->chunkById(100, function ($responses) use (&$marked, &$resumed) {
                foreach ($responses as $flowResponse) {
                    $flowResponse->markAbandoned('Timed out after '.$this->timeoutHours.' hours without completion');
                    $marked++;

                    if ($this->tryResumeAbandonedAutomation($flowResponse)) {
                        $resumed++;
                    }
                }
            });

        Log::info('WhatsApp Form abandonment processed', [
            'marked' => $marked,
            'resumed' => $resumed,
            'timeout_hours' => $this->timeoutHours,
        ]);

        return ['marked' => $marked, 'resumed' => $resumed];
    }

    private function tryResumeAbandonedAutomation(WhatsappFlowResponse $flowResponse): bool
    {
        if (! $flowResponse->flow_id || ! $flowResponse->flow_node_id || ! $flowResponse->contact_id) {
            return false;
        }

        $contact = Contact::find($flowResponse->contact_id);
        if (! $contact) {
            return false;
        }

        $currentNode = $contact->getContactStateValue($flowResponse->flow_id, 'current_node');
        if ($currentNode !== $flowResponse->flow_node_id) {
            return false;
        }

        $automationFlow = Flow::find($flowResponse->flow_id);
        if (! $automationFlow) {
            return false;
        }

        return $automationFlow->resumeFromFormAbandonment($contact, $flowResponse->flow_node_id);
    }
}
