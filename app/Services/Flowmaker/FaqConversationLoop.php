<?php

namespace App\Services\Flowmaker;

/**
 * Builds a reusable Question → Counter → Pricing → LLM → Follow-up → Branch loop
 * for conversational FAQ paths inside flow templates.
 */
class FaqConversationLoop
{
    /**
     * @param  array{nodes: array<int, array<string, mixed>>, edges: array<int, array<string, mixed>>}  $flowData
     * @param  array<string, mixed>  $config
     * @return array{nodes: array<int, array<string, mixed>>, edges: array<int, array<string, mixed>>}
     */
    public static function mergeInto(array $flowData, array $config): array
    {
        return [
            'nodes' => array_merge($flowData['nodes'], self::nodes($config)),
            'edges' => array_merge($flowData['edges'], self::edges($config)),
        ];
    }

    /**
     * @param  array{
     *     idPrefix: string,
     *     basePosition: array{x: int|float, y: int|float},
     *     questionInitial?: string,
     *     questionFollowup: string,
     *     systemPrompt: string,
     *     prompt?: string,
     *     llmVariableName?: string,
     *     llmLabel?: string,
     *     counterMax?: int,
     *     freeExecutions?: int,
     *     enableVectorSearch?: bool,
     *     vectorSearchLimit?: int,
     *     doneKeywords?: array<int, string>,
     *     humanKeywords?: array<int, string>,
     *     keywordExits?: array<int, array{id: string, keyword: string, target: string}>,
     *     doneTarget: string,
     *     humanTarget: string,
     *     limitTarget?: string,
     *     introMessage?: string|null,
     *     mode?: 'question'|'incoming',
     * }  $config
     * @return array<int, array<string, mixed>>
     */
    public static function nodes(array $config): array
    {
        $prefix = $config['idPrefix'];
        $x = (int) $config['basePosition']['x'];
        $y = (int) $config['basePosition']['y'];
        $mode = $config['mode'] ?? 'question';
        $llmVar = $config['llmVariableName'] ?? 'faq_reply';
        $prompt = $config['prompt'] ?? '{{contact_last_message}}';
        $counterMax = $config['counterMax'] ?? 5;
        $freeExecutions = $config['freeExecutions'] ?? 5;
        $enableVectorSearch = $config['enableVectorSearch'] ?? true;
        $vectorSearchLimit = $config['vectorSearchLimit'] ?? 5;
        $doneKeywords = $config['doneKeywords'] ?? ['done', 'thanks', 'resolved'];
        $humanKeywords = $config['humanKeywords'] ?? ['agent', 'human'];
        $keywordExits = $config['keywordExits'] ?? [];
        $limitTarget = $config['limitTarget'] ?? $config['humanTarget'];

        $nodes = [];
        $offsetY = 0;

        if (! empty($config['introMessage'])) {
            $nodes[] = [
                'id' => "{$prefix}-message-intro",
                'type' => 'message',
                'position' => ['x' => $x, 'y' => $y + $offsetY],
                'data' => [
                    'label' => 'FAQ intro',
                    'type' => 'message',
                    'settings' => ['message' => $config['introMessage']],
                ],
            ];
            $offsetY += 80;
        }

        if ($mode === 'question') {
            $nodes[] = [
                'id' => "{$prefix}-question-initial",
                'type' => 'question',
                'position' => ['x' => $x, 'y' => $y + $offsetY],
                'data' => [
                    'label' => 'FAQ question',
                    'type' => 'question',
                    'settings' => [
                        'question' => $config['questionInitial'] ?? 'What would you like to know?',
                        'variableName' => 'faq_question',
                    ],
                ],
            ];
        }

        $nodes[] = [
            'id' => "{$prefix}-counter",
            'type' => 'counter',
            'position' => ['x' => $x + ($mode === 'incoming' ? 380 : 380), 'y' => $y + $offsetY],
            'data' => [
                'label' => 'FAQ rate limit',
                'type' => 'counter',
                'settings' => [
                    'counter' => [
                        'maxExecutions' => $counterMax,
                        'period' => $mode === 'incoming' ? 'last_30_days' : 'all_time',
                    ],
                ],
            ],
        ];

        $nodes[] = [
            'id' => "{$prefix}-check-pricing",
            'type' => 'check_pricing',
            'position' => ['x' => $x + 760, 'y' => $y + $offsetY],
            'data' => [
                'label' => 'AI credits',
                'type' => 'check_pricing',
                'settings' => [
                    'pricing' => ['freeExecutions' => $freeExecutions],
                ],
            ],
        ];

        $nodes[] = [
            'id' => "{$prefix}-openai",
            'type' => 'openai',
            'position' => ['x' => $x + 1140, 'y' => $y + $offsetY - 40],
            'data' => [
                'label' => $config['llmLabel'] ?? 'FAQ assistant',
                'type' => 'openai',
                'settings' => [
                    'llm' => [
                        'model' => 'openai/gpt-4o-mini',
                        'systemPrompt' => $config['systemPrompt'],
                        'prompt' => $prompt,
                        'temperature' => 0.6,
                        'maxTokens' => 600,
                        'variableName' => $llmVar,
                        'autoSendMessage' => true,
                        'enableVectorSearch' => $enableVectorSearch,
                        'vectorSearchLimit' => $vectorSearchLimit,
                        'similarityThreshold' => 0.3,
                        'intentions' => [],
                    ],
                ],
            ],
        ];

        $nodes[] = [
            'id' => "{$prefix}-question-followup",
            'type' => 'question',
            'position' => ['x' => $x + 1520, 'y' => $y + $offsetY],
            'data' => [
                'label' => 'FAQ follow-up',
                'type' => 'question',
                'settings' => [
                    'question' => $config['questionFollowup'],
                    'variableName' => 'faq_followup',
                ],
            ],
        ];

        $branchY = $y + $offsetY + 200;
        $branchIndex = 0;

        foreach ($doneKeywords as $keywordIndex => $keyword) {
            $nodes[] = self::branchNode(
                "{$prefix}-branch-done-{$keywordIndex}",
                $x + 1900,
                $branchY + ($branchIndex * 120),
                "cond-done-{$keywordIndex}",
                $keyword
            );
            $branchIndex++;
        }

        foreach ($keywordExits as $exitIndex => $exit) {
            $nodes[] = self::branchNode(
                "{$prefix}-branch-exit-{$exitIndex}",
                $x + 1900,
                $branchY + ($branchIndex * 120),
                $exit['id'],
                $exit['keyword']
            );
            $branchIndex++;
        }

        foreach ($humanKeywords as $keywordIndex => $keyword) {
            $nodes[] = self::branchNode(
                "{$prefix}-branch-human-{$keywordIndex}",
                $x + 1900,
                $branchY + ($branchIndex * 120),
                "cond-human-{$keywordIndex}",
                $keyword
            );
            $branchIndex++;
        }

        $nodes[] = [
            'id' => "{$prefix}-message-limit",
            'type' => 'message',
            'position' => ['x' => $x + 760, 'y' => $y + $offsetY + 200],
            'data' => [
                'label' => 'FAQ limit reached',
                'type' => 'message',
                'settings' => [
                    'message' => 'You have reached the FAQ assistant limit. Reply *agent* or *human* to speak with our team.',
                ],
            ],
        ];

        return $nodes;
    }

    /**
     * @param  array{
     *     idPrefix: string,
     *     doneKeywords?: array<int, string>,
     *     humanKeywords?: array<int, string>,
     *     keywordExits?: array<int, array{id: string, keyword: string, target: string}>,
     *     doneTarget: string,
     *     humanTarget: string,
     *     limitTarget?: string,
     *     hasIntro?: bool,
     *     mode?: 'question'|'incoming',
     * }  $config
     * @return array<int, array<string, mixed>>
     */
    public static function edges(array $config): array
    {
        $prefix = $config['idPrefix'];
        $mode = $config['mode'] ?? 'question';
        $doneKeywords = $config['doneKeywords'] ?? ['done', 'thanks', 'resolved'];
        $humanKeywords = $config['humanKeywords'] ?? ['agent', 'human'];
        $keywordExits = $config['keywordExits'] ?? [];
        $limitTarget = $config['limitTarget'] ?? $config['humanTarget'];
        $hasIntro = $config['hasIntro'] ?? false;

        $edges = [];
        $loopTarget = "{$prefix}-counter";

        if ($hasIntro) {
            $edges[] = [
                'id' => "e-{$prefix}-intro-question",
                'source' => "{$prefix}-message-intro",
                'target' => "{$prefix}-question-initial",
            ];
        }

        if ($mode === 'question') {
            $edges[] = [
                'id' => "e-{$prefix}-q-counter",
                'source' => "{$prefix}-question-initial",
                'target' => "{$prefix}-counter",
            ];
        }

        $edges[] = ['id' => "e-{$prefix}-counter-pricing", 'source' => "{$prefix}-counter", 'target' => "{$prefix}-check-pricing", 'sourceHandle' => 'true'];
        $edges[] = ['id' => "e-{$prefix}-counter-limit", 'source' => "{$prefix}-counter", 'target' => "{$prefix}-message-limit", 'sourceHandle' => 'false'];
        $edges[] = ['id' => "e-{$prefix}-pricing-ai", 'source' => "{$prefix}-check-pricing", 'target' => "{$prefix}-openai", 'sourceHandle' => 'true'];
        $edges[] = ['id' => "e-{$prefix}-pricing-limit", 'source' => "{$prefix}-check-pricing", 'target' => "{$prefix}-message-limit", 'sourceHandle' => 'false'];
        $edges[] = ['id' => "e-{$prefix}-ai-followup", 'source' => "{$prefix}-openai", 'target' => "{$prefix}-question-followup"];
        $edges[] = ['id' => "e-{$prefix}-limit-human", 'source' => "{$prefix}-message-limit", 'target' => $limitTarget];

        if (! empty($doneKeywords)) {
            $edges[] = [
                'id' => "e-{$prefix}-followup-branch",
                'source' => "{$prefix}-question-followup",
                'target' => "{$prefix}-branch-done-0",
            ];
        } elseif (! empty($keywordExits)) {
            $edges[] = [
                'id' => "e-{$prefix}-followup-branch",
                'source' => "{$prefix}-question-followup",
                'target' => "{$prefix}-branch-exit-0",
            ];
        } elseif (! empty($humanKeywords)) {
            $edges[] = [
                'id' => "e-{$prefix}-followup-branch",
                'source' => "{$prefix}-question-followup",
                'target' => "{$prefix}-branch-human-0",
            ];
        } else {
            $edges[] = [
                'id' => "e-{$prefix}-followup-loop",
                'source' => "{$prefix}-question-followup",
                'target' => $loopTarget,
            ];
        }

        foreach ($doneKeywords as $keywordIndex => $keyword) {
            $branchId = "{$prefix}-branch-done-{$keywordIndex}";
            $condId = "cond-done-{$keywordIndex}";
            $edges[] = [
                'id' => "e-{$prefix}-done-{$keywordIndex}-exit",
                'source' => $branchId,
                'target' => $config['doneTarget'],
                'sourceHandle' => "condition-{$condId}-true",
            ];
            $edges[] = [
                'id' => "e-{$prefix}-done-{$keywordIndex}-next",
                'source' => $branchId,
                'target' => self::nextBranchAfterDone($prefix, $keywordIndex, $doneKeywords, $keywordExits, $humanKeywords, $loopTarget),
                'sourceHandle' => "condition-{$condId}-false",
            ];
        }

        foreach ($keywordExits as $exitIndex => $exit) {
            $branchId = "{$prefix}-branch-exit-{$exitIndex}";
            $condId = $exit['id'];
            $edges[] = [
                'id' => "e-{$prefix}-exit-{$exitIndex}-target",
                'source' => $branchId,
                'target' => $exit['target'],
                'sourceHandle' => "condition-{$condId}-true",
            ];
            $edges[] = [
                'id' => "e-{$prefix}-exit-{$exitIndex}-next",
                'source' => $branchId,
                'target' => self::nextBranchAfterExit($prefix, $exitIndex, $keywordExits, $humanKeywords, $loopTarget),
                'sourceHandle' => "condition-{$condId}-false",
            ];
        }

        foreach ($humanKeywords as $keywordIndex => $keyword) {
            $branchId = "{$prefix}-branch-human-{$keywordIndex}";
            $condId = "cond-human-{$keywordIndex}";
            $edges[] = [
                'id' => "e-{$prefix}-human-{$keywordIndex}-exit",
                'source' => $branchId,
                'target' => $config['humanTarget'],
                'sourceHandle' => "condition-{$condId}-true",
            ];
            $edges[] = [
                'id' => "e-{$prefix}-human-{$keywordIndex}-loop",
                'source' => $branchId,
                'target' => isset($humanKeywords[$keywordIndex + 1])
                    ? "{$prefix}-branch-human-".($keywordIndex + 1)
                    : $loopTarget,
                'sourceHandle' => "condition-{$condId}-false",
            ];
        }

        return $edges;
    }

    public static function entryNodeId(string $idPrefix, bool $hasIntro = false, string $mode = 'question'): string
    {
        if ($mode === 'incoming') {
            return "{$idPrefix}-counter";
        }

        return $hasIntro ? "{$idPrefix}-message-intro" : "{$idPrefix}-question-initial";
    }

    /**
     * @param  array<int, string>  $doneKeywords
     * @param  array<int, array{id: string, keyword: string, target: string}>  $keywordExits
     * @param  array<int, string>  $humanKeywords
     */
    private static function nextBranchAfterDone(
        string $prefix,
        int $keywordIndex,
        array $doneKeywords,
        array $keywordExits,
        array $humanKeywords,
        string $loopTarget
    ): string {
        if (isset($doneKeywords[$keywordIndex + 1])) {
            return "{$prefix}-branch-done-".($keywordIndex + 1);
        }

        if (! empty($keywordExits)) {
            return "{$prefix}-branch-exit-0";
        }

        if (! empty($humanKeywords)) {
            return "{$prefix}-branch-human-0";
        }

        return $loopTarget;
    }

    /**
     * @param  array<int, array{id: string, keyword: string, target: string}>  $keywordExits
     * @param  array<int, string>  $humanKeywords
     */
    private static function nextBranchAfterExit(
        string $prefix,
        int $exitIndex,
        array $keywordExits,
        array $humanKeywords,
        string $loopTarget
    ): string {
        if (isset($keywordExits[$exitIndex + 1])) {
            return "{$prefix}-branch-exit-".($exitIndex + 1);
        }

        if (! empty($humanKeywords)) {
            return "{$prefix}-branch-human-0";
        }

        return $loopTarget;
    }

    /**
     * @return array<string, mixed>
     */
    private static function branchNode(string $id, int $x, int $y, string $condId, string $keyword): array
    {
        return [
            'id' => $id,
            'type' => 'branch',
            'position' => ['x' => $x, 'y' => $y],
            'data' => [
                'label' => 'FAQ route',
                'type' => 'branch',
                'settings' => [
                    'webhookVariables' => [],
                    'conditions' => [
                        [
                            'id' => $condId,
                            'nodeId' => $id,
                            'variableId' => 'contact_last_message',
                            'operator' => 'contains',
                            'value' => $keyword,
                        ],
                    ],
                ],
            ],
        ];
    }
}
