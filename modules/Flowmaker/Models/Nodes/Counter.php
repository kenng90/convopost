<?php

namespace Modules\Flowmaker\Models\Nodes;

use Illuminate\Support\Facades\Log;
use Modules\Flowmaker\Models\Contact;
use Modules\Flowmaker\Models\FlowExecution;

class Counter extends Node
{
    public function process($message, $data)
    {
        Log::info('Processing message in counter node', ['message' => $message, 'data' => $data]);

        // Get counter settings from node data
        $counterSettings = $this->getDataAsArray()['settings']['counter'] ?? [];
        $maxExecutions = $counterSettings['maxExecutions'] ?? 1;
        $period = $counterSettings['period'] ?? 'all_time';
        
        // Get contact from data
        $contactId = is_object($data) ? $data->contact_id : $data['contact_id'];
        $contact = Contact::find($contactId);
        
        if (!$contact) {
            Log::error('Contact not found in counter node', ['contactId' => $contactId]);
            return ['success' => false, 'error' => 'Contact not found'];
        }

        // Log this execution
        FlowExecution::logExecution($this->flow_id, $contactId, $this->id);
        Log::info('Logged flow execution', [
            'flow_id' => $this->flow_id,
            'contact_id' => $contactId,
            'node_id' => $this->id
        ]);

        // Count total executions for this contact and flow in the specified period
        $executionCount = FlowExecution::countExecutions(
            $this->flow_id, 
            $contactId, 
            $period,
            $this->id
        );
        
        Log::info('Execution count', [
            'count' => $executionCount,
            'maxExecutions' => $maxExecutions,
            'period' => $period,
            'contactId' => $contactId,
            'flowId' => $this->flow_id
        ]);

        // Set the execution count as a contact state variable
        $contact->setContactState($this->flow_id, 'num_executions', $executionCount);
        
        // Also set period-specific variable for reference
        $contact->setContactState($this->flow_id, "num_executions_{$period}", $executionCount);
        
        Log::info('Set contact state variables', [
            'num_executions' => $executionCount,
            "num_executions_{$period}" => $executionCount
        ]);

        // Determine if execution count is within the limit
        // TRUE: count <= maxExecutions (within limit)
        // FALSE: count > maxExecutions (exceeded limit)
        $withinLimit = $executionCount <= $maxExecutions;
        
        Log::info('Counter node result', [
            'executionCount' => $executionCount,
            'maxExecutions' => $maxExecutions,
            'withinLimit' => $withinLimit,
            'period' => $period
        ]);

        // Get the appropriate next node based on the result
        $nextNode = $this->getNextNodeId($withinLimit);
        if ($nextNode) {
            Log::info('Moving to next node', ['nextNodeId' => $nextNode->id]);
            $nextNode->process($message, $data);
        } else {
            Log::info('No next node found for counter result', ['withinLimit' => $withinLimit]);
        }

        return [
            'success' => true,
            'executionCount' => $executionCount,
            'maxExecutions' => $maxExecutions,
            'withinLimit' => $withinLimit,
            'period' => $period
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
            Log::info('Checking edge handle', [
                'sourceHandle' => $sourceHandle,
                'looking_for' => $handleId,
                'contains' => str_contains($sourceHandle, $handleId)
            ]);
            
            if (str_contains($sourceHandle, $handleId)) {
                return $edge->getTarget();
            }
        }
        
        Log::warning('No matching edge found for counter node', [
            'nodeId' => $this->id,
            'handleId' => $handleId,
            'availableEdges' => array_map(function($edge) {
                return $edge->getSourceHandle();
            }, $this->outgoingEdges)
        ]);
        
        return null;
    }
}