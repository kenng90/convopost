<?php

namespace Modules\Flowmaker\Models\Nodes;

use Illuminate\Support\Facades\Log;
use Modules\Flowmaker\Models\Contact;

class End extends Node
{
    public function process($message, $data)
    {
        Log::info('Processing message in end node, clearing contact state');
        $contactId = is_object($data) ? $data->contact_id : ($data['contact_id'] ?? null);
        $contact = Contact::find($contactId);

        if (! $contact) {
            Log::error('Contact not found in end node', ['contactId' => $contactId]);

            return ['success' => false];
        }

        $contact->clearAllContactState($this->flow_id);
        Log::info('Contact state cleared');

        return [
            'success' => true,
        ];
    }

    protected function getNextNodeId($param = null)
    {
        // End node has no next node to process
        return null;
    }
}
