<?php

namespace Tests\Unit;

use App\Models\Company;
use Mockery;
use Modules\Whatsappcall\Services\FlowKnowledgeSearch;
use Modules\Whatsappcall\Services\VoiceSpokenLanguageService;
use Modules\Whatsappcall\Services\WhatsappAgentContextService;
use Tests\TestCase;

class WhatsappAgentContextServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_builds_context_from_greeting_and_catalog_config(): void
    {
        $company = Mockery::mock(Company::class);
        $company->shouldReceive('getConfig')->with('whatsapp_ai_flow_id', 0)->andReturn('');
        $company->shouldReceive('getConfig')->with('whatsapp_ai_catalog_ids', '[]')->andReturn('[]');
        $company->shouldReceive('getConfig')->with('whatsapp_ai_greeting', '')->andReturn('Hello from support');
        $company->shouldReceive('getConfig')->with('whatsapp_ai_spoken_language', 'en')->andReturn('en');
        $company->shouldReceive('getConfig')->with('whatsapp_ai_enable_vector_search', true)->andReturn(false);
        $company->shouldReceive('getAttribute')->with('id')->andReturn(1);

        $knowledge = Mockery::mock(FlowKnowledgeSearch::class);

        $service = new WhatsappAgentContextService($knowledge, new VoiceSpokenLanguageService);
        $result = $service->buildForCompany($company, null);

        $this->assertStringContainsString('Hello from support', $result['system_context']);
        $this->assertStringContainsString('Always speak English', $result['system_context']);
        $this->assertSame('', $result['vector_context']);
        $this->assertNull($result['flow_id']);
    }

    public function test_system_context_pins_configured_spoken_language(): void
    {
        $company = Mockery::mock(Company::class);
        $company->shouldReceive('getConfig')->with('whatsapp_ai_flow_id', 0)->andReturn('');
        $company->shouldReceive('getConfig')->with('whatsapp_ai_catalog_ids', '[]')->andReturn('[]');
        $company->shouldReceive('getConfig')->with('whatsapp_ai_greeting', '')->andReturn('');
        $company->shouldReceive('getConfig')->with('whatsapp_ai_spoken_language', 'en')->andReturn('sw');
        $company->shouldReceive('getConfig')->with('whatsapp_ai_enable_vector_search', true)->andReturn(false);
        $company->shouldReceive('getAttribute')->with('id')->andReturn(1);

        $service = new WhatsappAgentContextService(
            Mockery::mock(FlowKnowledgeSearch::class),
            new VoiceSpokenLanguageService
        );

        $result = $service->buildForCompany($company, null);

        $this->assertStringContainsString('Always speak Swahili', $result['system_context']);
    }

    public function test_default_vector_query_uses_greeting_when_set(): void
    {
        $company = Mockery::mock(Company::class);
        $company->shouldReceive('getConfig')->with('whatsapp_ai_greeting', '')->andReturn('Hi there');

        $service = new WhatsappAgentContextService(Mockery::mock(FlowKnowledgeSearch::class), new VoiceSpokenLanguageService);

        $this->assertSame('Hi there', $service->defaultVectorQuery($company));
    }
}
