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
        'book_appointment', 'booking_events_list', 'booking_event_register', 'send_booking_link', 'manage_booking', 'opening_hours', 'webhook', 'wait',
    ];

    /**
     * @param  array<string, mixed>  $flowData
     * @param  array{pending_form_bundle?: bool}  $options
     * @return array{valid: bool, errors: array<int, string>, warnings: array<int, string>}
     */
    public function validate(array $flowData, array $options = []): array
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

                if ($autoSend) {
                    foreach ($edges as $edge) {
                        if (($edge['source'] ?? '') !== $id) {
                            continue;
                        }

                        $targetId = $edge['target'] ?? '';
                        $targetType = $nodeTypes[$targetId] ?? null;
                        if ($targetType !== 'message') {
                            continue;
                        }

                        $targetNode = collect($nodes)->firstWhere('id', $targetId);
                        $messageText = $targetNode['data']['settings']['message'] ?? '';
                        $echoPatterns = [
                            '{{'.$variableName.'}}',
                            '{{'.$variableName.'_message}}',
                        ];

                        if (in_array(trim($messageText), $echoPatterns, true)) {
                            $warnings[] = "LLM node [{$id}] auto-sends and is followed by Message [{$targetId}] echoing the same variable — customers may get duplicate replies.";
                        }
                    }
                }
            }

            if ($type === 'whatsapp_catalog') {
                $catalogId = $node['data']['settings']['catalogId'] ?? '';
                if ($catalogId === '' || $catalogId === '1') {
                    $warnings[] = "Catalog node [{$id}] needs a real catalog ID before publish.";
                }
            }

            if ($type === 'listing_inquiry') {
                $catalogId = $node['data']['settings']['catalogId'] ?? '';
                if ($catalogId === '' || $catalogId === '1') {
                    $warnings[] = "Listing inquiry node [{$id}] needs a listing-mode catalog ID before publish.";
                }

                $bookingBackend = (string) ($node['data']['settings']['bookingBackend'] ?? 'whatsapp_only');
                if ($bookingBackend === 'reminders') {
                    $warnings[] = "Listing inquiry node [{$id}] uses Reminders backend — link each listing item to a bookable service in Catalog settings.";
                }
            }

            if ($type === 'book_appointment') {
                if (! $this->handleConnected($edges, $id, 'error')) {
                    $warnings[] = "Book appointment node [{$id}] should wire the Error output for failures.";
                }
                if (! $this->handleConnected($edges, $id, 'unavailable')) {
                    $warnings[] = "Book appointment node [{$id}] should wire the Unavailable output when no dates exist.";
                }
            }

            if ($type === 'booking_events_list') {
                if (! $this->handleConnected($edges, $id, 'selected')) {
                    $warnings[] = "List events node [{$id}] should wire the Selected output to a Register for event node.";
                }
                if (! $this->handleConnected($edges, $id, 'empty')) {
                    $warnings[] = "List events node [{$id}] should wire the Empty output when no events are published.";
                }
            }

            if ($type === 'booking_event_register') {
                if (! $this->handleConnected($edges, $id, 'error')) {
                    $warnings[] = "Register for event node [{$id}] should wire the Error output.";
                }
            }

            if ($type === 'manage_booking') {
                if (! $this->handleConnected($edges, $id, 'not_found')) {
                    $warnings[] = "Manage booking node [{$id}] should wire the Not found output.";
                }
            }

            if ($type === 'send_booking_link') {
                $linkType = trim((string) ($node['data']['settings']['link_type'] ?? ''));
                if ($linkType === '') {
                    $warnings[] = "Send booking link node [{$id}] needs a link type before publish.";
                }
            }

            if ($type === 'http') {
                $url = $node['data']['settings']['http']['url'] ?? '';
                if (is_string($url) && str_contains($url, 'example.com')) {
                    $warnings[] = "HTTP node [{$id}] still uses a placeholder API URL.";
                }
            }

            if ($type === 'whatsapp_flow') {
                $whatsappFlowId = $node['data']['settings']['whatsappFlowId'] ?? '';
                $pendingFormBundle = (bool) ($options['pending_form_bundle'] ?? false);
                if ($whatsappFlowId === '' || $whatsappFlowId === null) {
                    if ($pendingFormBundle) {
                        $warnings[] = "WhatsApp Form node [{$id}] will be linked from the bundled form on install.";
                    } else {
                        $errors[] = "WhatsApp Form node [{$id}] has no form selected.";
                    }
                }
                if (! $this->handleConnected($edges, $id, 'onFlowCompleted')) {
                    $warnings[] = "WhatsApp Form node [{$id}] should wire the Completed output.";
                }
                if (! $this->handleConnected($edges, $id, 'else')) {
                    $warnings[] = "WhatsApp Form node [{$id}] should wire the Abandoned/No match output for follow-ups.";
                }
            }

            if ($type === 'keyword_trigger') {
                $keywords = $node['data']['keywords']
                    ?? $node['data']['settings']['keywords']
                    ?? [];
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

    /**
     * @param  array<int, array<string, mixed>>  $edges
     */
    private function handleConnected(array $edges, string $nodeId, string $handle): bool
    {
        return collect($edges)->contains(function ($edge) use ($nodeId, $handle) {
            return ($edge['source'] ?? '') === $nodeId
                && ($edge['sourceHandle'] ?? '') === $handle
                && ! empty($edge['target']);
        });
    }
}
