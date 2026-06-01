<?php

namespace Tests\Unit;

use App\Models\Company;
use Mockery;
use Modules\Whatsappcall\Services\CallHandlingResolver;
use Tests\TestCase;

class CallHandlingResolverTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_should_not_use_ai_without_company_openai_key(): void
    {
        $company = Mockery::mock(Company::class);
        $company->shouldReceive('getConfig')->with('whatsapp_call_handling', 'live')->andReturn('ai');
        $company->shouldReceive('getConfig')->with('whatsapp_ai_openai_api_key', '')->andReturn('');

        $resolver = new CallHandlingResolver;

        $this->assertFalse($resolver->shouldUseAi($company));
    }
}
