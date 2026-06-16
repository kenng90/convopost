<?php

namespace Modules\Flowmaker\Listeners;

use App\Models\Company;
use Illuminate\Support\Collection;
use Modules\Flowmaker\Jobs\ProcessFlowMessage;
use Modules\Flowmaker\Models\Flow;

class RespondOnMessage
{
    public function handleMessageByContact($event)
    {
        try {
            $contact = $event->message->contact;
            $message = $event->message;
            if ($contact->enabled_ai_bot && ! $message->bot_has_replied) {
                $company_id = $contact->company_id;
                $company = Company::findOrFail($company_id);

                $flows = Flow::query()
                    ->where('company_id', $company_id)
                    ->whereNotNull('flow_data')
                    ->where('flow_data', '!=', '')
                    ->where('flow_data', '!=', '{}')
                    ->get(['id', 'name', 'company_id']);

                $flowsForChat = $this->filterFlowsForChat($company, $flows);

                foreach ($flowsForChat as $flow) {
                    ProcessFlowMessage::dispatch($flow->id, $message->id)->onQueue('flows');
                }
            }
        } catch (\Throwable $th) {
        }
    }

    /**
     * Chat runs all company flows except the one assigned to AI voice (knowledge-only there).
     * If that voice flow is the only flow, it is still used for chat so the bot does not go silent.
     *
     * @param  \Illuminate\Database\Eloquent\Collection<int, Flow>|Collection<int, Flow>  $flows
     * @return Collection<int, Flow>
     */
    public function filterFlowsForChat(Company $company, $flows): Collection
    {
        $voiceFlowId = (int) $company->getConfig('whatsapp_ai_flow_id', 0);
        if ($voiceFlowId <= 0) {
            return $flows instanceof Collection ? $flows : collect($flows);
        }

        $collection = $flows instanceof Collection ? $flows : collect($flows);
        $withoutVoice = $collection->reject(fn (Flow $flow) => (int) $flow->id === $voiceFlowId)->values();

        if ($withoutVoice->isEmpty()) {
            return $collection->values();
        }

        return $withoutVoice;
    }

    public function subscribe($events)
    {
        $events->listen(
            'Modules\Wpbox\Events\ContactReplies',
            [RespondOnMessage::class, 'handleMessageByContact']
        );
    }
}
