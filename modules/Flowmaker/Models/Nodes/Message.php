<?php

namespace Modules\Flowmaker\Models\Nodes;
use Illuminate\Support\Facades\Log;
use Modules\Flowmaker\Models\Contact;

class Message extends Node
{
    
    public function process($message, $data)
    {
        Log::info('Processing message in message node', ['message' => $message, 'data' => $data]);
        // Get message from node data
        try{
            $message= $this->getDataAsArray()['settings']['message'];
            Log::info('Message', ['message' => $message]);

            //Find the contact
            $contactId = is_object($data) ? $data->contact_id : $data['contact_id'];
            $contact = Contact::find($contactId);
            Log::info('Contact', ['contact' => $contact]);

            //Transform the message
           $message = $contact->changeVariables($message, $this->flow_id);
           Log::info('Transformed message', ['message' => $message]);

            //Send the message
            $contact->sendMessage($message,false);


        }catch(\Exception $e){
            Log::error('Error getting message from node data', ['error' => $e->getMessage()]);
        }
    

        // Continue flow to next node if one exists
        $nextNode = $this->getNextNodeId();
        if ($nextNode) {
            $nextNode->process($message, $data);
        }

        return [
            'success' => true
        ];
    }

    protected function getNextNodeId( $data =null)
    {
        // Get the first outgoing edge's target
        if (!empty($this->outgoingEdges)) {
            return $this->outgoingEdges[0]->getTarget();
        }
        return null;
    }
}
