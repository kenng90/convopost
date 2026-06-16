<?php

namespace Tests\Unit;

use Mockery;
use Modules\Flowmaker\Listeners\RespondOnMessage;
use Modules\Flowmaker\Models\Flow;
use Tests\TestCase;

class RespondOnMessageTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_skips_voice_assigned_flow_when_other_flows_exist(): void
    {
        $company = Mockery::mock(\App\Models\Company::class);
        $company->shouldReceive('getConfig')->with('whatsapp_ai_flow_id', 0)->andReturn('10');

        $voiceFlow = new Flow(['id' => 10, 'name' => 'Voice']);
        $chatFlow = new Flow(['id' => 20, 'name' => 'Sales']);

        $listener = new RespondOnMessage;
        $result = $listener->filterFlowsForChat($company, collect([$voiceFlow, $chatFlow]));

        $this->assertCount(1, $result);
        $this->assertSame(20, $result->first()->id);
    }

    public function test_keeps_voice_flow_when_it_is_the_only_flow(): void
    {
        $company = Mockery::mock(\App\Models\Company::class);
        $company->shouldReceive('getConfig')->with('whatsapp_ai_flow_id', 0)->andReturn('10');

        $voiceFlow = new Flow(['id' => 10, 'name' => 'Voice']);

        $listener = new RespondOnMessage;
        $result = $listener->filterFlowsForChat($company, collect([$voiceFlow]));

        $this->assertCount(1, $result);
        $this->assertSame(10, $result->first()->id);
    }

    public function test_processes_all_flows_when_no_voice_flow_configured(): void
    {
        $company = Mockery::mock(\App\Models\Company::class);
        $company->shouldReceive('getConfig')->with('whatsapp_ai_flow_id', 0)->andReturn('');

        $flows = collect([
            new Flow(['id' => 1, 'name' => 'A']),
            new Flow(['id' => 2, 'name' => 'B']),
        ]);

        $listener = new RespondOnMessage;
        $result = $listener->filterFlowsForChat($company, $flows);

        $this->assertCount(2, $result);
    }
}
