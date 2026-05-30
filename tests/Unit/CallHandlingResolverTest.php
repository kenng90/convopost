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

    public function test_live_mode_does_not_use_ai(): void
    {
        $company = $this->companyWithHandling('live');

        $resolver = new CallHandlingResolver;

        $this->assertFalse($resolver->shouldUseAi($company));
    }

    public function test_ai_mode_uses_ai(): void
    {
        $company = $this->companyWithHandling('ai');

        $resolver = new CallHandlingResolver;

        $this->assertTrue($resolver->shouldUseAi($company));
    }

    private function companyWithHandling(string $mode): Company
    {
        $company = Mockery::mock(Company::class);
        $company->shouldReceive('getConfig')->with('whatsapp_call_handling', CallHandlingResolver::MODE_LIVE)->andReturn($mode);

        return $company;
    }
}
