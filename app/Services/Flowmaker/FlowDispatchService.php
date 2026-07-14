<?php

namespace App\Services\Flowmaker;

use App\Models\Company;
use Illuminate\Support\Collection;
use Modules\Flowmaker\Models\Contact;
use Modules\Flowmaker\Models\ContactState;
use Modules\Flowmaker\Models\Flow;

class FlowDispatchService
{
    /**
     * Decide which company flows should process this inbound message.
     *
     * @param  Collection<int, Flow>|\Illuminate\Database\Eloquent\Collection<int, Flow>  $flows
     * @return Collection<int, Flow>
     */
    public function selectFlowsForMessage(Company $company, $flows, Contact $contact, string $messageBody): Collection
    {
        $candidates = $this->filterFlowsForChat($company, $flows)
            ->filter(fn (Flow $flow) => (bool) ($flow->is_active ?? true))
            ->values();

        if ($candidates->isEmpty()) {
            return collect();
        }

        $candidateIds = $candidates->pluck('id')->map(fn ($id) => (int) $id)->all();

        $sessionFlowIds = ContactState::query()
            ->where('contact_id', $contact->id)
            ->where('state', 'current_node')
            ->whereNotNull('value')
            ->where('value', '!=', '')
            ->whereIn('flow_id', $candidateIds)
            ->pluck('flow_id')
            ->unique()
            ->values();

        if ($sessionFlowIds->count() === 1) {
            $sessionId = (int) $sessionFlowIds->first();

            return $candidates->filter(fn (Flow $flow) => (int) $flow->id === $sessionId)->values();
        }

        $sorted = $candidates->sortBy([
            ['priority', 'desc'],
            ['id', 'asc'],
        ])->values();

        $exclusiveMatch = $sorted->first(function (Flow $flow) use ($messageBody) {
            return (bool) ($flow->exclusive_on_match ?? false)
                && $flow->matchesKeywordMessage($messageBody);
        });

        if ($exclusiveMatch) {
            return collect([$exclusiveMatch]);
        }

        return $sorted;
    }

    /**
     * @param  Collection<int, Flow>|\Illuminate\Database\Eloquent\Collection<int, Flow>  $flows
     * @return Collection<int, Flow>
     */
    public function filterFlowsForChat(Company $company, $flows): Collection
    {
        $voiceFlowId = (int) $company->getConfig('whatsapp_ai_flow_id', 0);
        $collection = $flows instanceof Collection ? $flows : collect($flows);

        if ($voiceFlowId <= 0) {
            return $collection->values();
        }

        $withoutVoice = $collection->reject(fn (Flow $flow) => (int) $flow->id === $voiceFlowId)->values();

        if ($withoutVoice->isEmpty()) {
            return $collection->values();
        }

        return $withoutVoice;
    }
}
