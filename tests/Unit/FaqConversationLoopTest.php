<?php

namespace Tests\Unit;

use App\Services\Flowmaker\FaqConversationLoop;
use App\Services\Flowmaker\FlowHealthValidator;
use Tests\TestCase;

class FaqConversationLoopTest extends TestCase
{
    public function test_builder_produces_valid_graph_for_question_mode(): void
    {
        $flowData = FaqConversationLoop::mergeInto([
            'nodes' => [
                [
                    'id' => 'keyword_trigger-1',
                    'type' => 'keyword_trigger',
                    'position' => ['x' => 0, 'y' => 0],
                    'data' => [
                        'label' => 'On Keyword',
                        'type' => 'keyword_trigger',
                        'keywords' => [['id' => 'kw1', 'value' => 'help', 'matchType' => 'contains']],
                    ],
                ],
                [
                    'id' => 'end-1',
                    'type' => 'end',
                    'position' => ['x' => 0, 'y' => 0],
                    'data' => ['label' => 'End', 'type' => 'end'],
                ],
            ],
            'edges' => [
                ['id' => 'e-kw', 'source' => 'keyword_trigger-1', 'target' => 'demo-faq-question-initial', 'sourceHandle' => 'keyword-kw1'],
            ],
        ], [
            'idPrefix' => 'demo-faq',
            'basePosition' => ['x' => 100, 'y' => 100],
            'questionInitial' => 'What is your question?',
            'questionFollowup' => 'Anything else? Reply *done* or *agent*.',
            'systemPrompt' => 'Be helpful.',
            'doneTarget' => 'end-1',
            'humanTarget' => 'end-1',
        ]);

        $this->assertNotEmpty($flowData['nodes']);
        $this->assertNotEmpty($flowData['edges']);

        $nodeIds = collect($flowData['nodes'])->pluck('id')->all();
        $this->assertContains('demo-faq-question-initial', $nodeIds);
        $this->assertContains('demo-faq-openai', $nodeIds);
        $this->assertContains('demo-faq-question-followup', $nodeIds);

        $validator = new FlowHealthValidator;
        $result = $validator->validate($flowData);
        $this->assertTrue($result['valid'], implode('; ', $result['errors']));
    }

    public function test_builder_supports_incoming_mode_for_voice_flows(): void
    {
        $flowData = FaqConversationLoop::mergeInto([
            'nodes' => [
                [
                    'id' => 'incomingMessage-1',
                    'type' => 'incomingMessage',
                    'position' => ['x' => 0, 'y' => 0],
                    'data' => ['label' => 'Incoming', 'type' => 'incomingMessage', 'settings' => []],
                ],
                [
                    'id' => 'end-1',
                    'type' => 'end',
                    'position' => ['x' => 0, 'y' => 0],
                    'data' => ['label' => 'End', 'type' => 'end'],
                ],
            ],
            'edges' => [
                ['id' => 'e-in', 'source' => 'incomingMessage-1', 'target' => 'voice-faq-counter'],
            ],
        ], [
            'idPrefix' => 'voice-faq',
            'basePosition' => ['x' => 200, 'y' => 200],
            'mode' => 'incoming',
            'questionFollowup' => 'Anything else?',
            'systemPrompt' => 'Be helpful.',
            'doneTarget' => 'end-1',
            'humanTarget' => 'end-1',
        ]);

        $nodeIds = collect($flowData['nodes'])->pluck('id')->all();
        $this->assertNotContains('voice-faq-question-initial', $nodeIds);
        $this->assertContains('voice-faq-counter', $nodeIds);
    }
}
