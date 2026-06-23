<?php

namespace Tests\Unit;

use Modules\Flowmaker\Models\Nodes\ListMessage;
use Modules\Flowmaker\Models\Nodes\Node;
use PHPUnit\Framework\TestCase;

class FlowNodeInstantiationTest extends TestCase
{
    public function test_list_message_node_can_be_constructed_and_serialized(): void
    {
        $nodeData = [
            'id' => 'list-1',
            'type' => 'list_message',
            'data' => [
                'settings' => [
                    'header' => 'Choose',
                    'body' => 'Pick one',
                    'sections' => [],
                ],
            ],
        ];

        $node = new ListMessage($nodeData, []);
        $node->flow_id = 7;

        $this->assertSame('list-1', $node->id);
        $this->assertSame('list_message', $node->type);
        $this->assertSame(7, $node->flow_id);

        $restored = unserialize(serialize($node));

        $this->assertInstanceOf(ListMessage::class, $restored);
        $this->assertSame('list-1', $restored->id);
        $this->assertSame(7, $restored->flow_id);
    }

    public function test_generic_node_does_not_require_eloquent_bootstrap(): void
    {
        $node = new Node([
            'id' => 'fallback-1',
            'type' => 'unknown',
            'data' => [],
        ], []);

        $this->assertSame('fallback-1', $node->id);
        $this->assertFalse($node->isStartNode);
    }
}
