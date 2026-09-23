<?php

namespace Tests\Unit;

use App\Support\Offering;
use Modules\Wpsupportlanding\Support\UnganishaBrand;
use Tests\TestCase;

class UnganishaBrandTest extends TestCase
{
    public function test_landing_brand_is_isolated_from_app_name(): void
    {
        config(['app.name' => 'ConvoConnect']);
        config(['settings.site_name' => 'ConvoConnect']);
        config(['offering.mode' => Offering::MODE_SOCIAL_COMMERCE]);

        $this->assertSame('Unganisha', UnganishaBrand::name());
        $this->assertSame('Unganisha Hub', UnganishaBrand::hubName());
        $this->assertSame('Publish social content that sells.', UnganishaBrand::tagline());
        $this->assertSame('The social commerce hub for modern businesses.', UnganishaBrand::positioning());
        $this->assertStringContainsString('Unganisha', UnganishaBrand::metaTitle());
        $this->assertStringContainsString('social posts', UnganishaBrand::description());
        $this->assertStringContainsString('M-Pesa', UnganishaBrand::description());
        $this->assertStringNotContainsString('ConvoConnect', UnganishaBrand::description());
        $this->assertStringNotContainsString('ConvoConnect', UnganishaBrand::metaTitle());
        $this->assertStringNotContainsString('MauzoChat', UnganishaBrand::name());
        $this->assertStringContainsString('/landing/unganisha/mark.png', UnganishaBrand::markUrl());
        $this->assertStringContainsString('/landing/unganisha/logo.png', UnganishaBrand::logoUrl());
        $this->assertStringContainsString('/landing/unganisha/logo-on-dark.png', UnganishaBrand::logoOnDarkUrl());
        $this->assertStringContainsString('/landing/unganisha/og.png', UnganishaBrand::ogImageUrl());
        $this->assertFileExists(public_path('landing/unganisha/mark.png'));
        $this->assertFileExists(public_path('landing/unganisha/logo.png'));
        $this->assertFileExists(public_path('landing/unganisha/logo-on-dark.png'));
        $this->assertFileExists(public_path('landing/unganisha/og.png'));
        $this->assertFileExists(public_path('landing/unganisha/mark.svg'));

        $productNames = collect(UnganishaBrand::products())->pluck('name')->all();
        $this->assertContains('Unganisha Social', $productNames);
        $this->assertNotContains('Unganisha Inbox', $productNames);
        $this->assertNotContains('Unganisha Campaigns', $productNames);
    }

    public function test_full_offering_restores_inbox_product_copy(): void
    {
        config(['offering.mode' => Offering::MODE_FULL]);

        $this->assertSame('Where conversations become commerce.', UnganishaBrand::tagline());
        $this->assertSame('The omnichannel commerce hub for modern businesses.', UnganishaBrand::positioning());
        $this->assertStringContainsString('connect with customers', UnganishaBrand::description());
        $productNames = collect(UnganishaBrand::products())->pluck('name')->all();
        $this->assertContains('Unganisha Inbox', $productNames);
        $this->assertContains('Unganisha Campaigns', $productNames);
        $this->assertContains('Unganisha Social', $productNames);
        $inbox = collect(UnganishaBrand::products())->firstWhere('name', 'Unganisha Inbox');
        $this->assertStringContainsString('WhatsApp Calls', $inbox['blurb']);
        $this->assertStringContainsString('programmable phone', $inbox['blurb']);
    }
}
