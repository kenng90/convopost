<?php

namespace App\Services\Flowmaker;

use App\Models\WhatsappFlow;
use InvalidArgumentException;
use Modules\Flowmaker\Models\Flow;

class WhatsappFormAutomationFactory
{
    public const RECIPES = ['lead', 'book', 'checkout'];

    /**
     * Create a draft Flowmaker automation bound to a Live WhatsApp Form.
     *
     * @throws InvalidArgumentException
     */
    public function createFromForm(WhatsappFlow $form, string $recipe = 'lead', ?int $companyId = null): Flow
    {
        $recipe = strtolower($recipe);
        if (! in_array($recipe, self::RECIPES, true)) {
            throw new InvalidArgumentException('Invalid recipe. Use lead, book, or checkout.');
        }

        if (empty($form->meta_flow_id)) {
            throw new InvalidArgumentException('Form must be Live on WhatsApp before it can be used in automation.');
        }

        $companyId = $companyId ?: (int) $form->company_id;
        $flowData = $this->buildFlowData($form, $recipe);

        $flow = Flow::create([
            'name' => $this->nameFor($form, $recipe),
            'company_id' => $companyId,
            'priority' => 10,
            'exclusive_on_match' => true,
            'is_active' => true,
        ]);

        $encoded = json_encode($flowData);
        $flow->flow_data = $encoded;
        $flow->draft_flow_data = $encoded;
        $flow->has_unpublished_changes = true;
        $flow->source_template = 'whatsapp_form_'.$recipe;
        $flow->save();

        return $flow;
    }

    /**
     * @return array{nodes: list<array<string, mixed>>, edges: list<array<string, mixed>>}
     */
    public function buildFlowData(WhatsappFlow $form, string $recipe): array
    {
        $keyword = match ($recipe) {
            'book' => 'book',
            'checkout' => 'order',
            default => 'form',
        };

        $nodes = [
            [
                'id' => 'keyword_trigger-1',
                'type' => 'keyword_trigger',
                'position' => ['x' => 0, 'y' => 200],
                'data' => [
                    'label' => 'On Keyword',
                    'type' => 'keyword_trigger',
                    'keywords' => [
                        ['id' => 'kw1', 'value' => $keyword, 'matchType' => 'contains'],
                    ],
                ],
            ],
            [
                'id' => 'whatsapp_flow-1',
                'type' => 'whatsapp_flow',
                'position' => ['x' => 380, 'y' => 200],
                'data' => [
                    'label' => 'Collect with WhatsApp Form',
                    'type' => 'whatsapp_flow',
                    'settings' => [
                        'whatsappFlowId' => (int) $form->id,
                        'header' => 'Complete the form',
                        'footer' => 'Your responses help us serve you better',
                        'conditions' => [],
                        'fieldMappings' => [],
                        'onComplete' => [
                            'groupId' => 'none',
                            'journeyId' => 'none',
                            'stageId' => 'none',
                        ],
                    ],
                ],
            ],
            [
                'id' => 'message-abandon',
                'type' => 'message',
                'position' => ['x' => 760, 'y' => 420],
                'data' => [
                    'label' => 'Follow up',
                    'type' => 'message',
                    'settings' => [
                        'message' => 'We noticed you did not finish the form. Reply *'.$keyword.'* to try again, or wait for an agent.',
                    ],
                ],
            ],
            [
                'id' => 'assign_agent-1',
                'type' => 'assign_agent',
                'position' => ['x' => 1140, 'y' => 420],
                'data' => [
                    'label' => 'Assign agent',
                    'type' => 'assign_agent',
                    'settings' => ['agentId' => 'none'],
                ],
            ],
            [
                'id' => 'end-abandon',
                'type' => 'end',
                'position' => ['x' => 1520, 'y' => 420],
                'data' => ['label' => 'End', 'type' => 'end'],
            ],
            [
                'id' => 'end-1',
                'type' => 'end',
                'position' => ['x' => 1900, 'y' => 200],
                'data' => ['label' => 'End', 'type' => 'end'],
            ],
        ];

        $edges = [
            ['id' => 'e-kw', 'source' => 'keyword_trigger-1', 'target' => 'whatsapp_flow-1', 'sourceHandle' => 'keyword-kw1'],
            ['id' => 'e-abandon', 'source' => 'whatsapp_flow-1', 'target' => 'message-abandon', 'sourceHandle' => 'onAbandoned'],
            ['id' => 'e-else', 'source' => 'whatsapp_flow-1', 'target' => 'message-abandon', 'sourceHandle' => 'else'],
            ['id' => 'e-abandon-agent', 'source' => 'message-abandon', 'target' => 'assign_agent-1'],
            ['id' => 'e-agent-end', 'source' => 'assign_agent-1', 'target' => 'end-abandon'],
        ];

        if ($recipe === 'book') {
            $nodes = array_merge($nodes, [
                [
                    'id' => 'book_appointment-1',
                    'type' => 'book_appointment',
                    'position' => ['x' => 760, 'y' => 120],
                    'data' => [
                        'label' => 'Book appointment',
                        'type' => 'book_appointment',
                        'settings' => [
                            'source_name' => 'Form booking',
                            'duration_minutes' => '60',
                            'header' => 'Book your visit',
                            'body' => 'Choose a date and time.',
                            'buttonText' => 'Choose slot',
                            'success_message' => 'Thanks! Your appointment is confirmed.',
                        ],
                    ],
                ],
                [
                    'id' => 'assign_group-1',
                    'type' => 'assign_group',
                    'position' => ['x' => 1140, 'y' => 120],
                    'data' => [
                        'label' => 'Bookings team',
                        'type' => 'assign_group',
                        'settings' => ['groupId' => 'none', 'action' => 'add'],
                    ],
                ],
            ]);
            $edges[] = ['id' => 'e-done-book', 'source' => 'whatsapp_flow-1', 'target' => 'book_appointment-1', 'sourceHandle' => 'onFlowCompleted'];
            $edges[] = ['id' => 'e-book-group', 'source' => 'book_appointment-1', 'target' => 'assign_group-1'];
            $edges[] = ['id' => 'e-group-end', 'source' => 'assign_group-1', 'target' => 'end-1'];
        } elseif ($recipe === 'checkout') {
            $nodes = array_merge($nodes, [
                [
                    'id' => 'request_payment-1',
                    'type' => 'request_payment',
                    'position' => ['x' => 760, 'y' => 120],
                    'data' => [
                        'label' => 'Collect payment',
                        'type' => 'request_payment',
                        'settings' => [
                            'payment' => [
                                'amount' => '1000',
                                'provider' => 'auto',
                                'accountReference' => 'FORM-ORDER',
                                'description' => 'Order payment',
                            ],
                        ],
                    ],
                ],
                [
                    'id' => 'order_status-1',
                    'type' => 'order_status',
                    'position' => ['x' => 1140, 'y' => 120],
                    'data' => [
                        'label' => 'Confirm order',
                        'type' => 'order_status',
                        'settings' => [
                            'status' => 'confirmed',
                            'message' => 'Payment received! Order status: {{order_status}}. Ref: {{order_reference}}',
                            'journeyId' => 'none',
                            'stageId' => 'none',
                        ],
                    ],
                ],
                [
                    'id' => 'assign_group-1',
                    'type' => 'assign_group',
                    'position' => ['x' => 1520, 'y' => 120],
                    'data' => [
                        'label' => 'Fulfillment team',
                        'type' => 'assign_group',
                        'settings' => ['groupId' => 'none', 'action' => 'add'],
                    ],
                ],
            ]);
            $edges[] = ['id' => 'e-done-pay', 'source' => 'whatsapp_flow-1', 'target' => 'request_payment-1', 'sourceHandle' => 'onFlowCompleted'];
            $edges[] = ['id' => 'e-pay-ok', 'source' => 'request_payment-1', 'target' => 'order_status-1', 'sourceHandle' => 'success'];
            $edges[] = ['id' => 'e-pay-fail', 'source' => 'request_payment-1', 'target' => 'message-abandon', 'sourceHandle' => 'failed'];
            $edges[] = ['id' => 'e-status-group', 'source' => 'order_status-1', 'target' => 'assign_group-1'];
            $edges[] = ['id' => 'e-group-end', 'source' => 'assign_group-1', 'target' => 'end-1'];
        } else {
            $nodes = array_merge($nodes, [
                [
                    'id' => 'assign_group-1',
                    'type' => 'assign_group',
                    'position' => ['x' => 760, 'y' => 120],
                    'data' => [
                        'label' => 'Leads team',
                        'type' => 'assign_group',
                        'settings' => ['groupId' => 'none', 'action' => 'add'],
                    ],
                ],
                [
                    'id' => 'message-thanks',
                    'type' => 'message',
                    'position' => ['x' => 1140, 'y' => 120],
                    'data' => [
                        'label' => 'Thanks',
                        'type' => 'message',
                        'settings' => [
                            'message' => 'Thanks! We received your details and will follow up shortly.',
                        ],
                    ],
                ],
            ]);
            $edges[] = ['id' => 'e-done-group', 'source' => 'whatsapp_flow-1', 'target' => 'assign_group-1', 'sourceHandle' => 'onFlowCompleted'];
            $edges[] = ['id' => 'e-group-thanks', 'source' => 'assign_group-1', 'target' => 'message-thanks'];
            $edges[] = ['id' => 'e-thanks-end', 'source' => 'message-thanks', 'target' => 'end-1'];
        }

        return ['nodes' => $nodes, 'edges' => $edges];
    }

    private function nameFor(WhatsappFlow $form, string $recipe): string
    {
        $suffix = match ($recipe) {
            'book' => 'Book',
            'checkout' => 'Checkout',
            default => 'Lead',
        };

        return $form->name.' — '.$suffix.' automation';
    }
}
