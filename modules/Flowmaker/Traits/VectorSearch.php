<?php

namespace Modules\Flowmaker\Traits;

use App\Services\Platform\EmbeddingService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Modules\Flowmaker\Models\EmbeddedChunk;
use Modules\Flowmaker\Models\Flow;

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
     * Get embeddings for text via OpenRouter (same key as knowledge-base indexing).
     *
     * @return array<int, float>|null
     */
    protected function getEmbedding(string $text, int $flowId): ?array
    {
        try {
            $cacheKey = 'flow_embedding:'.$flowId.':'.md5($text);

            return Cache::remember($cacheKey, 300, function () use ($text, $flowId) {
                $flow = Flow::withoutGlobalScopes()->find($flowId);
                $company = $flow?->company;

                if ($company === null) {
                    Log::error('Could not resolve company for vector search embedding', ['flow_id' => $flowId]);

                    return null;
                }

                return app(EmbeddingService::class)->create(
                    $company,
                    $text,
                    $flowId,
                    meterUsage: false,
                );
            });
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
            $queryEmbedding = $this->getEmbedding($query, $flowId);

            if (! $queryEmbedding) {
                Log::warning('Could not get embedding for query, skipping vector search');

                return [];
            }

            $chunks = EmbeddedChunk::query()
                ->join('flowdocuments', 'embeddedchunks.document_id', '=', 'flowdocuments.id')
                ->where('flowdocuments.flow_id', $flowId)
                ->select([
                    'embeddedchunks.id',
                    'embeddedchunks.content',
                    'embeddedchunks.embedding',
                    'flowdocuments.title',
                    'flowdocuments.source_type',
                ])
                ->get();

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
                        'document_title' => $chunk->title ?? 'Untitled',
                        'document_type' => $chunk->source_type ?? 'unknown',
                    ];
                }
            }

            // Sort by similarity (highest first) and limit results
            usort($similarities, function ($a, $b) {
                return $b['similarity'] <=> $a['similarity'];
            });

            $results = array_slice($similarities, 0, $limit);

            Log::info('Vector search completed', [
                'query' => substr($query, 0, 100),
                'total_chunks' => $chunks->count(),
                'relevant_results' => count($results),
                'top_similarity' => ! empty($results) ? $results[0]['similarity'] : 0,
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
            $formattedContext .= "\n[Document ".($index + 1).' - '.$doc['document_title'].' ('.ucfirst($doc['document_type']).') - Relevance: '.round($doc['similarity'] * 100, 1)."%]\n";
            $formattedContext .= $doc['content']."\n";
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
        $queryEmbedding = $this->getEmbedding($query, $flowId);

        if (! $queryEmbedding) {
            return ['error' => 'Could not get embedding for query'];
        }

        $chunks = EmbeddedChunk::query()
            ->join('flowdocuments', 'embeddedchunks.document_id', '=', 'flowdocuments.id')
            ->where('flowdocuments.flow_id', $flowId)
            ->select([
                'embeddedchunks.id',
                'embeddedchunks.content',
                'embeddedchunks.embedding',
                'flowdocuments.title',
                'flowdocuments.source_type',
            ])
            ->get();

        $similarities = [];
        foreach ($chunks as $chunk) {
            $similarity = $this->cosineSimilarity($queryEmbedding, $chunk->embedding);
            $similarities[] = [
                'chunk_id' => $chunk->id,
                'similarity' => $similarity,
                'content_preview' => substr($chunk->content, 0, 100).'...',
                'document_title' => $chunk->title ?? 'Untitled',
                'document_type' => $chunk->source_type ?? 'unknown',
            ];
        }

        usort($similarities, function ($a, $b) {
            return $b['similarity'] <=> $a['similarity'];
        });

        return [
            'query' => $query,
            'total_chunks' => count($chunks),
            'results' => array_slice($similarities, 0, $limit),
        ];
    }
}
