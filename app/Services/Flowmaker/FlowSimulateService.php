<?php

namespace App\Services\Flowmaker;

class FlowSimulateService
{
    /**
     * Dry-run walk of a flow graph for a scenario (no WhatsApp sends).
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function simulate(array $payload, string $message, string $scenario = 'keyword'): array
    {
        $nodes = $payload['nodes'] ?? [];
        $edges = $payload['edges'] ?? [];
        $byId = [];
        foreach ($nodes as $node) {
            $byId[$node['id']] = $node;
        }

        $matchedKeywords = [];
        $startId = null;

        foreach ($nodes as $node) {
            if (($node['type'] ?? '') !== 'keyword_trigger') {
                continue;
            }
            foreach ($node['data']['keywords'] ?? $node['data']['settings']['keywords'] ?? [] as $keyword) {
                $value = strtolower((string) ($keyword['value'] ?? ''));
                $matchType = $keyword['matchType'] ?? 'contains';
                $haystack = strtolower($message);
                $matches = $matchType === 'exact'
                    ? $haystack === $value
                    : ($value !== '' && str_contains($haystack, $value));
                if ($matches) {
                    $matchedKeywords[] = $keyword['value'];
                    $startId = $node['id'];
                }
            }
        }

        if (! $startId) {
            foreach ($nodes as $node) {
                if (in_array($node['type'] ?? '', ['incomingMessage', 'incoming_message'], true)) {
                    $startId = $node['id'];
                    break;
                }
            }
        }

        $path = [];
        $variables = [];
        $current = $startId;
        $visited = 0;
        $preferredHandle = match ($scenario) {
            'catalog_select' => 'onProductSelected',
            'payment_success' => 'success',
            'payment_failed' => 'failed',
            default => null,
        };

        while ($current && isset($byId[$current]) && $visited < 40) {
            $node = $byId[$current];
            $type = $node['type'] ?? '';
            $label = $node['data']['label'] ?? $type;
            $path[] = [
                'id' => $current,
                'type' => $type,
                'label' => $label,
            ];

            if ($type === 'whatsapp_catalog') {
                $variables['catalog_id'] = $node['data']['settings']['catalogId'] ?? null;
                if ($scenario === 'catalog_select') {
                    $variables['selected_product'] = ['id' => 'sim-product', 'title' => 'Simulated product'];
                }
                if (in_array($scenario, ['payment_success', 'payment_failed', 'catalog_select'], true)) {
                    $variables['catalog_order_total_amount'] = '100';
                }
            }

            if ($type === 'request_payment') {
                $variables['payment_result_status'] = $scenario === 'payment_failed' ? 'failed' : 'success';
            }

            if ($type === 'order_status') {
                $variables['order_status'] = $node['data']['settings']['status'] ?? 'confirmed';
            }

            if ($type === 'end') {
                break;
            }

            $next = $this->nextNodeId($edges, $current, $preferredHandle, $type, $scenario);
            $current = $next;
            $visited++;
        }

        return [
            'message' => $message,
            'scenario' => $scenario,
            'matched_keywords' => array_values(array_unique($matchedKeywords)),
            'would_start' => $startId !== null,
            'path' => $path,
            'variables' => $variables,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $edges
     */
    private function nextNodeId(array $edges, string $sourceId, ?string $preferredHandle, string $type, string $scenario): ?string
    {
        $candidates = array_values(array_filter($edges, fn ($e) => ($e['source'] ?? '') === $sourceId));
        if ($candidates === []) {
            return null;
        }

        if ($preferredHandle) {
            foreach ($candidates as $edge) {
                if (($edge['sourceHandle'] ?? '') === $preferredHandle) {
                    return $edge['target'] ?? null;
                }
            }
        }

        if ($type === 'whatsapp_catalog' && $scenario === 'catalog_select') {
            foreach (['onProductSelected', 'onCheckoutComplete'] as $handle) {
                foreach ($candidates as $edge) {
                    if (($edge['sourceHandle'] ?? '') === $handle) {
                        return $edge['target'] ?? null;
                    }
                }
            }
        }

        if ($type === 'request_payment') {
            $handle = $scenario === 'payment_failed' ? 'failed' : 'success';
            foreach ($candidates as $edge) {
                if (($edge['sourceHandle'] ?? '') === $handle) {
                    return $edge['target'] ?? null;
                }
            }
        }

        if ($type === 'keyword_trigger') {
            return $candidates[0]['target'] ?? null;
        }

        foreach ($candidates as $edge) {
            $handle = $edge['sourceHandle'] ?? '';
            if ($handle === '' || $handle === 'default') {
                return $edge['target'] ?? null;
            }
        }

        return $candidates[0]['target'] ?? null;
    }
}
