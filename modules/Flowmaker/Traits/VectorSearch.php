<?php

namespace Modules\Flowmaker\Traits;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\Flowmaker\Models\EmbeddedChunk;
use Modules\Flowmaker\Models\Flowdocument;

trait VectorSearch
{
    /**
     * Calculate cosine similarity between two vectors
     */
    protected function cosineSimilarity($vectorA, $vectorB)
    {
        if (count($vectorA) !== count($vectorB)) {
            return 0;
        }
        
        $dotProduct = 0;
        $magnitudeA = 0;
        $magnitudeB = 0;
        
        for ($i = 0; $i < count($vectorA); $i++) {
            $dotProduct += $vectorA[$i] * $vectorB[$i];
            $magnitudeA += $vectorA[$i] * $vectorA[$i];
            $magnitudeB += $vectorB[$i] * $vectorB[$i];
        }
        
        $magnitudeA = sqrt($magnitudeA);
        $magnitudeB = sqrt($magnitudeB);
        
        if ($magnitudeA == 0 || $magnitudeB == 0) {
            return 0;
        }
        
        return $dotProduct / ($magnitudeA * $magnitudeB);
    }
    
    /**
     * Get embeddings for text using OpenAI API (same method as used in AIController)
     */
    protected function getEmbedding($text)
    {
        try {
            $apiKey = config('wpbox.openai_api_key');
            
            if (empty($apiKey)) {
                Log::error('OpenAI API key not configured for embeddings');
                return null;
            }

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json',
            ])->post('https://api.openai.com/v1/embeddings', [
                'input' => $text,
                'model' => 'text-embedding-3-small'
            ]);

            if ($response->successful()) {
                $data = $response->json();
                return $data['data'][0]['embedding'] ?? null;
            } else {
                Log::error('OpenAI API embedding error', [
                    'status' => $response->status(),
                    'response' => $response->body()
                ]);
                return null;
            }
        } catch (\Exception $e) {
            Log::error('Error creating embedding for search', ['error' => $e->getMessage()]);
            return null;
        }
    }
    
    /**
     * Search for relevant documents using vector similarity
     */
    protected function searchRelevantDocuments($query, $flowId, $limit = 5, $similarityThreshold = 0.3)
    {
        try {
            // Get embedding for the query
            $queryEmbedding = $this->getEmbedding($query);
            
            if (!$queryEmbedding) {
                Log::warning('Could not get embedding for query, skipping vector search');
                return [];
            }
            
            // Get all embedded chunks for this flow
            $chunks = EmbeddedChunk::whereHas('document', function($q) use ($flowId) {
                $q->where('flow_id', $flowId);
            })->with('document')->get();
            
            if ($chunks->isEmpty()) {
                Log::info('No embedded chunks found for flow', ['flow_id' => $flowId]);
                return [];
            }
            
            // Calculate similarities and sort
            $similarities = [];
            foreach ($chunks as $chunk) {
                $similarity = $this->cosineSimilarity($queryEmbedding, $chunk->embedding);
                
                if ($similarity >= $similarityThreshold) {
                    $similarities[] = [
                        'chunk' => $chunk,
                        'similarity' => $similarity,
                        'content' => $chunk->content,
                        'document_title' => $chunk->document->title ?? 'Untitled',
                        'document_type' => $chunk->document->source_type ?? 'unknown'
                    ];
                }
            }
            
            // Sort by similarity (highest first) and limit results
            usort($similarities, function($a, $b) {
                return $b['similarity'] <=> $a['similarity'];
            });
            
            $results = array_slice($similarities, 0, $limit);
            
            Log::info('Vector search completed', [
                'query' => substr($query, 0, 100),
                'total_chunks' => $chunks->count(),
                'relevant_results' => count($results),
                'top_similarity' => !empty($results) ? $results[0]['similarity'] : 0
            ]);
            
            return $results;
            
        } catch (\Exception $e) {
            Log::error('Error in vector search', ['error' => $e->getMessage()]);
            return [];
        }
    }
    
    /**
     * Format relevant documents for inclusion in LLM context
     */
    protected function formatRelevantDocuments($relevantDocs)
    {
        if (empty($relevantDocs)) {
            return '';
        }
        
        $formattedContext = "\n\n=== RELEVANT KNOWLEDGE BASE ===\n";
        
        foreach ($relevantDocs as $index => $doc) {
            $formattedContext .= "\n[Document " . ($index + 1) . " - " . $doc['document_title'] . " (" . ucfirst($doc['document_type']) . ") - Relevance: " . round($doc['similarity'] * 100, 1) . "%]\n";
            $formattedContext .= $doc['content'] . "\n";
            $formattedContext .= "---\n";
        }
        
        $formattedContext .= "\n=== END KNOWLEDGE BASE ===\n\n";
        $formattedContext .= "Please use the information from the knowledge base above to provide accurate and relevant responses. If the user's question relates to information in the knowledge base, prioritize that information in your response.\n";
        
        return $formattedContext;
    }
    
    /**
     * Debug vector search - useful for testing and troubleshooting
     */
    protected function debugVectorSearch($query, $flowId, $limit = 10)
    {
        $queryEmbedding = $this->getEmbedding($query);
        
        if (!$queryEmbedding) {
            return ['error' => 'Could not get embedding for query'];
        }
        
        $chunks = EmbeddedChunk::whereHas('document', function($q) use ($flowId) {
            $q->where('flow_id', $flowId);
        })->with('document')->get();
        
        $similarities = [];
        foreach ($chunks as $chunk) {
            $similarity = $this->cosineSimilarity($queryEmbedding, $chunk->embedding);
            $similarities[] = [
                'chunk_id' => $chunk->id,
                'similarity' => $similarity,
                'content_preview' => substr($chunk->content, 0, 100) . '...',
                'document_title' => $chunk->document->title ?? 'Untitled',
                'document_type' => $chunk->document->source_type ?? 'unknown'
            ];
        }
        
        usort($similarities, function($a, $b) {
            return $b['similarity'] <=> $a['similarity'];
        });
        
        return [
            'query' => $query,
            'total_chunks' => count($chunks),
            'results' => array_slice($similarities, 0, $limit)
        ];
    }
} 