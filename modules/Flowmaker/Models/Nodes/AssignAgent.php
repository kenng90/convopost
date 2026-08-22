<?php

namespace Modules\Flowmaker\Models\Nodes;

use Illuminate\Support\Facades\Log;
use Modules\Flowmaker\Models\Contact;

class AssignAgent extends Node
{
    public function process($message, $data)
    {
        Log::info('Processing message in AssignAgent node', ['message' => $message, 'data' => $data]);

        try {
            $settings = $this->getDataAsArray()['settings'] ?? [];
            $contactId = is_object($data) ? $data->contact_id : $data['contact_id'];
            $contact = Contact::find($contactId);

            if (! $contact) {
                Log::error('Contact not found', ['contactId' => $contactId]);

                return ['success' => false];
            }

            $agentId = $settings['agentId'] ?? null;

            if (empty($agentId) || $agentId === 'none') {
                $contact->user_id = null;
            } else {
                $agent = \App\Models\User::role('staff')
                    ->where('id', $agentId)
                    ->where('company_id', $contact->company_id)
                    ->first();

                if (! $agent) {
                    Log::error('Agent not found or does not belong to the same company', [
                        'agentId' => $agentId,
                        'companyId' => $contact->company_id,
                    ]);

                    return ['success' => false];
                }

                $contact->user_id = $agentId;
                $contact->enabled_ai_bot = false;
            }

            $contact->save();
        } catch (\Exception $e) {
            Log::error('Error processing AssignAgent node', ['error' => $e->getMessage()]);

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
