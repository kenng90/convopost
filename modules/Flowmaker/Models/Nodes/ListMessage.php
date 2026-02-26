<?php

namespace Modules\Flowmaker\Models\Nodes;

use App\Models\Company;
use Illuminate\Support\Facades\Log;
use Modules\Flowmaker\Models\Contact;
use Illuminate\Support\Facades\Http;

class ListMessage extends Node
{
    public function listenForReply($message, $data){
        Log::info('Listening for reply in list message node');

        // Get extra data
        $extraData = $data->extra;
        $node = null;
        $elseNode = $this->getNextNodeId("else");
        
        if($extraData != null && $extraData != ''){
            Log::info('Extra data found', ['extraData' => $extraData]);

            $settings = $this->getDataAsArray()['settings'];
            $sections = $settings['sections'] ?? [];

            // Check if the extra data matches any of the list options
            foreach($sections as $section){
                $rows = $section['rows'] ?? [];
                foreach($rows as $row){
                    $listItemId = "{$section['id']}-{$row['id']}_id{$this->id}_flow{$this->flow_id}";
                    if($listItemId == $extraData){
                        Log::info('List item ID found', ['listItemId' => $listItemId]);

                        // Get the handle ID for the connection
                        $handleId = "{$section['id']}-{$row['id']}";
                        Log::info('Handle ID', ['handleId' => $handleId]);

                        // Get node with handle
                        $node = $this->getNextNodeId($handleId);
                        if($node != null){
                            Log::info('Next node found', ['node' => $node]);
                        } else {
                            Log::info('Node not found, go with else case');
                            $node = $elseNode;
                        }
                        break 2; // Break out of both loops
                    }
                }
            }
        } else {
            Log::info('No extra data found');
        }

        // Clear the current node from the contact state
        $contact = Contact::find($data['contact_id']);
        Log::info("Clear current node from contact state for contact ".$contact->id." and flow ".$this->flow_id);
        $contact->clearContactState($this->flow_id, 'current_node');
        Log::info("Current node cleared");

        if($node != null){
            Log::info('Node found, process it');
            $node->process($message, $data);
        } else if($elseNode != null){
            Log::info('Node not found, go with else case');
            $elseNode->process($message, $data);
        } else {
            Log::info('Node not found, Else node not found');
        }
    }

    public function process($message, $data)
    {
        Log::info('Processing message in list message node', ['message' => $message, 'data' => $data]);
        
        if($this->isStartNode){
            // In this case we need to listen for a reply
            $this->listenForReply($message, $data);
            return [
                'success' => true
            ];
        }
        
        $contact = Contact::find($data['contact_id']);

        // Get settings
        $settings = $this->getDataAsArray()['settings'];

        // Process the message content with variables
        $header = $contact->changeVariables($settings['header'] ?? '');
        $body = $contact->changeVariables($settings['body'] ?? '');
        $footer = $contact->changeVariables($settings['footer'] ?? '');
        $buttonText = $contact->changeVariables($settings['buttonText'] ?? 'Choose an option');

        // Get the token from the company
        $company = Company::find($contact->company_id);
        $token = $company->getConfig('plain_token', '');

        // Prepare the sections and rows
        $sections = [];
        $settingSections = $settings['sections'] ?? [];

        foreach($settingSections as $section){
            $rows = [];
            $sectionRows = $section['rows'] ?? [];
            
            foreach($sectionRows as $row){
                $rows[] = [
                    'id' => "{$section['id']}-{$row['id']}_id{$this->id}_flow{$this->flow_id}",
                    'title' => $contact->changeVariables($row['title'] ?? ''),
                    'description' => $contact->changeVariables($row['description'] ?? '')
                ];
            }

            $sections[] = [
                'title' => $contact->changeVariables($section['title'] ?? ''),
                'rows' => $rows
            ];
        }

        // Prepare the payload
        $payload = [
            'token' => $token,
            'phone' => $contact->phone,
            'message' => $body,
            'header' => $header,
            'footer' => $footer,
            'action' => [
                'button' => $buttonText,
                'sections' => $sections
            ]
        ];

        Log::info('List message payload', ['payload' => $payload]);

        // Make the API call
        try {
            $response = Http::post(config('app.url').'/api/wpbox/sendlistmessage', $payload);
            Log::info('List message API response', ['response' => $response->json()]);
            
            if (!$response->successful()) {
                Log::error('Failed to send list message', ['error' => $response->body()]);
            } else {
                // Set the user state
                $contact->setContactState($this->flow_id, 'current_node', $this->id);
            }
        } catch (\Exception $e) {
            Log::error('Error sending list message', ['error' => $e->getMessage()]);
        }

        return [
            'success' => true
        ];
    }

    protected function getNextNodeId($handleId = null)
    {
        // Find the edge that connects from this node based on the handle ID
        foreach ($this->outgoingEdges as $edge) {
            if (str_contains($edge->getSourceHandle(), $handleId)) {
                return $edge->getTarget();
            }
        }
        return null;
    }
} 