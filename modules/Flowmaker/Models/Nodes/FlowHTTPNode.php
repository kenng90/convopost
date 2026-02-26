<?php

namespace Modules\Flowmaker\Models\Nodes;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Modules\Flowmaker\Models\Contact;

class FlowHTTPNode extends Node
{
    
    public function process($message, $data)
    {
        Log::info('Processing message in HTTP node', ['message' => $message, 'data' => $data]);
        
        try {
            // Get HTTP settings from node data
            $httpSettings = $this->getDataAsArray()['settings']['http'] ?? [];
            
            Log::info('HTTP Settings', ['httpSettings' => $httpSettings]);

            // Find the contact
            $contactId = is_object($data) ? $data->contact_id : $data['contact_id'];
            $contact = Contact::find($contactId);
            
            if (!$contact) {
                Log::error('Contact not found', ['contactId' => $contactId]);
                return ['success' => false];
            }

            Log::info('Contact found', ['contact' => $contact->id]);

            // Extract settings
            $method = strtoupper($httpSettings['method'] ?? 'GET');
            $url = $httpSettings['url'] ?? '';
            $headers = $httpSettings['headers'] ?? [];
            $params = $httpSettings['params'] ?? [];
            $responseVar = $httpSettings['responseVar'] ?? '';

            // Transform URL with variables
            $url = $contact->changeVariables($url, $this->flow_id);
            Log::info('Transformed URL', ['url' => $url]);

            if (empty($url)) {
                Log::error('URL is empty after transformation');
                return ['success' => false];
            }

            // Prepare headers
            $requestHeaders = [];
            foreach ($headers as $header) {
                if (!empty($header['key']) && !empty($header['value'])) {
                    $key = $contact->changeVariables($header['key'], $this->flow_id);
                    $value = $contact->changeVariables($header['value'], $this->flow_id);
                    $requestHeaders[$key] = $value;
                }
            }

            // Prepare parameters/data
            $requestData = [];
            foreach ($params as $param) {
                if (!empty($param['key']) && !empty($param['value'])) {
                    $key = $contact->changeVariables($param['key'], $this->flow_id);
                    $value = $contact->changeVariables($param['value'], $this->flow_id);
                    $requestData[$key] = $value;
                }
            }

            Log::info('Making HTTP request', [
                'method' => $method,
                'url' => $url,
                'headers' => $requestHeaders,
                'data' => $requestData
            ]);

            // Make HTTP request based on method
            $response = null;
            switch ($method) {
                case 'GET':
                    $response = Http::withHeaders($requestHeaders)->get($url, $requestData);
                    break;
                case 'POST':
                    $response = Http::withHeaders($requestHeaders)->post($url, $requestData);
                    break;
                case 'PUT':
                    $response = Http::withHeaders($requestHeaders)->put($url, $requestData);
                    break;
                case 'DELETE':
                    $response = Http::withHeaders($requestHeaders)->delete($url, $requestData);
                    break;
                default:
                    Log::error('Unsupported HTTP method', ['method' => $method]);
                    return ['success' => false];
            }

            if ($response) {
                $responseData = [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'json' => $response->json(),
                    'headers' => $response->headers()
                ];

                Log::info('HTTP Response received', [
                    'status' => $response->status(),
                    'bodyLength' => strlen($response->body())
                ]);

                // Store response in variable if specified
                if (!empty($responseVar)) {
                    $contact->setContactState($this->flow_id, $responseVar, json_encode($responseData));
                    Log::info('Response stored in variable', ['variable' => $responseVar]);
                }

                // Store individual components for easier access
                if (!empty($responseVar)) {
                    $contact->setContactState($this->flow_id, $responseVar . '_status', $response->status());
                    $contact->setContactState($this->flow_id, $responseVar . '_body', $response->body());
                    if ($response->json()) {
                        $contact->setContactState($this->flow_id, $responseVar . '_json', json_encode($response->json()));
                    }
                }

            } else {
                Log::error('HTTP request failed - no response');
                return ['success' => false];
            }

        } catch (\Exception $e) {
            Log::error('Error processing HTTP request', ['error' => $e->getMessage()]);
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
