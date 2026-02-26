<?php

/**
 * LLM Node with Vector Search Integration
 * 
 * This LLM node integrates with your vectorized knowledge base to provide context-aware AI responses.
 * 
 * Features:
 * - Automatic vector similarity search against flow documents
 * - Configurable similarity thresholds and result limits
 * - Context injection into OpenRouter API calls
 * - Metadata tracking for analytics and debugging
 * 
 * Configuration Options:
 * - enableVectorSearch: true/false (default: true)
 * - vectorSearchLimit: number of documents to include (default: 5)
 * - similarityThreshold: minimum similarity score (default: 0.3)
 * 
 * How it works:
 * 1. User message/prompt is converted to embedding vector
 * 2. Cosine similarity calculated against all flow document chunks
 * 3. Most relevant chunks above threshold are selected
 * 4. Formatted context is injected into OpenRouter API call
 * 5. LLM responds with knowledge base awareness
 * 
 * Stored Variables:
 * - {variableName}: The LLM response
 * - {variableName}_vector_search_metadata: Search metadata (JSON)
 */

namespace Modules\Flowmaker\Models\Nodes;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\Flowmaker\Models\Contact;
use Modules\Flowmaker\Models\Flow;
use Modules\Flowmaker\Traits\VectorSearch;

class LLM extends Node
{
    use VectorSearch;
    
    public function process($message, $data)
    {
        Log::info('Processing message in LLM node', ['message' => $message, 'data' => $data]);
        
        try {
            // Get LLM settings from node data
            $settings = $this->getDataAsArray()['settings']['llm'] ?? [];
            
            // Default settings if not provided
            $model = $settings['model'] ?? 'openai/gpt-4o-mini';
            $systemPrompt = $settings['systemPrompt'] ?? 'You are a helpful AI assistant.';
            $prompt = $settings['prompt'] ?? '';
            $temperature = $settings['temperature'] ?? 0.7;
            $maxTokens = $settings['maxTokens'] ?? 1000;
            $variableName = $settings['variableName'] ?? 'ai_response';
            
            // Vector search settings
            $enableVectorSearch = $settings['enableVectorSearch'] ?? true;
            $vectorSearchLimit = $settings['vectorSearchLimit'] ?? 5;
            $similarityThreshold = $settings['similarityThreshold'] ?? 0.3;

            // Intentions settings
            $intentions = $settings['intentions'] ?? [];
            $hasIntentions = !empty($intentions);
            
            // Find the contact
            $contactId = is_object($data) ? $data->contact_id : $data['contact_id'];
            $contact = Contact::find($contactId);
           
            if (!$contact) {
                throw new \Exception('Contact not found');
            }
            if( $message){
                $contact->last_message = $message;
                $contact->save();
            }
            Log::info('Contact', ['contact' => $contact,'flow_id'=>$this->flow_id]);
            $previousSummary = $contact->getAISummary($this->flow_id);
            
            Log::info('LLM Settings', [
                'model' => $model, 
                'variableName' => $variableName,
                'temperature' => $temperature,
                'maxTokens' => $maxTokens,
                'enableVectorSearch' => $enableVectorSearch,
                'vectorSearchLimit' => $vectorSearchLimit,
                'similarityThreshold' => $similarityThreshold,
                'hasIntentions' => $hasIntentions,
                'intentionsCount' => count($intentions)
            ]);
            
            // Replace variables in system prompt and user prompt
            $processedSystemPrompt = $contact->changeVariables($systemPrompt, $this->flow_id);
            Log::info('Processed System Prompt', ['processedSystemPrompt' => $processedSystemPrompt]);
            $processedPrompt = $contact->changeVariables($prompt, $this->flow_id);
            
            // Build the JSON structure based on whether intentions are configured
            $jsonStructure = ['message' => 'string'];
            $intentionPromptPart = '';
            
            if ($hasIntentions) {
                $jsonStructure['intent'] = 'string';
                
                // Build intention descriptions for the prompt
                $intentionDescriptions = [];
                foreach ($intentions as $intention) {
                    if (!empty($intention['name']) && !empty($intention['description'])) {
                        $intentionDescriptions[] = "- {$intention['name']}: {$intention['description']}";
                    }
                }
                
                if (!empty($intentionDescriptions)) {
                    $intentionPromptPart = "\n\nBased on the user's message, also determine which of these intentions best matches:\n" . 
                                         implode("\n", $intentionDescriptions) . 
                                         "\n\nIf none of the intentions match clearly, set intent to an empty string.";
                }
            }
            
            $jsonStructureString = json_encode($jsonStructure);
            $processedPrompt .= "{$intentionPromptPart} Return in json format. Do not include any other text than the json. The structure should be {$jsonStructureString}. That is all";
            
            Log::info('Processed Prompts', [
                'systemPrompt' => $processedSystemPrompt,
                'prompt' => $processedPrompt,
                'previousSummary' => $previousSummary
            ]);
            
            // Get OpenRouter API key from config
            // Get the OpenRouter API key from the current flow's company config
            $flow=Flow::find($this->flow_id);
            Log::info('Flow', ['flow' => $flow]);
            $company = $flow->company;
            Log::info('Company', ['company' => $company]);
            $apiKey = $company->getConfig('openrouter_api_key');
            Log::info('API Key', ['apiKey' => $apiKey]);
            if (!$apiKey) {
                throw new \Exception('OpenRouter API key not configured');
            }
            
            // Search for relevant documents using vector similarity
            $knowledgeBaseContext = '';
            $relevantDocs = [];
            
            if ($enableVectorSearch) {
                $searchQuery = $message ?: $processedPrompt;
                $relevantDocs = $this->searchRelevantDocuments($searchQuery, $this->flow_id, $vectorSearchLimit, $similarityThreshold);
                $knowledgeBaseContext = $this->formatRelevantDocuments($relevantDocs);
                
                Log::info('Vector search results', [
                    'enabled' => $enableVectorSearch,
                    'query' => substr($searchQuery, 0, 100),
                    'found_docs' => count($relevantDocs),
                    'context_length' => strlen($knowledgeBaseContext),
                    'similarity_threshold' => $similarityThreshold,
                    'search_limit' => $vectorSearchLimit
                ]);
            } else {
                Log::info('Vector search disabled for this LLM node');
            }

            $data_request = [
                [
                    'role' => 'system',
                    'content' => $processedSystemPrompt
                ],
                [
                    'role' => 'system', 
                    'content' => "\n The previous summary of the conversation is: " . $previousSummary
                ],
                [
                    'role' => 'user', 
                    'content' => $processedPrompt
                ]
               
            ];

            if($knowledgeBaseContext){
                $data_request[] = [
                    'role' => 'system', 
                    'content' => "Here is some context that might be relevant to the user's message: " . $knowledgeBaseContext
                ];
            }

            Log::info('FULL Data Request', ['data_request' => $data_request]);
            
            // Make API call to OpenRouter
            $response = Http::timeout(60)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $apiKey,
                    'Content-Type' => 'application/json',
                    'HTTP-Referer' => config('app.url'),
                    'X-Title' => config('app.name', 'Flowmaker'),
                ])
                ->post('https://openrouter.ai/api/v1/chat/completions', [
                    'model' => $model,
                    'messages' => $data_request,
                    'temperature' => floatval($temperature),
                    'max_tokens' => intval($maxTokens),
                    'response_format' => [
                        'type' => 'json_object'
                    ]
                ]);
            
            if (!$response->successful()) {
                Log::error('OpenRouter API error', [
                    'status' => $response->status(),
                    'response' => $response->body()
                ]);
                throw new \Exception('OpenRouter API call failed: ' . $response->status());
            }
            
            $responseData = $response->json();
            $llmResponse = $responseData['choices'][0]['message']['content'] ?? '';
            
            Log::info('LLM Response', ['response' => $llmResponse]);
            
            // Try to parse as JSON for structured data
            $structuredResponse = null;
            try {
                $structuredResponse = json_decode($llmResponse, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    // Store the structured response
                    $contact->setContactState($this->flow_id, $variableName, json_encode($structuredResponse));
                    
                    // Also store individual fields if it's an object
                    if (is_array($structuredResponse)) {
                        foreach ($structuredResponse as $key => $value) {
                            if (is_scalar($value)) {
                                $contact->setContactState($this->flow_id, $variableName . '_' . $key, $value);

                                // When we get the message, we need to update also the contact state for the message
                                if($key == 'message'){
                                    $previousSummary = $contact->getContactStateValue($this->flow_id, 'ai_summary');
                                    //New summary
                                    $newSummary = $previousSummary . "\n" ."Q: ".$message. "\n" ."A: ".$value;   
                                    Log::info('New Summary', ['newSummary' => $newSummary]);
                                    
                                    //If the new summary is longer than 1000 characters, we need to summarize it using the LLM
                                    if(strlen($newSummary) > 500){
                                        Log::info('Summary too long, calling OpenRouter to summarize', ['length' => strlen($newSummary)]);
                                        
                                        try {
                                            // Make API call to OpenRouter to summarize the conversation
                                            $summarizeResponse = Http::timeout(60)
                                                ->withHeaders([
                                                    'Authorization' => 'Bearer ' . $apiKey,
                                                    'Content-Type' => 'application/json',
                                                    'HTTP-Referer' => config('app.url'),
                                                    'X-Title' => config('app.name', 'Flowmaker'),
                                                ])
                                                ->post('https://openrouter.ai/api/v1/chat/completions', [
                                                    'model' => 'openai/gpt-4o-mini', // Use efficient model for summarization
                                                    'messages' => [
                                                        [
                                                            'role' => 'system',
                                                            'content' => 'You are a conversation summarizer. Summarize the following conversation history while preserving key information, decisions, and context. Keep it concise but comprehensive. Return only the summary in JSON format: {"summary": "your summary here"}'
                                                        ],
                                                        [
                                                            'role' => 'user', 
                                                            'content' => 'Please summarize this conversation: ' . $newSummary
                                                        ]
                                                    ],
                                                    'temperature' => 0.3, // Lower temperature for more consistent summaries
                                                    'max_tokens' => 500,   // Limit summary length
                                                    'response_format' => [
                                                        'type' => 'json_object'
                                                    ]
                                                ]);
                                            
                                            if ($summarizeResponse->successful()) {
                                                $summaryData = $summarizeResponse->json();
                                                $summaryContent = $summaryData['choices'][0]['message']['content'] ?? '';
                                                
                                                // Try to extract summary from JSON response
                                                try {
                                                    $summaryJson = json_decode($summaryContent, true);
                                                    $finalSummary = $summaryJson['summary'] ?? $summaryContent;
                                                } catch (\Exception $e) {
                                                    $finalSummary = $summaryContent;
                                                }
                                                
                                                Log::info('Successfully summarized conversation', ['original_length' => strlen($newSummary), 'summary_length' => strlen($finalSummary)]);
                                                $contact->setContactState($this->flow_id, 'ai_summary', $finalSummary);
                                            } else {
                                                Log::error('Failed to summarize conversation', ['status' => $summarizeResponse->status()]);
                                                // Fallback: Keep only the latest part of the summary
                                                $truncatedSummary = substr($newSummary, -800); // Keep last 800 chars
                                                $contact->setContactState($this->flow_id, 'ai_summary', $truncatedSummary);
                                            }
                                        } catch (\Exception $e) {
                                            Log::error('Error during summarization', ['error' => $e->getMessage()]);
                                            // Fallback: Keep only the latest part of the summary
                                            $truncatedSummary = substr($newSummary, -800); // Keep last 800 chars
                                            $contact->setContactState($this->flow_id, 'ai_summary', $truncatedSummary);
                                        }
                                    } else {
                                        // Summary is still within limits, store as is
                                        Log::info('Summary is still within limits, storing as is', ['newSummary' => $newSummary]);
                                        $contact->setContactState($this->flow_id, 'ai_summary', $newSummary);
                                    }
                                }
                                
                                // Handle intent field specifically
                                if($key == 'intent' && $hasIntentions) {
                                    Log::info('Detected intent', ['intent' => $value, 'variableName' => $variableName]);
                                    // Store the intent in the _intent variable
                                    $contact->setContactState($this->flow_id, $variableName . '_intent', $value);
                                }
                            }
                        }
                    }
                } else {
                    // Store as plain text if not valid JSON
                    $contact->setContactState($this->flow_id, $variableName, $llmResponse);
                }
            } catch (\Exception $e) {
                // Store as plain text if JSON parsing fails
                $contact->setContactState($this->flow_id, $variableName, $llmResponse);
            }
            
            Log::info('LLM response stored in variable', ['variableName' => $variableName]);
            
            // Store vector search metadata for debugging/analytics
            /*if ($enableVectorSearch && !empty($relevantDocs)) {
                $vectorSearchMetadata = [
                    'query' => substr($searchQuery ?? '', 0, 200),
                    'results_count' => count($relevantDocs),
                    'top_similarity' => !empty($relevantDocs) ? round($relevantDocs[0]['similarity'] * 100, 2) : 0,
                    'documents_used' => array_map(function($doc) {
                        return [
                            'title' => $doc['document_title'],
                            'type' => $doc['document_type'],
                            'similarity' => round($doc['similarity'] * 100, 2)
                        ];
                    }, $relevantDocs)
                ];
                $contact->setContactState($this->flow_id, $variableName . '_vector_search_metadata', json_encode($vectorSearchMetadata));
            }*/
            
        } catch (\Exception $e) {
            Log::error('Error processing LLM node', ['error' => $e->getMessage()]);
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

    protected function getNextNodeId($data = null)
    {
        // Get the first outgoing edge's target
        if (!empty($this->outgoingEdges)) {
            return $this->outgoingEdges[0]->getTarget();
        }
        return null;
    }
}
