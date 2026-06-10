<?php

namespace Modules\Whatsappcall\Services;

use Modules\Flowmaker\Traits\VectorSearch;

class FlowKnowledgeSearch
{
    use VectorSearch;

    public function searchAndFormat(
        string $query,
        int $flowId,
        int $limit = 5,
        float $similarityThreshold = 0.3
    ): string {
        $docs = $this->searchRelevantDocuments($query, $flowId, $limit, $similarityThreshold);

        return $this->formatRelevantDocuments($docs);
    }
}
