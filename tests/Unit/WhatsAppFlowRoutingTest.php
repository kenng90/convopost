<?php

namespace Tests\Unit;

use Modules\Flowmaker\Models\Nodes\Edge;
use Modules\Flowmaker\Models\Nodes\End;
use Modules\Flowmaker\Models\Nodes\WhatsAppFlow as WhatsAppFlowNode;
use ReflectionMethod;
use Tests\TestCase;

class WhatsAppFlowRoutingTest extends TestCase
{
    public function test_matched_condition_falls_back_to_on_flow_completed_when_condition_handle_missing(): void
    {
        $formNode = new WhatsAppFlowNode([
            'id' => 'whatsapp_flow-1',
            'type' => 'whatsapp_flow',
            'data' => [
                'settings' => [
                    'conditions' => [
                        ['fieldName' => 'notes', 'operator' => '==', 'value' => 'ken'],
                    ],
                ],
            ],
        ], []);

        $bookNode = new End(['id' => 'book_appointment-1', 'type' => 'end', 'data' => []], []);
        $elseNode = new End(['id' => 'message-no-match', 'type' => 'end', 'data' => []], []);

        $completedEdge = new Edge([
            'id' => 'e-done-book',
            'source' => 'whatsapp_flow-1',
            'target' => 'book_appointment-1',
            'sourceHandle' => 'onFlowCompleted',
        ]);
        $completedEdge->setTarget($bookNode);

        $elseEdge = new Edge([
            'id' => 'e-else',
            'source' => 'whatsapp_flow-1',
            'target' => 'message-no-match',
            'sourceHandle' => 'else',
        ]);
        $elseEdge->setTarget($elseNode);

        $formNode->addOutgoingEdge($completedEdge);
        $formNode->addOutgoingEdge($elseEdge);

        $method = new ReflectionMethod(WhatsAppFlowNode::class, 'resolveConditionRouteHandle');
        $method->setAccessible(true);

        $handle = $method->invoke($formNode, [
            ['fieldName' => 'notes', 'operator' => '==', 'value' => 'ken'],
        ], ['notes' => 'ken']);

        $this->assertSame('onFlowCompleted', $handle);
    }

    public function test_unmatched_condition_routes_to_else(): void
    {
        $formNode = new WhatsAppFlowNode([
            'id' => 'whatsapp_flow-1',
            'type' => 'whatsapp_flow',
            'data' => ['settings' => ['conditions' => []]],
        ], []);

        $elseNode = new End(['id' => 'message-no-match', 'type' => 'end', 'data' => []], []);
        $elseEdge = new Edge([
            'id' => 'e-else',
            'source' => 'whatsapp_flow-1',
            'target' => 'message-no-match',
            'sourceHandle' => 'else',
        ]);
        $elseEdge->setTarget($elseNode);
        $formNode->addOutgoingEdge($elseEdge);

        $method = new ReflectionMethod(WhatsAppFlowNode::class, 'resolveConditionRouteHandle');
        $method->setAccessible(true);

        $handle = $method->invoke($formNode, [
            ['fieldName' => 'notes', 'operator' => '==', 'value' => 'ken'],
        ], ['notes' => 'other']);

        $this->assertSame('else', $handle);
    }

    public function test_matched_condition_uses_dedicated_condition_handle_when_connected(): void
    {
        $formNode = new WhatsAppFlowNode([
            'id' => 'whatsapp_flow-1',
            'type' => 'whatsapp_flow',
            'data' => ['settings' => ['conditions' => []]],
        ], []);

        $matchNode = new End(['id' => 'match-node', 'type' => 'end', 'data' => []], []);
        $matchEdge = new Edge([
            'id' => 'e-match',
            'source' => 'whatsapp_flow-1',
            'target' => 'match-node',
            'sourceHandle' => 'condition_0',
        ]);
        $matchEdge->setTarget($matchNode);
        $formNode->addOutgoingEdge($matchEdge);

        $method = new ReflectionMethod(WhatsAppFlowNode::class, 'resolveConditionRouteHandle');
        $method->setAccessible(true);

        $handle = $method->invoke($formNode, [
            ['fieldName' => 'notes', 'operator' => '==', 'value' => 'ken'],
        ], ['notes' => 'ken']);

        $this->assertSame('condition_0', $handle);
    }
}
