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
    public function selectFlowsForMessage(
        Company $company,
        $flows,
        Contact $contact,
        string $messageBody,
        ?string $channel = null,
    ): Collection {
        $candidates = $this->filterFlowsForChat($company, $flows)
            ->filter(fn (Flow $flow) => (bool) ($flow->is_active ?? true))
            ->values();

        if ($channel) {
            $candidates = $candidates
                ->filter(fn (Flow $flow) => $this->flowSupportsChannel($flow, $channel))
                ->values();
        }

        if ($candidates->isEmpty()) {
            return collect();
        }

        $candidateIds = $candidates->pluck('id')->map(fn ($id) => (int) $id)->all();

        $sorted = $candidates->sortBy([
            ['priority', 'desc'],
            ['id', 'asc'],
        ])->values();

        // Exclusive keywords (e.g. "book") must be allowed to interrupt a stuck
        // session on another flow — otherwise a waiting quick-replies/menu node
        // permanently traps the contact and booking forms never send.
        $exclusiveMatch = $sorted->first(function (Flow $flow) use ($messageBody) {
            return (bool) ($flow->exclusive_on_match ?? false)
                && $flow->matchesKeywordMessage($messageBody);
        });

        if ($exclusiveMatch) {
            $this->clearOtherFlowSessions($contact, (int) $exclusiveMatch->id, $candidateIds);

            return collect([$exclusiveMatch]);
        }

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

        return $sorted;
    }

    /**
     * Drop waiting-state locks on other flows when an exclusive keyword takes over.
     *
     * @param  list<int>  $candidateIds
     */
    private function clearOtherFlowSessions(Contact $contact, int $winningFlowId, array $candidateIds): void
    {
        $otherIds = array_values(array_filter(
            $candidateIds,
            fn (int $id) => $id !== $winningFlowId
        ));

        if ($otherIds === []) {
            return;
        }

        ContactState::query()
            ->where('contact_id', $contact->id)
            ->where('state', 'current_node')
            ->whereIn('flow_id', $otherIds)
            ->delete();
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

    private function flowSupportsChannel(Flow $flow, string $channel): bool
    {
        $data = json_decode((string) ($flow->flow_data ?? '{}'), true) ?: [];
        $supported = $data['supported_channels'] ?? $data['meta']['supported_channels'] ?? null;

        if (! is_array($supported) || $supported === []) {
            return true;
        }

        return in_array($channel, $supported, true);
    }
}
