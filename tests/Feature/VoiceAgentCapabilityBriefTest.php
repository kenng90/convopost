<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\ListCatalog;
use App\Scopes\CompanyScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Reminders\Models\Source;
use Modules\Whatsappcall\Services\VoiceAgentCapabilityBriefService;
use Tests\TestCase;

class VoiceAgentCapabilityBriefTest extends TestCase
{
    use RefreshDatabase;

    public function test_builds_brief_from_catalog_and_booking_services(): void
    {
        $company = Company::factory()->create();

        $catalog = ListCatalog::withoutGlobalScope(CompanyScope::class)->create([
            'company_id' => $company->id,
            'name' => 'Shop',
            'slug' => 'shop-voice-brief',
            'version' => 1,
            'items' => [
                ['id' => '1', 'title' => 'Blue Widget', 'price' => 100],
                ['id' => '2', 'title' => 'Red Widget', 'price' => 200],
            ],
            'columns' => [],
            'source' => 'manual',
        ]);

        $company->setConfig('whatsapp_ai_catalog_ids', json_encode([$catalog->id]));
        $company->setConfig('whatsapp_ai_booking_enabled', true);
        $company->setConfig('whatsapp_ai_booking_appointments', true);

        Source::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'name' => 'Consultation',
            'is_bookable' => true,
            'default_duration_minutes' => 30,
            'sort_order' => 0,
        ]);

        $brief = app(VoiceAgentCapabilityBriefService::class)->buildForCompany($company);

        $this->assertTrue($brief['mention_in_greeting']);
        $this->assertContains('products and orders', $brief['categories']);
        $this->assertContains('booking appointments', $brief['categories']);
        $this->assertContains('Blue Widget', $brief['examples']);
        $this->assertContains('Consultation', $brief['examples']);
        $this->assertStringContainsString('products and orders', $brief['spoken_brief']);
        $this->assertStringContainsString('Blue Widget', $brief['spoken_brief']);
        $this->assertStringContainsString('capability brief', strtolower($brief['instruction_brief']));
    }

    public function test_mention_in_greeting_can_be_disabled(): void
    {
        $company = Company::factory()->create();
        $company->setConfig('whatsapp_ai_mention_capabilities_in_greeting', false);

        $brief = app(VoiceAgentCapabilityBriefService::class)->buildForCompany($company);

        $this->assertFalse($brief['mention_in_greeting']);
        $this->assertStringNotContainsString('opening greeting', strtolower($brief['instruction_brief']));
    }
}
