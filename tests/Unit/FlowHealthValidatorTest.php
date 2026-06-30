<?php

namespace Tests\Unit;

use App\Services\Flowmaker\FlowHealthValidator;
use PHPUnit\Framework\TestCase;

class FlowHealthValidatorTest extends TestCase
{
    public function test_flags_invalid_keyword_source_handles(): void
    {
        $validator = new FlowHealthValidator;

        $result = $validator->validate([
            'nodes' => [
                [
                    'id' => 'keyword_trigger-1',
                    'type' => 'keyword_trigger',
                    'data' => [
                        'settings' => [
                            'keywords' => [
                                ['id' => 'kw1', 'value' => 'shop', 'matchType' => 'contains'],
                            ],
                        ],
                    ],
                ],
                ['id' => 'message-1', 'type' => 'message', 'data' => []],
            ],
            'edges' => [
                ['id' => 'e1', 'source' => 'keyword_trigger-1', 'target' => 'message-1', 'sourceHandle' => 'kw1'],
            ],
        ]);

        $this->assertFalse($result['valid']);
        $this->assertNotEmpty($result['errors']);
    }

    public function test_accepts_keyword_prefixed_handles(): void
    {
        $validator = new FlowHealthValidator;

        $result = $validator->validate([
            'nodes' => [
                [
                    'id' => 'keyword_trigger-1',
                    'type' => 'keyword_trigger',
                    'data' => [
                        'settings' => [
                            'keywords' => [
                                ['id' => 'kw1', 'value' => 'shop', 'matchType' => 'contains'],
                            ],
                        ],
                    ],
                ],
                ['id' => 'message-1', 'type' => 'message', 'data' => []],
                ['id' => 'end-1', 'type' => 'end', 'data' => []],
            ],
            'edges' => [
                ['id' => 'e1', 'source' => 'keyword_trigger-1', 'target' => 'message-1', 'sourceHandle' => 'keyword-kw1'],
                ['id' => 'e2', 'source' => 'message-1', 'target' => 'end-1'],
            ],
        ]);

        $this->assertTrue($result['valid']);
    }

    public function test_warns_when_end_node_has_outgoing_edge(): void
    {
        $validator = new FlowHealthValidator;

        $result = $validator->validate([
            'nodes' => [
                ['id' => 'keyword_trigger-1', 'type' => 'keyword_trigger', 'data' => ['settings' => ['keywords' => []]]],
                ['id' => 'end-1', 'type' => 'end', 'data' => []],
                ['id' => 'message-1', 'type' => 'message', 'data' => []],
            ],
            'edges' => [
                ['id' => 'e1', 'source' => 'end-1', 'target' => 'message-1'],
            ],
        ]);

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('End node', $result['errors'][0]);
    }
}
