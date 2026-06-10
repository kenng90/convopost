<?php

namespace Tests\Unit;

use App\Models\Company;
use Mockery;
use Modules\Whatsappcall\Services\CompanyVoiceOpenAiKeyResolver;
use Tests\TestCase;

class CompanyVoiceOpenAiKeyResolverTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_returns_company_key_when_set(): void
    {
        config(['wpbox.openai_api_key' => 'sk-platform-should-not-be-used']);

        $company = Mockery::mock(Company::class);
        $company->shouldReceive('getConfig')->with('whatsapp_ai_openai_api_key', '')->andReturn('sk-company');

        $resolver = new CompanyVoiceOpenAiKeyResolver;

        $this->assertSame('sk-company', $resolver->resolve($company));
        $this->assertTrue($resolver->isConfigured($company));
    }

    public function test_returns_null_when_company_key_missing_even_if_platform_key_exists(): void
    {
        config(['wpbox.openai_api_key' => 'sk-platform']);

        $company = Mockery::mock(Company::class);
        $company->shouldReceive('getConfig')->with('whatsapp_ai_openai_api_key', '')->andReturn('');

        $resolver = new CompanyVoiceOpenAiKeyResolver;

        $this->assertNull($resolver->resolve($company));
        $this->assertFalse($resolver->isConfigured($company));
    }
}
