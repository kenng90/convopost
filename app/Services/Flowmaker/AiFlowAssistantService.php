<?php

namespace App\Services\Flowmaker;

use App\Models\Company;
use App\Services\Platform\ManagedAiService;
use App\Services\Platform\OpenRouterService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AiFlowAssistantService
{
    /** @var array<int, string> */
    private array $allowedNodeTypes = [
        'keyword_trigger',
        'message',
        'end',
        'request_payment',
        'whatsapp_catalog',
        'catalog_search',
        'listing_inquiry',
        'question',
        'datastore',
        'assign_agent',
        'assign_group',
        'assign_journey_stage',
        'branch',
        'quick_replies',
        'whatsapp_flow',
        'order_status',
    ];

    public function __construct(
        private readonly ManagedAiService $managedAi,
        private readonly OpenRouterService $openRouter,
    ) {
    }

    /**
     * @return array{nodes: array, edges: array, summary: string, source: string}
     */
    public function generate(Company $company, string $description): array
    {
        $keyInfo = $this->managedAi->resolveOpenRouterKey($company);
        $action = 'ai_flow_generate';
        $cost = $this->managedAi->actionCost($action);

        if ($keyInfo['key'] === null) {
            throw new \RuntimeException(__('No OpenRouter API key is configured. Add your key in workspace settings or upgrade for managed AI.'));
        }

        if ($keyInfo['should_meter'] && ! $this->managedAi->canConsume($company, $cost)) {
            throw new \RuntimeException($this->managedAi->exhaustionMessage($company));
        }

        if (config('managed-ai.llm_enabled', true)) {
            try {
                $draft = $this->generateViaLlm($keyInfo['key'], $description);
                if ($keyInfo['should_meter']) {
                    $this->managedAi->consume($company, $cost, $action, [
                        'model' => config('managed-ai.flow_generate_model', 'openai/gpt-4o-mini'),
                        'source' => 'llm',
                    ]);
                }

                return array_merge($draft, ['source' => 'llm']);
            } catch (\Throwable $e) {
                Log::warning('AI flow LLM generation failed, falling back to rules', [
                    'error' => $e->getMessage(),
                ]);

                if (! config('managed-ai.fallback_to_rules', true)) {
                    throw $e;
                }
            }
        }

        $draft = $this->generateRuleBased($description);
        if ($keyInfo['should_meter'] && config('managed-ai.charge_rules_fallback', false)) {
            $this->managedAi->consume($company, $cost, $action, ['source' => 'rules']);
        }

        return array_merge($draft, ['source' => 'rules']);
    }

    /**
     * @return array{nodes: array, edges: array, summary: string}
     */
    private function generateViaLlm(string $apiKey, string $description): array
    {
        $model = config('managed-ai.flow_generate_model', 'openai/gpt-4o-mini');
        $allowed = implode(', ', $this->allowedNodeTypes);

        $systemPrompt = <<<PROMPT
You are a WhatsApp flow builder assistant. Given a business requirement, output a valid JSON object with exactly these keys:
- "nodes": array of flow nodes
- "edges": array of connections between nodes
- "summary": one sentence describing the draft for the user

Each node must have: id (unique string), type (one of: {$allowed}), position {x,y}, data {label, type, ...type-specific fields}.

Rules:
- Start with a keyword_trigger node with 1-3 keywords relevant to the use case.
- End paths with an "end" node.
- Use "message" nodes for automated replies; settings.message holds the text.
- For shops/catalogs, use whatsapp_catalog (displayMode interactive_list) then request_payment (provider auto, amount {{catalog_order_total_amount}}), then optional order_status.
- For simple payments, use request_payment (not mpesa_stk_push).
- For lead capture, use question nodes and assign_agent / assign_group / assign_journey_stage.
- Edges need id, source, target, and sourceHandle when leaving keyword_trigger or multi-outcome nodes (success/failed, onCheckoutComplete).
- Keep flows simple (3-10 nodes). Use realistic placeholder copy.
- Return ONLY valid JSON, no markdown fences.
PROMPT;

        $result = $this->openRouter->chatCompletion(
            $apiKey,
            [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $description],
            ],
            $model,
            0.4,
            4000,
            true,
        );

        $parsed = json_decode($result['content'], true);
        if (! is_array($parsed) || empty($parsed['nodes']) || empty($parsed['edges'])) {
            throw new \RuntimeException('LLM returned invalid flow structure');
        }

        $nodes = $this->normalizeNodes($parsed['nodes']);
        $edges = $this->normalizeEdges($parsed['edges'], $nodes);

        return [
            'nodes' => $nodes,
            'edges' => $edges,
            'summary' => (string) ($parsed['summary'] ?? __('AI-generated draft flow. Review in the editor before publishing.')),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $nodes
     * @return array<int, array<string, mixed>>
     */
    private function normalizeNodes(array $nodes): array
    {
        $normalized = [];

        foreach ($nodes as $index => $node) {
            if (! is_array($node) || empty($node['type'])) {
                continue;
            }

            if (! in_array($node['type'], $this->allowedNodeTypes, true)) {
                continue;
            }

            $id = (string) ($node['id'] ?? ($node['type'].'-'.Str::uuid()));
            $data = is_array($node['data'] ?? null) ? $node['data'] : [];
            $data['label'] = $data['label'] ?? ucfirst(str_replace('_', ' ', $node['type']));
            $data['type'] = $node['type'];

            if ($node['type'] === 'keyword_trigger' && empty($data['keywords'])) {
                $data['keywords'] = [['id' => 'kw1', 'value' => 'hello', 'matchType' => 'contains']];
            }

            if ($node['type'] === 'message' && empty($data['settings']['message'])) {
                $data['settings'] = array_merge($data['settings'] ?? [], [
                    'message' => 'Thank you for contacting us. How can we help?',
                ]);
            }

            $normalized[] = [
                'id' => $id,
                'type' => $node['type'],
                'position' => [
                    'x' => (int) ($node['position']['x'] ?? ($index * 300)),
                    'y' => (int) ($node['position']['y'] ?? 120),
                ],
                'data' => $data,
            ];
        }

        if (count($normalized) < 2) {
            throw new \RuntimeException('LLM flow had too few valid nodes');
        }

        if (! collect($normalized)->contains(fn (array $n) => $n['type'] === 'end')) {
            $normalized[] = [
                'id' => 'end-'.Str::uuid(),
                'type' => 'end',
                'position' => ['x' => count($normalized) * 300, 'y' => 120],
                'data' => ['label' => 'End', 'type' => 'end'],
            ];
        }

        return array_values($normalized);
    }

    /**
     * @param  array<int, array<string, mixed>>  $edges
     * @param  array<int, array<string, mixed>>  $nodes
     * @return array<int, array<string, mixed>>
     */
    private function normalizeEdges(array $edges, array $nodes): array
    {
        $nodeIds = collect($nodes)->pluck('id')->all();
        $normalized = [];

        foreach ($edges as $edge) {
            if (! is_array($edge)) {
                continue;
            }

            $source = $edge['source'] ?? null;
            $target = $edge['target'] ?? null;

            if (! in_array($source, $nodeIds, true) || ! in_array($target, $nodeIds, true)) {
                continue;
            }

            $normalized[] = [
                'id' => (string) ($edge['id'] ?? 'e-'.Str::uuid()),
                'source' => $source,
                'target' => $target,
                'sourceHandle' => $edge['sourceHandle'] ?? null,
            ];
        }

        if ($normalized === [] && count($nodes) >= 2) {
            $normalized[] = [
                'id' => 'e-fallback',
                'source' => $nodes[0]['id'],
                'target' => $nodes[1]['id'],
                'sourceHandle' => $nodes[0]['type'] === 'keyword_trigger' ? 'kw1' : null,
            ];
        }

        return array_values($normalized);
    }

    /**
     * Rule-based fallback when LLM is unavailable.
     *
     * @return array{nodes: array, edges: array, summary: string}
     */
    public function generateRuleBased(string $description): array
    {
        $lower = strtolower($description);
        $keywords = $this->extractKeywords($lower);
        $message = $this->buildMessage($description, $lower);

        $triggerId = 'keyword_trigger-'.uniqid();
        $messageId = 'message-'.uniqid();
        $endId = 'end-'.uniqid();

        $keywordRows = [];
        $edges = [];
        foreach ($keywords as $index => $keyword) {
            $handle = 'kw'.($index + 1);
            $keywordRows[] = ['id' => $handle, 'value' => $keyword, 'matchType' => 'contains'];
            $edges[] = [
                'id' => 'e-'.$handle,
                'source' => $triggerId,
                'target' => $messageId,
                'sourceHandle' => $handle,
            ];
        }

        if (empty($keywordRows)) {
            $keywordRows[] = ['id' => 'kw1', 'value' => 'hello', 'matchType' => 'contains'];
            $edges[] = ['id' => 'e-kw1', 'source' => $triggerId, 'target' => $messageId, 'sourceHandle' => 'kw1'];
        }

        $edges[] = ['id' => 'e-end', 'source' => $messageId, 'target' => $endId];

        $nodes = [
            [
                'id' => $triggerId,
                'type' => 'keyword_trigger',
                'position' => ['x' => 0, 'y' => 120],
                'data' => [
                    'label' => 'On Keyword',
                    'type' => 'keyword_trigger',
                    'keywords' => $keywordRows,
                ],
            ],
            [
                'id' => $messageId,
                'type' => 'message',
                'position' => ['x' => 420, 'y' => 120],
                'data' => [
                    'label' => 'Message',
                    'type' => 'message',
                    'settings' => ['message' => $message],
                ],
            ],
            [
                'id' => $endId,
                'type' => 'end',
                'position' => ['x' => 840, 'y' => 120],
                'data' => ['label' => 'End', 'type' => 'end'],
            ],
        ];

        if ($this->needsShop($lower)) {
            $catalogId = 'whatsapp_catalog-'.uniqid();
            $payId = 'request_payment-'.uniqid();
            $statusId = 'order_status-'.uniqid();
            $nodes[] = [
                'id' => $catalogId,
                'type' => 'whatsapp_catalog',
                'position' => ['x' => 630, 'y' => 40],
                'data' => [
                    'label' => 'Send Catalog',
                    'type' => 'whatsapp_catalog',
                    'settings' => [
                        'catalogId' => '',
                        'header' => 'Browse our products',
                        'displayMode' => 'interactive_list',
                        'autoResumeFlow' => true,
                    ],
                ],
            ];
            $nodes[] = [
                'id' => $payId,
                'type' => 'request_payment',
                'position' => ['x' => 630, 'y' => 200],
                'data' => [
                    'label' => 'Collect Payment',
                    'type' => 'request_payment',
                    'settings' => [
                        'payment' => [
                            'amount' => '{{catalog_order_total_amount}}',
                            'provider' => 'auto',
                            'accountReference' => 'ORDER',
                            'description' => 'Order payment',
                            'responseVar' => 'payment_result',
                        ],
                    ],
                ],
            ];
            $nodes[] = [
                'id' => $statusId,
                'type' => 'order_status',
                'position' => ['x' => 840, 'y' => 200],
                'data' => [
                    'label' => 'Order confirmed',
                    'type' => 'order_status',
                    'settings' => [
                        'status' => 'confirmed',
                        'message' => 'Thanks! Your order is confirmed. Reference: {{order_reference}}',
                    ],
                ],
            ];
            $edges = array_filter($edges, fn ($e) => $e['target'] !== $endId);
            $edges[] = ['id' => 'e-catalog', 'source' => $messageId, 'target' => $catalogId];
            $edges[] = ['id' => 'e-pay', 'source' => $catalogId, 'target' => $payId, 'sourceHandle' => 'onCheckoutComplete'];
            $edges[] = ['id' => 'e-status', 'source' => $payId, 'target' => $statusId, 'sourceHandle' => 'success'];
            $edges[] = ['id' => 'e-end', 'source' => $statusId, 'target' => $endId];
            $nodes = array_map(function ($node) use ($endId) {
                if ($node['id'] === $endId) {
                    $node['position'] = ['x' => 1100, 'y' => 200];
                }

                return $node;
            }, $nodes);
        } elseif ($this->needsPayment($lower)) {
            $payId = 'request_payment-'.uniqid();
            $nodes[] = [
                'id' => $payId,
                'type' => 'request_payment',
                'position' => ['x' => 630, 'y' => 120],
                'data' => [
                    'label' => 'Collect Payment',
                    'type' => 'request_payment',
                    'settings' => [
                        'payment' => [
                            'amount' => '100',
                            'provider' => 'auto',
                            'accountReference' => 'PAYMENT',
                            'description' => 'Payment',
                            'responseVar' => 'payment_result',
                        ],
                    ],
                ],
            ];
            $edges = array_filter($edges, fn ($e) => $e['target'] !== $endId);
            $edges[] = ['id' => 'e-pay', 'source' => $messageId, 'target' => $payId];
            $edges[] = ['id' => 'e-end', 'source' => $payId, 'target' => $endId, 'sourceHandle' => 'success'];
            $nodes = array_map(function ($node) use ($endId) {
                if ($node['id'] === $endId) {
                    $node['position'] = ['x' => 1050, 'y' => 120];
                }

                return $node;
            }, $nodes);
        }

        return [
            'nodes' => array_values($nodes),
            'edges' => array_values($edges),
            'summary' => __('Draft flow with :count keyword trigger(s) and an automated reply. Review in the editor before publishing.', ['count' => count($keywordRows)]),
        ];
    }

    private function extractKeywords(string $lower): array
    {
        $candidates = ['pay', 'payment', 'book', 'order', 'help', 'support', 'price', 'buy', 'interested'];
        $found = array_values(array_filter($candidates, fn ($w) => str_contains($lower, $w)));

        return array_slice($found, 0, 3);
    }

    private function buildMessage(string $original, string $lower): string
    {
        if (str_contains($lower, 'pay') || str_contains($lower, 'mpesa')) {
            return "Thanks for your message. We'll send an M-Pesa payment request shortly. Please have your phone ready.";
        }

        if (str_contains($lower, 'book') || str_contains($lower, 'appointment')) {
            return 'Happy to help you book! Share your preferred date and time, or visit our booking page.';
        }

        if (str_contains($lower, 'order') || str_contains($lower, 'status')) {
            return 'Please share your order number and we will check the status for you.';
        }

        if (str_contains($lower, 'faq') || str_contains($lower, 'support') || str_contains($lower, 'help')) {
            return "I'm here to help. Describe your question and our team will assist you. Type *agent* to reach a human.";
        }

        return mb_substr(trim($original), 0, 500) ?: 'Thank you for contacting us. How can we help you today?';
    }

    private function needsPayment(string $lower): bool
    {
        return str_contains($lower, 'mpesa') || str_contains($lower, 'payment') || str_contains($lower, 'pay');
    }

    private function needsShop(string $lower): bool
    {
        return str_contains($lower, 'shop')
            || str_contains($lower, 'catalog')
            || str_contains($lower, 'store')
            || (str_contains($lower, 'sell') && str_contains($lower, 'product'))
            || (str_contains($lower, 'buy') && str_contains($lower, 'product'));
    }
}
