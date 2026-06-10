<?php

namespace Tests\Unit;

use App\Services\VendorBrandingScrubber;
use Tests\TestCase;

class VendorBrandingScrubberTest extends TestCase
{
    public function test_scrub_replaces_legacy_vendor_names(): void
    {
        $input = 'Hi Daniel, welcome to Mobidonia from daniel@mobidonia.com';
        $output = VendorBrandingScrubber::scrub($input);

        $this->assertStringNotContainsString('Mobidonia', $output);
        $this->assertStringNotContainsString('Daniel', $output);
        $this->assertStringNotContainsString('daniel@mobidonia.com', $output);
        $this->assertStringContainsString('Kenneth', $output);
        $this->assertStringContainsString('kenneth@', $output);
    }

    public function test_email_templates_do_not_contain_legacy_vendor_names(): void
    {
        foreach (VendorBrandingScrubber::emailTemplateConfigs() as $template) {
            $this->assertStringNotContainsString('Mobidonia', $template['value']);
            $this->assertStringNotContainsString('Daniel', $template['value']);
            $this->assertStringNotContainsString('mobidonia.com', $template['value']);
        }
    }

    public function test_sms_templates_do_not_contain_legacy_vendor_names(): void
    {
        foreach (VendorBrandingScrubber::smsTemplateConfigs() as $template) {
            $this->assertStringNotContainsString('Mobidonia', $template['value']);
            $this->assertStringNotContainsString('Daniel', $template['value']);
            $this->assertStringNotContainsString('mobidonia.com', $template['value']);
        }
    }

    public function test_contains_vendor_branding_detects_legacy_values(): void
    {
        $this->assertTrue(VendorBrandingScrubber::containsVendorBranding('Founder, Mobidonia'));
        $this->assertTrue(VendorBrandingScrubber::containsVendorBranding('Daniel Dimov'));
        $this->assertFalse(VendorBrandingScrubber::containsVendorBranding('Kenneth'));
    }
}
