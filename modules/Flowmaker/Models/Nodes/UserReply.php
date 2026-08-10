<?php

namespace Modules\Flowmaker\Models\Nodes;

use App\Services\Flowmaker\FlowOutboundService;
use Illuminate\Support\Facades\Log;
use Modules\Flowmaker\Models\Contact;

class UserReply extends Node
{
    public function listenForReply($message, $data)
    {
        Log::info('Listening for reply in user reply node');

        $contactId = is_object($data) ? $data->contact_id : $data['contact_id'];
        $contact = Contact::find($contactId);
        $settings = $this->getDataAsArray()['settings'];

        $variableName = $settings['variableName'] ?? 'user_response';

        Log::info('Storing user reply in variable', [
            'variableName' => $variableName,
            'reply' => $message,
            'contact_id' => $contact->id,
            'flow_id' => $this->flow_id,
        ]);

        $contact->setContactState($this->flow_id, $variableName, $message);

        Log::info('clear current node from contact state for contact '.$contact->id.' and flow '.$this->flow_id);
        $contact->clearContactState($this->flow_id, 'current_node');
        $this->isStartNode = false;

        $nextNode = $this->getNextNodeId();
        if ($nextNode != null) {
            Log::info('Next node found, process it', ['next_node' => $nextNode->id]);
            $nextNode->process($message, $data);
        } else {
            Log::info('No next node found');
        }
    }

    public function process($message, $data)
    {
        Log::info('Processing message in user reply node', ['message' => $message, 'data' => $data]);

        if ($this->isStartNode) {
            $this->listenForReply($message, $data);

            return [
                'success' => true,
            ];
        }

        $contactId = is_object($data) ? $data->contact_id : $data['contact_id'];
        $contact = Contact::find($contactId);

        $settings = $this->getDataAsArray()['settings'];
        $question = $contact->changeVariables($settings['question'] ?? 'Please provide your response:', $this->flow_id);

        $contact->setContactState($this->flow_id, 'current_node', $this->id);

        $sent = app(FlowOutboundService::class)->sendText($contact, $question);

        if ($sent && (int) $sent->status === 5) {
            $error = strtolower((string) ($sent->error ?? ''));
            if (str_contains($error, 'window')) {
                $contact->clearContactState($this->flow_id, 'current_node');
            }
        }

        return [
            'success' => true,
        ];
    }

    protected function getNextNodeId($handleId = null)
    {
        foreach ($this->outgoingEdges as $edge) {
            return $edge->getTarget();
        }

        return null;
    }
}
