<?php

namespace Modules\Flowmaker\Http\Controllers;

use App\Services\Platform\EmbeddingService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Modules\Flowmaker\Models\EmbeddedChunk;
use Modules\Flowmaker\Models\Flow;
use Modules\Flowmaker\Models\Flowdocument;
use Modules\Flowmaker\Services\DocumentParserService;
use Modules\Flowmaker\Services\WebsiteScraperService;

class AIController extends Controller
{
    protected $websiteScraperService;

    protected $documentParserService;

    public function __construct(
        WebsiteScraperService $websiteScraperService,
        DocumentParserService $documentParserService,
        private EmbeddingService $embeddings,
    ) {
        $this->websiteScraperService = $websiteScraperService;
        $this->documentParserService = $documentParserService;
    }

    /**
     * Get training data for a specific flow
     */
    public function getTrainingData(Request $request, $flowId)
    {
        try {
            $flow = Flow::findOrFail($flowId);

            $faqs = Flowdocument::getBySourceTypeForFlow($flowId, 'faq');
            $trainedWebsites = Flowdocument::getBySourceTypeForFlow($flowId, 'website');
            $trainedFiles = Flowdocument::where('flow_id', $flowId)
                ->whereNotIn('source_type', ['faq', 'website'])
                ->get();

            return response()->json([
                'faqs' => $faqs->map(function ($doc) {
                    return $doc->getFormattedData();
                }),
                'trainedWebsites' => $trainedWebsites->map(function ($doc) {
                    return $doc->getFormattedData();
                }),
                'trainedFiles' => $trainedFiles->map(function ($doc) {
                    return $doc->getFormattedData();
                }),
            ]);

        } catch (\Exception $e) {
            Log::error('Error getting training data: '.$e->getMessage());

            return response()->json([
                'error' => 'An error occurred while fetching training data',
            ], 500);
        }
    }

    /**
     * Process uploaded file and create embeddings
     */
    public function processFile(Request $request)
    {
        try {
            Log::info('File processing request received', [
                'request_data' => $request->all(),
                'file_url' => $request->input('file_url'),
                'file_name' => $request->input('file_name'),
                'file_type' => $request->input('file_type'),
                'flow_id' => $request->input('flow_id'),
            ]);

            $validator = Validator::make($request->all(), [
                'file_url' => 'required|string',
                'file_name' => 'required|string',
                'file_type' => 'required|string|in:pdf,docx,doc,txt',
                'flow_id' => 'required|exists:flows,id',
            ]);

            if ($validator->fails()) {
                Log::error('File processing validation failed', [
                    'errors' => $validator->errors()->toArray(),
                    'request_data' => $request->all(),
                ]);

                return response()->json([
                    'error' => 'Validation failed',
                    'validation_errors' => $validator->errors()->toArray(),
                    'received_data' => $request->all(),
                ], 422);
            }

            $fileUrl = $request->input('file_url');
            $fileName = $request->input('file_name');
            $fileType = $request->input('file_type');
            $flowId = $request->input('flow_id');
            $flow = Flow::findOrFail($flowId);

            Log::info('Processing file for embeddings', [
                'file_url' => $fileUrl,
                'file_name' => $fileName,
                'file_type' => $fileType,
                'flow_id' => $flowId,
            ]);

            // Check if this file has already been processed for this flow
            $existingDocument = Flowdocument::where('flow_id', $flowId)
                ->where('source_type', $fileType)
                ->where('source_url', $fileUrl)
                ->first();

            if ($existingDocument) {
                return response()->json([
                    'error' => 'This file has already been processed for this flow',
                ], 422);
            }

            // Extract text from the document
            $parsedData = $this->documentParserService->extractText($fileUrl, $fileType);

            if (empty($parsedData['content'])) {
                return response()->json([
                    'error' => 'Could not extract content from the document. '.($parsedData['error'] ?? ''),
                ], 422);
            }

            if (strlen($parsedData['content']) < 50) {
                return response()->json([
                    'error' => 'Document content is too short to create meaningful embeddings',
                ], 422);
            }

            // Create the flow document
            $flowDocument = Flowdocument::create([
                'flow_id' => $flowId,
                'title' => $fileName,
                'source_type' => $fileType,
                'source_url' => $fileUrl,
                'content' => $parsedData['content'],
            ]);

            // Split content into chunks for embedding
            $chunks = $this->splitContentIntoChunks($parsedData['content']);
            $successfulChunks = 0;

            // Process each chunk and create embeddings
            foreach ($chunks as $chunk) {
                if (strlen(trim($chunk)) < 50) { // Skip very short chunks
                    continue;
                }

                $embedding = $this->createEmbedding($chunk, $flow);

                if ($embedding) {
                    EmbeddedChunk::create([
                        'document_id' => $flowDocument->id,
                        'content' => $chunk,
                        'embedding' => $embedding,
                    ]);
                    $successfulChunks++;
                }
            }

            if ($successfulChunks === 0) {
                // Delete the document if no embeddings were created
                $flowDocument->delete();

                return response()->json([
                    'error' => 'Failed to create embeddings for the document. Please check your OpenRouter API key or managed AI credits.',
                ], 500);
            }

            Log::info('File processed successfully', [
                'flow_id' => $flowId,
                'document_id' => $flowDocument->id,
                'chunks_processed' => $successfulChunks,
                'file_name' => $fileName,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'File processed and embedded successfully',
                'document' => $flowDocument->getFormattedData(),
                'embedding_created' => true,
                'chunk_count' => $successfulChunks,
            ]);

        } catch (\Exception $e) {
            Log::error('Error processing file: '.$e->getMessage(), [
                'request_data' => $request->all(),
                'exception' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'error' => 'An error occurred while processing the file',
            ], 500);
        }
    }

    /**
     * Process FAQ and create embeddings
     */
    public function processFAQ(Request $request)
    {
        try {
            $request->validate([
                'question' => 'required|string|max:500',
                'answer' => 'required|string|max:2000',
                'flow_id' => 'required|exists:flows,id',
            ]);

            $question = trim($request->input('question'));
            $answer = trim($request->input('answer'));
            $flowId = $request->input('flow_id');
            $flow = Flow::findOrFail($flowId);

            // Check if this exact FAQ already exists for this flow
            $existingFAQ = Flowdocument::where('flow_id', $flowId)
                ->where('source_type', 'faq')
                ->where('title', $question)
                ->first();

            if ($existingFAQ) {
                return response()->json([
                    'error' => 'This FAQ question already exists for this flow',
                ], 422);
            }

            // Create the flow document for FAQ
            $flowDocument = Flowdocument::create([
                'flow_id' => $flowId,
                'title' => $question,
                'source_type' => 'faq',
                'source_url' => $answer, // Store answer in source_url for FAQs
                'content' => $question."\n\n".$answer, // Combined content for embedding
            ]);

            // Create embedding for the combined question and answer
            $combinedContent = $question."\n\n".$answer;

            if (strlen($combinedContent) < 10) {
                // Delete the document if content is too short
                $flowDocument->delete();

                return response()->json([
                    'error' => 'FAQ content is too short to create meaningful embeddings',
                ], 422);
            }

            $embedding = $this->createEmbedding($combinedContent, $flow);

            if (! $embedding) {
                // Delete the document if embedding creation failed
                $flowDocument->delete();

                return response()->json([
                    'error' => 'Failed to create embedding for the FAQ. Please check your OpenRouter API key or managed AI credits.',
                ], 500);
            }

            // Create the embedded chunk
            $embeddedChunk = EmbeddedChunk::create([
                'document_id' => $flowDocument->id,
                'content' => $combinedContent,
                'embedding' => $embedding,
            ]);

            Log::info('FAQ processed successfully', [
                'flow_id' => $flowId,
                'document_id' => $flowDocument->id,
                'chunk_id' => $embeddedChunk->id,
                'question' => $question,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'FAQ processed and embedded successfully',
                'document' => $flowDocument->getFormattedData(),
                'embedding_created' => true,
                'chunk_count' => 1,
            ]);

        } catch (\Exception $e) {
            Log::error('Error processing FAQ: '.$e->getMessage(), [
                'request_data' => $request->all(),
                'exception' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'error' => 'An error occurred while processing the FAQ',
            ], 500);
        }
    }

    /**
     * Delete training document and its embeddings
     */
    public function deleteDocument(Request $request, $documentId)
    {
        try {
            $documentId = str_replace('web-', '', $documentId);
            $document = Flowdocument::findOrFail($documentId);

            // Delete all associated embeddings (cascade should handle this)
            $document->delete();

            return response()->json([
                'success' => true,
                'message' => 'Document deleted successfully',
            ]);

        } catch (\Exception $e) {
            Log::error('Error deleting document: '.$e->getMessage());

            return response()->json([
                'error' => 'An error occurred while deleting the document',
            ], 500);
        }
    }

    /**
     * Process website content and create embeddings
     */
    public function processWebsite(Request $request)
    {
        try {
            $request->validate([
                'url' => 'required|url',
                'flow_id' => 'required|exists:flows,id',
            ]);

            $url = $request->input('url');
            $flowId = $request->input('flow_id');
            $flow = Flow::findOrFail($flowId);

            // Check if this URL has already been processed for this flow
            $existingDocument = Flowdocument::where('flow_id', $flowId)
                ->where('source_type', 'website')
                ->where('source_url', $url)
                ->first();

            if ($existingDocument) {
                return response()->json([
                    'error' => 'This website has already been processed for this flow',
                ], 422);
            }

            // Scrape the website content
            $scrapedData = $this->websiteScraperService->extractText($url);

            if (empty($scrapedData['content'])) {
                return response()->json([
                    'error' => 'Could not extract content from the website',
                ], 422);
            }

            // Create the flow document
            $flowDocument = Flowdocument::create([
                'flow_id' => $flowId,
                'title' => $scrapedData['title'] ?: 'Website Content',
                'source_type' => 'website',
                'source_url' => $url,
                'content' => $scrapedData['content'],
            ]);

            // Split content into chunks for embedding
            $chunks = $this->splitContentIntoChunks($scrapedData['content']);

            // Process each chunk and create embeddings
            foreach ($chunks as $chunk) {
                if (strlen(trim($chunk)) < 50) { // Skip very short chunks
                    continue;
                }

                $embedding = $this->createEmbedding($chunk, $flow);

                if ($embedding) {
                    EmbeddedChunk::create([
                        'document_id' => $flowDocument->id,
                        'content' => $chunk,
                        'embedding' => $embedding,
                    ]);
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Website processed successfully',
                'document' => $flowDocument->getFormattedData(),
            ]);

        } catch (\Exception $e) {
            Log::error('Error processing website: '.$e->getMessage());

            return response()->json([
                'error' => 'An error occurred while processing the website',
            ], 500);
        }
    }

    /**
     * Split content into manageable chunks for embedding
     */
    private function splitContentIntoChunks($content, $maxChunkSize = 1000)
    {
        $chunks = [];
        $sentences = preg_split('/(?<=[.!?])\s+/', $content, -1, PREG_SPLIT_NO_EMPTY);

        $currentChunk = '';

        foreach ($sentences as $sentence) {
            if (strlen($currentChunk.' '.$sentence) > $maxChunkSize && ! empty($currentChunk)) {
                $chunks[] = trim($currentChunk);
                $currentChunk = $sentence;
            } else {
                $currentChunk .= (empty($currentChunk) ? '' : ' ').$sentence;
            }
        }

        if (! empty($currentChunk)) {
            $chunks[] = trim($currentChunk);
        }

        return $chunks;
    }

    /**
     * Train on knowledge base articles
     */
    public function trainKnowledgeBase(Request $request)
    {
        try {
            $request->validate([
                'flow_id' => 'required|exists:flows,id',
            ]);

            $flowId = $request->input('flow_id');
            $flow = Flow::findOrFail($flowId);
            $companyId = $flow->company_id;

            Log::info('Starting knowledge base training', [
                'flow_id' => $flowId,
                'company_id' => $companyId,
            ]);

            // Check if knowledge_articles table exists
            if (! DB::getSchemaBuilder()->hasTable('knowledge_articles')) {
                return response()->json([
                    'error' => 'Knowledge base module not available (table not found)',
                ], 422);
            }

            // Get the last training timestamp for this flow
            $lastTrainingTimestamp = Flowdocument::where('flow_id', $flowId)
                ->where('source_type', 'knowledge_article')
                ->max('updated_at');

            Log::info('Last knowledge base training timestamp', [
                'last_training' => $lastTrainingTimestamp,
            ]);

            // Get all published knowledge articles for this company
            $knowledgeArticlesQuery = DB::table('knowledge_articles')
                ->where('company_id', $companyId)
                ->where('status', 'published')
                ->whereNull('deleted_at');

            // Separate new and updated articles
            $newArticles = [];
            $updatedArticles = [];

            if ($lastTrainingTimestamp) {
                // Get new articles (created after last training)
                $newArticles = (clone $knowledgeArticlesQuery)
                    ->where('created_at', '>', $lastTrainingTimestamp)
                    ->get();

                // Get updated articles (updated after last training, but not created after)
                $updatedArticles = (clone $knowledgeArticlesQuery)
                    ->where('updated_at', '>', $lastTrainingTimestamp)
                    ->where('created_at', '<=', $lastTrainingTimestamp)
                    ->get();
            } else {
                // No previous training - all articles are new
                $newArticles = $knowledgeArticlesQuery->get();
            }

            Log::info('Found articles to process', [
                'new_articles' => count($newArticles),
                'updated_articles' => count($updatedArticles),
            ]);

            $stats = [
                'new_articles' => 0,
                'updated_articles' => 0,
                'total_chunks' => 0,
                'errors' => [],
            ];

            // Process new articles
            foreach ($newArticles as $article) {
                try {
                    $chunkCount = $this->trainArticle($flowId, $article, false);
                    $stats['new_articles']++;
                    $stats['total_chunks'] += $chunkCount;

                    Log::info('Processed new article', [
                        'article_id' => $article->id,
                        'title' => $article->title,
                        'chunks' => $chunkCount,
                    ]);
                } catch (\Exception $e) {
                    Log::error('Error processing new article', [
                        'article_id' => $article->id,
                        'error' => $e->getMessage(),
                    ]);
                    $stats['errors'][] = "Article '{$article->title}': {$e->getMessage()}";
                }
            }

            // Process updated articles
            foreach ($updatedArticles as $article) {
                try {
                    $chunkCount = $this->trainArticle($flowId, $article, true);
                    $stats['updated_articles']++;
                    $stats['total_chunks'] += $chunkCount;

                    Log::info('Processed updated article', [
                        'article_id' => $article->id,
                        'title' => $article->title,
                        'chunks' => $chunkCount,
                    ]);
                } catch (\Exception $e) {
                    Log::error('Error processing updated article', [
                        'article_id' => $article->id,
                        'error' => $e->getMessage(),
                    ]);
                    $stats['errors'][] = "Article '{$article->title}': {$e->getMessage()}";
                }
            }

            Log::info('Knowledge base training completed', $stats);

            $message = 'Knowledge base training completed successfully';
            if (count($stats['errors']) > 0) {
                $message .= ' with some errors';
            }

            return response()->json([
                'success' => true,
                'message' => $message,
                'stats' => $stats,
            ]);

        } catch (\Exception $e) {
            Log::error('Error in knowledge base training: '.$e->getMessage(), [
                'request_data' => $request->all(),
                'exception' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'error' => 'An error occurred while training on knowledge base',
            ], 500);
        }
    }

    /**
     * Train a single knowledge article
     */
    private function trainArticle($flowId, $article, $isUpdate = false)
    {
        $flow = Flow::findOrFail($flowId);

        // Create unique identifier for this article
        $sourceUrl = "knowledge_article_{$article->id}";

        if ($isUpdate) {
            // Delete existing training data for this article
            $existingDocument = Flowdocument::where('flow_id', $flowId)
                ->where('source_type', 'knowledge_article')
                ->where('source_url', $sourceUrl)
                ->first();

            if ($existingDocument) {
                Log::info('Deleting existing knowledge article training data', [
                    'document_id' => $existingDocument->id,
                    'article_id' => $article->id,
                ]);
                $existingDocument->delete(); // This should cascade to embedded chunks
            }
        }

        // Prepare content for training (title + content)
        $trainingContent = $article->title."\n\n".strip_tags($article->content);

        if (strlen($trainingContent) < 50) {
            throw new \Exception('Article content is too short to create meaningful embeddings');
        }

        // Create the flow document
        $flowDocument = Flowdocument::create([
            'flow_id' => $flowId,
            'title' => $article->title,
            'source_type' => 'knowledge_article',
            'source_url' => $sourceUrl,
            'content' => $trainingContent,
        ]);

        // Split content into chunks for embedding
        $chunks = $this->splitContentIntoChunks($trainingContent);
        $successfulChunks = 0;

        // Process each chunk and create embeddings
        foreach ($chunks as $chunk) {
            if (strlen(trim($chunk)) < 50) { // Skip very short chunks
                continue;
            }

            $embedding = $this->createEmbedding($chunk, $flow);

            if ($embedding) {
                EmbeddedChunk::create([
                    'document_id' => $flowDocument->id,
                    'content' => $chunk,
                    'embedding' => $embedding,
                ]);
                $successfulChunks++;
            }
        }

        if ($successfulChunks === 0) {
            // Delete the document if no embeddings were created
            $flowDocument->delete();
            throw new \Exception('Failed to create embeddings for the article');
        }

        return $successfulChunks;
    }

    /**
     * Create embedding via OpenRouter (same key as LLM / Flow Assistant).
     */
    private function createEmbedding(string $text, Flow $flow): ?array
    {
        $company = $flow->company;

        if (! $company) {
            Log::error('Flow has no company for embedding metering', ['flow_id' => $flow->id]);

            return null;
        }

        return $this->embeddings->create($company, $text, $flow->id, meterUsage: true);
    }
}
