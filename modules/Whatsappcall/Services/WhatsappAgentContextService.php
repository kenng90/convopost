<?php

namespace Modules\Whatsappcall\Services;

use App\Models\Company;
use App\Models\ListCatalog;
use Modules\Flowmaker\Models\Flow;
use Modules\Flowmaker\Models\Flowdocument;

class WhatsappAgentContextService
{
    public function __construct(
        protected FlowKnowledgeSearch $knowledgeSearch,
    ) {
    }

    /**
     * @return array{system_context: string, vector_context: string, flow_id: ?int}
     */
    public function buildForCompany(Company $company, ?string $vectorQuery = null): array
    {
        $flowId = (int) $company->getConfig('whatsapp_ai_flow_id', 0) ?: null;
        $catalogIds = json_decode($company->getConfig('whatsapp_ai_catalog_ids', '[]'), true) ?: [];
        $parts = [];

        $greeting = trim((string) $company->getConfig('whatsapp_ai_greeting', ''));
        if ($greeting !== '') {
            $parts[] = 'Greeting: '.$greeting;
        }

        $systemPrompt = $this->extractFlowSystemPrompt($company, $flowId);
        if ($systemPrompt !== '') {
            $parts[] = "Instructions:\n".$systemPrompt;
        }

        if ($flowId) {
            $flow = Flow::withoutGlobalScopes()
                ->where('id', $flowId)
                ->where('company_id', $company->id)
                ->first();
            if ($flow) {
                $parts[] = 'Knowledge flow: '.$flow->name;
                $docs = Flowdocument::where('flow_id', $flow->id)->limit(20)->pluck('content');
                foreach ($docs as $doc) {
                    if ($doc) {
                        $parts[] = 'Knowledge: '.mb_substr(strip_tags((string) $doc), 0, 500);
                    }
                }
            }
        }

        foreach ($catalogIds as $catalogId) {
            $catalog = ListCatalog::withoutGlobalScopes()
                ->where('id', $catalogId)
                ->where('company_id', $company->id)
                ->first();
            if (! $catalog) {
                continue;
            }
            $items = collect($catalog->items ?? [])->take(30);
            $parts[] = 'Catalog: '.$catalog->name;
            foreach ($items as $item) {
                $title = $item['title'] ?? 'Item';
                $price = $item['price'] ?? '';
                $desc = mb_substr($item['description'] ?? '', 0, 120);
                $parts[] = '- '.$title.($price ? " ({$price})" : '').($desc ? ": {$desc}" : '');
            }
        }

        if ($catalogIds !== [] && filter_var(
            $company->getConfig('whatsapp_ai_send_invoice_after_call', true),
            FILTER_VALIDATE_BOOLEAN
        )) {
            $parts[] = 'When the caller wants to buy a catalog product, confirm the exact product name and price on the call. After the call ends, a WhatsApp invoice with a payment link is sent automatically — you do not need to run chat flows for this.';
        }

        $vectorContext = '';
        $enableVector = filter_var(
            $company->getConfig('whatsapp_ai_enable_vector_search', true),
            FILTER_VALIDATE_BOOLEAN
        );
        if ($flowId && $enableVector && $vectorQuery && trim($vectorQuery) !== '') {
            $vectorContext = $this->knowledgeSearch->searchAndFormat(
                $vectorQuery,
                $flowId,
                (int) $company->getConfig('whatsapp_ai_vector_search_limit', 5),
                (float) $company->getConfig('whatsapp_ai_vector_similarity_threshold', 0.3)
            );
        }

        return [
            'system_context' => implode("\n", $parts),
            'vector_context' => $vectorContext,
            'flow_id' => $flowId,
        ];
    }

    public function defaultVectorQuery(Company $company): string
    {
        $greeting = trim((string) $company->getConfig('whatsapp_ai_greeting', ''));

        return $greeting !== ''
            ? $greeting
            : 'WhatsApp voice call customer inquiry products services';
    }

    private function extractFlowSystemPrompt(Company $company, ?int $flowId): string
    {
        if (! $flowId) {
            return '';
        }

        $flow = Flow::withoutGlobalScopes()
            ->where('id', $flowId)
            ->where('company_id', $company->id)
            ->first();

        if (! $flow || ! $flow->flow_data) {
            return '';
        }

        $flowData = json_decode($flow->flow_data, true);
        if (! is_array($flowData) || empty($flowData['nodes'])) {
            return '';
        }

        foreach ($flowData['nodes'] as $node) {
            $type = $node['type'] ?? '';
            if (! in_array($type, ['openai', 'llm'], true)) {
                continue;
            }
            $prompt = $node['data']['settings']['llm']['systemPrompt']
                ?? $node['data']['settings']['openai']['systemPrompt']
                ?? $node['data']['settings']['systemPrompt']
                ?? null;
            if (is_string($prompt) && trim($prompt) !== '') {
                return trim($prompt);
            }
        }

        return '';
    }
}
