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
            // Get agent settings from node data
            $settings = $this->getDataAsArray()['settings'] ?? [];
            
            Log::info('AssignAgent Settings', ['settings' => $settings]);

            // Find the contact
            $contactId = is_object($data) ? $data->contact_id : $data['contact_id'];
            $contact = Contact::find($contactId);
            
            if (!$contact) {
                Log::error('Contact not found', ['contactId' => $contactId]);
                return ['success' => false];
            }

            Log::info('Contact found', ['contact' => $contact->id]);

            // Extract agent ID from settings
            $agentId = $settings['agentId'] ?? null;

            if (empty($agentId) || $agentId === 'none') {
                Log::info('No agent selected or agent set to none, removing assignment');
                $contact->user_id = null;
            } else {
                // Verify the agent exists and belongs to the same company
                $agent = \App\Models\User::role('staff')
                    ->where('id', $agentId)
                    ->where('company_id', $contact->company_id)
                    ->first();
                
                if (!$agent) {
                    Log::error('Agent not found or does not belong to the same company', [
                        'agentId' => $agentId, 
                        'companyId' => $contact->company_id
                    ]);
                    return ['success' => false];
                }

                Log::info('Assigning contact to agent', [
                    'contactId' => $contact->id,
                    'agentId' => $agentId,
                    'agentName' => $agent->name
                ]);

                $contact->user_id = $agentId;
            }
            
            $contact->save();
            
            Log::info('Contact agent assignment updated successfully', [
                'contactId' => $contact->id,
                'newAgentId' => $contact->user_id
            ]);

        } catch (\Exception $e) {
            Log::error('Error processing AssignAgent node', ['error' => $e->getMessage()]);
            return ['success' => false];
        }

        // Continue flow to next node if one exists
        $nextNode = $this->getNextNodeId();
        if ($nextNode) {
            $nextNode->process($message, $data);
        }

        return ['success' => true];
    }

    protected function getNextNodeId($data = null)
    {
        // Get the first outgoing edge's target
        if (!empty($this->outgoingEdges)) {
            return $this->outgoingEdges[0]->getTarget();
        }
        return null;
    }
} 