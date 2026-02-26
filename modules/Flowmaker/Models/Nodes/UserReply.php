<?php

namespace Modules\Flowmaker\Models\Nodes;

use App\Models\Company;
use Illuminate\Support\Facades\Log;
use Modules\Flowmaker\Models\Contact;
use Illuminate\Support\Facades\Http;

class UserReply extends Node
{
    public function listenForReply($message, $data){
       Log::info('Listening for reply in user reply node');

       $contact = Contact::find($data['contact_id']);
       $settings = $this->getDataAsArray()['settings'];
       
       // Store the user's reply in the specified variable
       $variableName = $settings['variableName'] ?? 'user_response';
       
       Log::info('Storing user reply in variable', [
           'variableName' => $variableName, 
           'reply' => $message,
           'contact_id' => $contact->id,
           'flow_id' => $this->flow_id
       ]);
       
       // Set the contact state with the user's reply
       $contact->setContactState($this->flow_id, $variableName, $message);
       
       //Clear the current node from the contact state
       Log::info("clear current node from contact state for contact ".$contact->id." and flow ".$this->flow_id);
       $contact->clearContactState($this->flow_id, 'current_node');
       $this->isStartNode = false;
       Log::info("current node cleared");

       // Move to the next node
       $nextNode = $this->getNextNodeId();
       if($nextNode != null){
            Log::info('Next node found, process it', ['next_node' => $nextNode->id]);
            $nextNode->process($message, $data);
        } else {
            Log::info('No next node found');
        }
    }

    public function process($message, $data)
    {
        Log::info('Processing message in user reply node', ['message' => $message, 'data' => $data]);
        
        if($this->isStartNode){
            //In this case we need to listen for a reply
            $this->listenForReply($message, $data);
            return [
                'success' => true
            ];
        }
        
        $contact = Contact::find($data['contact_id']);

        //Get settings
        $settings = $this->getDataAsArray()['settings'];

        //Process the question content with variables
        $question = $contact->changeVariables($settings['question'] ?? 'Please provide your response:', $this->flow_id);

        //Get the token from the company
        $company = Company::find($contact->company_id);
        $token = $company->getConfig('plain_token', '');

        // Prepare the message payload
        $payload = [
            'token' => $token,
            'phone' => $contact->phone,
            'message' => $question
        ];

        Log::info('Question message payload', ['payload' => $payload]);

        // Make the API call to send the question
        try {
            $response = Http::post(config('app.url').'/api/wpbox/sendmessage', $payload);
            Log::info('Question message API response', ['response' => $response->json()]);
            
            if (!$response->successful()) {
                Log::error('Failed to send question message', ['error' => $response->body()]);
            } else {
                //Set the user state to wait for response
                $contact->setContactState($this->flow_id, 'current_node', $this->id);
                Log::info('Set contact state to wait for user reply', ['node_id' => $this->id]);
            }
        } catch (\Exception $e) {
            Log::error('Error sending question message', ['error' => $e->getMessage()]);
        }

        return [
            'success' => true
        ];
    }

    protected function getNextNodeId($handleId = null)
    {
        // Find the first outgoing edge from this node
        foreach ($this->outgoingEdges as $edge) {
            return $edge->getTarget();
        }
        return null;
    }
}
