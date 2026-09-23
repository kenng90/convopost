<?php

namespace Tests\Unit;

use App\Support\Offering;
use Tests\TestCase;

class OfferingFullModeRunbookTest extends TestCase
{
    private string $runbookPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->runbookPath = base_path('OFFERING_MODE_FULL_RUNBOOK.md');
    }

    public function test_full_mode_runbook_exists_and_covers_flip_checklist(): void
    {
        $this->assertFileExists($this->runbookPath);

        $body = file_get_contents($this->runbookPath);
        $this->assertIsString($body);
        $this->assertNotSame('', trim($body));

        foreach ([
            'OFFERING_MODE=full',
            'OFFERING_MODE=social_commerce',
            'whatsapp_dormant_modules',
            'whatsapp_dormant_routes',
            'BlockDormantWhatsappUi',
            'OfferingNavigationTest',
            'OfferingWhatsappUiBlockTest',
            'LandingPageTest',
            'plan-entitlements',
            'Rollback',
        ] as $needle) {
            $this->assertStringContainsString(
                $needle,
                $body,
                "Runbook must document checklist item: {$needle}"
            );
        }
    }

    public function test_runbook_references_match_offering_config_sources(): void
    {
        $body = (string) file_get_contents($this->runbookPath);

        $this->assertStringContainsString('config/offering.php', $body);
        $this->assertStringContainsString(Offering::MODE_FULL, $body);
        $this->assertStringContainsString(Offering::MODE_SOCIAL_COMMERCE, $body);

        foreach (array_slice(config('offering.whatsapp_dormant_modules', []), 0, 3) as $module) {
            $this->assertContains($module, config('offering.whatsapp_dormant_modules'));
        }

        $this->assertContains('chat.index', config('offering.whatsapp_dormant_routes'));
        $this->assertContains('campaigns.index', config('offering.whatsapp_dormant_routes'));
        $this->assertStringContainsString('chat.index', $body);
        $this->assertStringContainsString('campaigns.index', $body);
    }
}
