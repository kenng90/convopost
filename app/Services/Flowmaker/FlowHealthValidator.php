<?php

namespace App\Services\Flowmaker;

class FlowHealthValidator
{
    private const TRIGGER_TYPES = ['keyword_trigger', 'incomingMessage', 'incoming_message', 'template', 'opening_hours'];

    private const EXECUTABLE_TYPES = [
        'keyword_trigger', 'incomingMessage', 'incoming_message', 'message', 'image', 'pdf', 'video',
        'template', 'quick_replies', 'list_message', 'branch', 'openai', 'question', 'http',
        'whatsapp_catalog', 'listing_inquiry', 'whatsapp_flow', 'counter', 'check_pricing',
        'assign_agent', 'assign_group', 'assign_journey_stage', 'mpesa_stk_push', 'set_variable',
        'book_appointment', 'booking_events_list', 'booking_event_register', 'opening_hours', 'webhook', 'wait',
    ];

    /**
     * @param  array<string, mixed>  $flowData
     * @return array{valid: bool, errors: array<int, string>, warnings: array<int, string>}
     */
    public function validate(array $flowData): array
    {
        $errors = [];
        $warnings = [];

        $nodes = $flowData['nodes'] ?? [];
        $edges = $flowData['edges'] ?? [];

        if (empty($nodes)) {
            return [
                'valid' => false,
                'errors' => ['Flow has no nodes.'],
                'warnings' => [],
            ];
        }

        $nodeIds = [];
        $nodeTypes = [];

        foreach ($nodes as $node) {
            $id = $node['id'] ?? null;
            $type = $node['type'] ?? ($node['data']['type'] ?? null);

            if (! $id) {
                $errors[] = 'A node is missing an id.';

                continue;
            }

            $nodeIds[] = $id;
            $nodeTypes[$id] = $type;

            if ($type === 'openai') {
                $llm = $node['data']['settings']['llm'] ?? $node['data']['settings']['openai'] ?? [];
                $autoSend = $llm['autoSendMessage'] ?? true;
                $variableName = $llm['variableName'] ?? 'ai_response';

                if (! $autoSend) {
                    $hasOutgoing = collect($edges)->contains(fn ($e) => ($e['source'] ?? '') === $id);
                    if (! $hasOutgoing) {
                        $warnings[] = "LLM node [{$id}] has auto-send disabled but no outgoing connection to send the reply.";
                    }
                }

                if (empty($variableName)) {
                    $warnings[] = "LLM node [{$id}] has no variable name configured.";
                }
            }

            if ($type === 'keyword_trigger') {
                $keywords = $node['data']['settings']['keywords'] ?? [];
                foreach ($keywords as $keyword) {
                    $keywordId = $keyword['id'] ?? null;
                    if (! $keywordId) {
                        continue;
                    }

                    $expectedHandle = 'keyword-'.$keywordId;
                    $connected = collect($edges)->first(function ($edge) use ($id, $keywordId, $expectedHandle) {
                        if (($edge['source'] ?? '') !== $id) {
                            return false;
                        }

                        $handle = $edge['sourceHandle'] ?? null;

                        return $handle === $expectedHandle || $handle === $keywordId;
                    });

                    if (! $connected) {
                        $label = $keyword['value'] ?? $keywordId;
                        $warnings[] = "Keyword [{$label}] on node [{$id}] has no outgoing connection.";
                    } elseif (collect($edges)->contains(fn ($e) => ($e['source'] ?? '') === $id && ($e['sourceHandle'] ?? '') === $keywordId)) {
                        $errors[] = "Keyword edge on [{$id}] uses handle [{$keywordId}] but should use [{$expectedHandle}].";
                    }
                }
            }
        }

        foreach ($edges as $edge) {
            $source = $edge['source'] ?? null;
            $target = $edge['target'] ?? null;

            if ($source && ! in_array($source, $nodeIds, true)) {
                $errors[] = "Edge references missing source node [{$source}].";
            }

            if ($target && ! in_array($target, $nodeIds, true)) {
                $errors[] = "Edge references missing target node [{$target}].";
            }

            if ($source && ($nodeTypes[$source] ?? null) === 'end') {
                $errors[] = "End node [{$source}] cannot have outgoing connections.";
            }
        }

        $triggers = array_filter($nodeIds, fn ($id) => in_array($nodeTypes[$id] ?? '', self::TRIGGER_TYPES, true));
        if (empty($triggers)) {
            $warnings[] = 'Flow has no trigger node (keyword, incoming message, or template).';
        }

        $reachable = $this->reachableNodeIds($nodeIds, $edges, $triggers);
        foreach ($nodeIds as $nodeId) {
            $type = $nodeTypes[$nodeId] ?? '';
            if (in_array($type, self::EXECUTABLE_TYPES, true) && ! in_array($nodeId, $reachable, true)) {
                $warnings[] = "Node [{$nodeId}] ({$type}) is not reachable from any trigger.";
            }
        }

        $hasEnd = collect($nodeTypes)->contains(fn ($type) => $type === 'end');
        if (! $hasEnd) {
            $warnings[] = 'Flow has no End node — conversations may not clear state.';
        }

        return [
            'valid' => empty($errors),
            'errors' => array_values(array_unique($errors)),
            'warnings' => array_values(array_unique($warnings)),
        ];
    }

    /**
     * @param  array<int, string>  $nodeIds
     * @param  array<int, array<string, mixed>>  $edges
     * @param  array<int, string>  $startIds
     * @return array<int, string>
     */
    private function reachableNodeIds(array $nodeIds, array $edges, array $startIds): array
    {
        $adjacency = [];
        foreach ($edges as $edge) {
            $source = $edge['source'] ?? null;
            $target = $edge['target'] ?? null;
            if ($source && $target) {
                $adjacency[$source][] = $target;
            }
        }

        $visited = [];
        $queue = array_values($startIds);

        while (! empty($queue)) {
            $current = array_shift($queue);
            if (in_array($current, $visited, true)) {
                continue;
            }
            $visited[] = $current;

            foreach ($adjacency[$current] ?? [] as $next) {
                if (! in_array($next, $visited, true)) {
                    $queue[] = $next;
                }
            }
        }

        return $visited;
    }
}
