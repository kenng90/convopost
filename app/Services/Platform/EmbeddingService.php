<?php

namespace App\Services\Platform;

use App\Models\Company;
use Illuminate\Support\Facades\Log;

class EmbeddingService
{
    public function __construct(
        private readonly ManagedAiService $managedAi,
        private readonly OpenRouterService $openRouter,
    ) {
    }

    public function model(): string
    {
        return (string) config('managed-ai.embedding_model', 'openai/text-embedding-3-small');
    }

    /**
     * Create a vector embedding via OpenRouter (same key as LLM / Flow Assistant).
     *
     * @param  bool  $meterUsage  When true, deducts managed AI credits (knowledge-base indexing).
     *                            Runtime vector search passes false — cost is covered by the LLM turn.
     * @return array<int, float>|null
     */
    public function create(Company $company, string $text, int $flowId, bool $meterUsage = true): ?array
    {
        $keyInfo = $this->managedAi->resolveOpenRouterKey($company);

        if ($keyInfo['key'] === null) {
            Log::error('No OpenRouter API key configured for embeddings', ['flow_id' => $flowId]);

            return null;
        }

        $action = 'ai_embedding';
        $shouldMeter = $meterUsage && $keyInfo['should_meter'];
        $cost = $this->managedAi->actionCost($action);

        if ($shouldMeter && ! $this->managedAi->canConsume($company, $cost)) {
            Log::warning('Managed AI credits exhausted for embedding', ['flow_id' => $flowId]);

            return null;
        }

        try {
            $embedding = $this->openRouter->createEmbedding(
                $keyInfo['key'],
                $text,
                $this->model(),
            );
        } catch (\Throwable $e) {
            Log::error('Embedding creation failed', [
                'flow_id' => $flowId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        if ($embedding === null) {
            return null;
        }

        if ($shouldMeter) {
            $this->managedAi->consume($company, $cost, $action, [
                'model' => $this->model(),
                'flow_id' => $flowId,
            ]);
        }

        return $embedding;
    }
}
