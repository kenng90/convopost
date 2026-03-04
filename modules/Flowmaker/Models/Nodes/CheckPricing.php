<?php

namespace Modules\Flowmaker\Models\Nodes;

use Illuminate\Support\Facades\Log;
use Modules\Flowmaker\Models\Contact;

class CheckPricing extends Node
{
    public function process($message, $data)
    {
        Log::info('Processing message in check pricing node', ['message' => $message, 'data' => $data]);

        // Get pricing settings from node data
        $pricingSettings = $this->getDataAsArray()['settings']['pricing'] ?? [];
        $freeExecutions = $pricingSettings['freeExecutions'] ?? 0;
        
        // Get contact from data
        $contactId = is_object($data) ? $data->contact_id : $data['contact_id'];
        $contact = Contact::find($contactId);
        
        if (!$contact) {
            Log::error('Contact not found in check pricing node', ['contactId' => $contactId]);
            return ['success' => false, 'error' => 'Contact not found'];
        }

        $currentCredits = $contact->credits ?? 0;
        $minimumCredits = -$freeExecutions; // Allow negative credits up to free executions
        
        Log::info('Check pricing node evaluation', [
            'contactId' => $contactId,
            'currentCredits' => $currentCredits,
            'freeExecutions' => $freeExecutions,
            'minimumCredits' => $minimumCredits,
            'nodeId' => $this->id
        ]);

        // Check if user has enough credits (including free executions)
        $hasEnoughCredits = $currentCredits > $minimumCredits;
        
        if ($hasEnoughCredits) {
            // Deduct 1 credit
            $newCredits = $currentCredits - 1;
            $contact->credits = $newCredits;
            $contact->save();
            
            Log::info('Credit deducted', [
                'contactId' => $contactId,
                'previousCredits' => $currentCredits,
                'newCredits' => $newCredits,
                'deducted' => 1
            ]);

            // Set contact state with current credit info
            $contact->setContactState($this->flow_id, 'user_credits', $newCredits);
            $contact->setContactState($this->flow_id, 'credits_sufficient', 'true');
            
            Log::info('Check pricing result: TRUE (sufficient credits)', [
                'contactId' => $contactId,
                'creditsAfterDeduction' => $newCredits,
                'minimumRequired' => $minimumCredits
            ]);
        } else {
            // Not enough credits
            Log::info('Check pricing result: FALSE (insufficient credits)', [
                'contactId' => $contactId,
                'currentCredits' => $currentCredits,
                'minimumRequired' => $minimumCredits,
                'shortage' => $minimumCredits - $currentCredits
            ]);

            // Set contact state indicating insufficient credits
            $contact->setContactState($this->flow_id, 'user_credits', $currentCredits);
            $contact->setContactState($this->flow_id, 'credits_sufficient', 'false');
        }

        // Get the appropriate next node based on the result
        $nextNode = $this->getNextNodeId($hasEnoughCredits);
        if ($nextNode) {
            Log::info('Moving to next node', ['nextNodeId' => $nextNode->id, 'path' => $hasEnoughCredits ? 'TRUE' : 'FALSE']);
            $nextNode->process($message, $data);
        } else {
            Log::info('No next node found for pricing check result', ['hasEnoughCredits' => $hasEnoughCredits]);
        }

        return [
            'success' => true,
            'hasEnoughCredits' => $hasEnoughCredits,
            'creditsAfterCheck' => $hasEnoughCredits ? $currentCredits - 1 : $currentCredits,
            'freeExecutions' => $freeExecutions,
            'minimumCredits' => $minimumCredits
        ];
    }

    /**
     * Get the next node based on true/false result
     */
    protected function getNextNodeId($isTrue = null)
    {
        // Find the edge that connects from this node based on true/false result
        $handleId = $isTrue ? 'true' : 'false';
        
        foreach ($this->outgoingEdges as $edge) {
            $sourceHandle = $edge->getSourceHandle();
            Log::info('Checking edge handle in pricing node', [
                'sourceHandle' => $sourceHandle,
                'looking_for' => $handleId,
                'contains' => str_contains($sourceHandle, $handleId)
            ]);
            
            if (str_contains($sourceHandle, $handleId)) {
                return $edge->getTarget();
            }
        }
        
        Log::warning('No matching edge found for pricing node', [
            'nodeId' => $this->id,
            'handleId' => $handleId,
            'availableEdges' => array_map(function($edge) {
                return $edge->getSourceHandle();
            }, $this->outgoingEdges)
        ]);
        
        return null;
    }
}