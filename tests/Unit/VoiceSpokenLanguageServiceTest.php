<?php

namespace Tests\Unit;

use App\Models\Company;
use Mockery;
use Modules\Whatsappcall\Services\VoiceSpokenLanguageService;
use Tests\TestCase;

class VoiceSpokenLanguageServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_defaults_to_english_when_unset(): void
    {
        $company = $this->mockCompany('');

        $service = new VoiceSpokenLanguageService;

        $this->assertSame('en', $service->resolveCode($company));
        $this->assertSame('English', $service->resolveName($company));
        $this->assertTrue($service->isPinned($company));
        $this->assertSame('en', $service->transcriptionCode($company));
        $this->assertStringContainsString('Always speak English', $service->instructionText($company));
    }

    public function test_pins_swahili_and_maps_dispatch_payload(): void
    {
        $company = $this->mockCompany('sw');

        $dispatch = (new VoiceSpokenLanguageService)->forDispatch($company);

        $this->assertSame('sw', $dispatch['code']);
        $this->assertSame('Swahili', $dispatch['name']);
        $this->assertTrue($dispatch['pinned']);
        $this->assertSame('sw', $dispatch['transcription_code']);
        $this->assertStringContainsString('Always speak Swahili', $dispatch['instruction']);
    }

    public function test_auto_does_not_pin_transcription(): void
    {
        $company = $this->mockCompany('auto');

        $service = new VoiceSpokenLanguageService;

        $this->assertSame('auto', $service->resolveCode($company));
        $this->assertFalse($service->isPinned($company));
        $this->assertNull($service->transcriptionCode($company));
        $this->assertStringContainsString("Detect the caller's language", $service->instructionText($company));
    }

    public function test_invalid_code_falls_back_to_english(): void
    {
        $company = $this->mockCompany('not-a-language');

        $service = new VoiceSpokenLanguageService;

        $this->assertSame('en', $service->resolveCode($company));
        $this->assertSame('en', $service->transcriptionCode($company));
    }

    public function test_norwegian_bokmal_maps_to_whisper_no(): void
    {
        $company = $this->mockCompany('nb');

        $this->assertSame('no', (new VoiceSpokenLanguageService)->transcriptionCode($company));
    }

    public function test_options_include_auto_and_whisper_languages(): void
    {
        $options = (new VoiceSpokenLanguageService)->options();

        $this->assertArrayHasKey('auto', $options);
        $this->assertArrayHasKey('en', $options);
        $this->assertArrayHasKey('sw', $options);
        $this->assertContains('auto', (new VoiceSpokenLanguageService)->allowedCodes());
        $this->assertContains('en', (new VoiceSpokenLanguageService)->allowedCodes());
    }

    private function mockCompany(string $code): Company
    {
        $company = Mockery::mock(Company::class);
        $company->shouldReceive('getConfig')
            ->with(VoiceSpokenLanguageService::CONFIG_KEY, VoiceSpokenLanguageService::DEFAULT_CODE)
            ->andReturn($code);

        return $company;
    }
}
