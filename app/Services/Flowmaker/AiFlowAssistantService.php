<?php

namespace App\Services\Flowmaker;

class AiFlowAssistantService
{
    /**
     * Generate a draft flow from natural language (rule-based MVP).
     *
     * @return array{nodes: array, edges: array, summary: string}
     */
    public function generate(string $description): array
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

        if ($this->needsPayment($lower)) {
            $mpesaId = 'mpesa_stk_push-'.uniqid();
            $nodes[] = [
                'id' => $mpesaId,
                'type' => 'mpesa_stk_push',
                'position' => ['x' => 630, 'y' => 120],
                'data' => [
                    'label' => 'MPesa STK Push',
                    'type' => 'mpesa_stk_push',
                    'settings' => [
                        'mpesa' => [
                            'amount' => '100',
                            'accountReference' => 'PAYMENT',
                            'transactionDesc' => 'Payment',
                            'responseVar' => 'mpesa_result',
                        ],
                    ],
                ],
            ];
            $edges = array_filter($edges, fn ($e) => $e['target'] !== $endId);
            $edges[] = ['id' => 'e-mpesa', 'source' => $messageId, 'target' => $mpesaId];
            $edges[] = ['id' => 'e-end', 'source' => $mpesaId, 'target' => $endId];
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
}
