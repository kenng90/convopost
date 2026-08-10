<?php

namespace Modules\Flowmaker\Models\Nodes;

use Illuminate\Support\Facades\Log;
use Modules\Flowmaker\Models\Contact;

class Message extends Node
{
    public function process($message, $data)
    {
        Log::info('Processing message in message node', ['message' => $message, 'data' => $data]);

        try {
            $message = $this->getDataAsArray()['settings']['message'];
            Log::info('Message', ['message' => $message]);

            $contactId = is_object($data) ? $data->contact_id : $data['contact_id'];
            $contact = Contact::find($contactId);
            Log::info('Contact', ['contact' => $contact]);

            $message = $contact->changeVariables($message, $this->flow_id);
            Log::info('Transformed message', ['message' => $message]);

            $sent = $contact->sendMessage($message, false, false, 'TEXT', null, null, null, true);

            if ($this->outboundSendFailed($sent)) {
                Log::error('Flow message node failed to send outbound message', [
                    'flow_id' => $this->flow_id,
                    'node_id' => $this->id,
                    'contact_id' => $contact->id,
                    'message_id' => $sent->id ?? null,
                    'status' => $sent->status ?? null,
                    'error' => $sent->error ?? null,
                ]);

                return [
                    'success' => false,
                    'error' => $sent->error ?? __('Send failed'),
                ];
            }
        } catch (\Exception $e) {
            Log::error('Error getting message from node data', ['error' => $e->getMessage()]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }

        // Continue flow to next node only after a successful send
        $nextNode = $this->getNextNodeId();
        if ($nextNode) {
            $nextNode->process($message, $data);
        }

        return [
            'success' => true,
        ];
    }

    /**
     * WhatsApp keeps status=1 until delivery webhooks; Meta sets status=2 on success.
     * Credit blocks historically use status=2 with an error string. Hard failures use status=5.
     */
    private function outboundSendFailed($sent): bool
    {
        if (! $sent) {
            return true;
        }

        $status = (int) $sent->status;

        if ($status === 5) {
            return true;
        }

        return $status === 2 && filled($sent->error);
    }

    protected function getNextNodeId($data = null)
    {
        // Get the first outgoing edge's target
        if (! empty($this->outgoingEdges)) {
            return $this->outgoingEdges[0]->getTarget();
        }

        return null;
    }
}
