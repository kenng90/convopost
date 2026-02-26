<?php

namespace Modules\Flowmaker\Models\Nodes;

use Illuminate\Support\Facades\Log;
use Modules\Flowmaker\Models\Contact;

class SetVariable extends Node
{
    
    public function process($message, $data)
    {
        Log::info('Processing message in SetVariable node', ['message' => $message, 'data' => $data]);
        
        try {
            // Get variable settings from node data
            $settings = $this->getDataAsArray()['settings'] ?? [];
            
            Log::info('SetVariable Settings', ['settings' => $settings]);

            // Find the contact
            $contactId = is_object($data) ? $data->contact_id : $data['contact_id'];
            $contact = Contact::find($contactId);
            
            if (!$contact) {
                Log::error('Contact not found', ['contactId' => $contactId]);
                return ['success' => false];
            }

            Log::info('Contact found', ['contact' => $contact->id]);

            // Extract variable name and value from settings
            $variableName = $settings['variableName'] ?? '';
            $variableValue = $settings['variableValue'] ?? '';

            if (empty($variableName)) {
                Log::error('Variable name is empty');
                return ['success' => false];
            }

            // Transform the variable value with existing variables (in case it references other variables)
            $transformedValue = $contact->changeVariables($variableValue, $this->flow_id);
            
            Log::info('Setting variable', [
                'variableName' => $variableName,
                'originalValue' => $variableValue,
                'transformedValue' => $transformedValue
            ]);

            // Set the contact state (variable)
            $contact->setContactState($this->flow_id, $variableName, $transformedValue);
            
            Log::info('Variable set successfully', [
                'variableName' => $variableName,
                'value' => $transformedValue
            ]);

        } catch (\Exception $e) {
            Log::error('Error processing SetVariable node', ['error' => $e->getMessage()]);
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
