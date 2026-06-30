<?php

namespace Tests\Unit;

use App\Services\Flowmaker\FlowTemplateEnricher;
use Tests\TestCase;

class FlowTemplateEnricherTest extends TestCase
{
    public function test_enricher_adds_auto_send_to_llm_nodes(): void
    {
        $templates = FlowTemplateEnricher::enrich([
            'sample' => [
                'flow_data' => [
                    'nodes' => [
                        [
                            'id' => 'openai-1',
                            'type' => 'openai',
                            'data' => [
                                'settings' => [
                                    'llm' => [
                                        'model' => 'openai/gpt-4o-mini',
                                        'prompt' => 'hi',
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'edges' => [],
                ],
            ],
        ]);

        $llm = $templates['sample']['flow_data']['nodes'][0]['data']['settings']['llm'];
        $this->assertTrue($llm['autoSendMessage']);
        $this->assertSame('ai_response', $llm['variableName']);
    }
}
