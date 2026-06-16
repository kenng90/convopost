<?php

namespace Modules\Flowmaker\Models\Nodes;

use Illuminate\Support\Facades\Log;
use Modules\Flowmaker\Models\Contact;
use Modules\Journies\Models\JourneyStage;
use Modules\Journies\Services\JourneyContactService;

class AssignJourneyStage extends Node
{
    public function process($message, $data)
    {
        Log::info('Processing AssignJourneyStage node', ['data' => $data]);

        try {
            $settings = $this->getDataAsArray()['settings'] ?? [];
            $stageId = $settings['stageId'] ?? null;

            if (empty($stageId) || $stageId === 'none') {
                return ['success' => true];
            }

            $contactId = is_object($data) ? $data->contact_id : $data['contact_id'];
            $contact = Contact::find($contactId);

            if (! $contact) {
                Log::error('AssignJourneyStage contact not found', ['contactId' => $contactId]);

                return ['success' => false];
            }

            $stage = JourneyStage::query()
                ->where('id', $stageId)
                ->whereHas('journey', fn ($query) => $query->where('company_id', $contact->company_id))
                ->first();

            if (! $stage) {
                Log::error('AssignJourneyStage stage not found', ['stageId' => $stageId]);

                return ['success' => false];
            }

            app(JourneyContactService::class)->moveContactToStage(
                $contact,
                $stage,
                'flow',
                null,
                true,
                false,
            );
        } catch (\Exception $e) {
            Log::error('Error processing AssignJourneyStage node', ['error' => $e->getMessage()]);

            return ['success' => false];
        }

        $nextNode = $this->getNextNodeId();
        if ($nextNode) {
            $nextNode->process($message, $data);
        }

        return ['success' => true];
    }

    protected function getNextNodeId($data = null)
    {
        if (! empty($this->outgoingEdges)) {
            return $this->outgoingEdges[0]->getTarget();
        }

        return null;
    }
}
