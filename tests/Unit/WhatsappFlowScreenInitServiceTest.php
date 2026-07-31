<?php

namespace Tests\Unit;

use App\Models\WhatsappFlow;
use App\Services\WhatsappFlows\WhatsappFlowScreenInitService;
use Tests\TestCase;

class WhatsappFlowScreenInitServiceTest extends TestCase
{
    public function test_init_uses_published_meta_schema_for_string_fields(): void
    {
        $flow = new WhatsappFlow([
            'flow_json' => [
                'screens' => [[
                    'id' => 'LOAN',
                    'dynamic_data' => [[
                        'key' => 'emi',
                        'type' => 'option_list',
                        'example_items' => [['id' => 'x', 'title' => 'wrong']],
                    ]],
                ]],
            ],
            'meta_flow_json' => [
                'screens' => [[
                    'id' => 'LOAN',
                    'data' => [
                        'emi' => ['type' => 'string', '__example__' => '1200'],
                        'rate' => ['type' => 'string', '__example__' => '12%'],
                    ],
                ]],
            ],
        ]);

        $init = app(WhatsappFlowScreenInitService::class)->initDataForScreen($flow, 'LOAN');

        $this->assertSame('1200', $init['emi']);
        $this->assertSame('12%', $init['rate']);
    }
}
