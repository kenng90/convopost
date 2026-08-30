<?php

namespace Tests\Unit;

use Modules\Wpsupportlanding\Support\MauzoChatBrand;
use Tests\TestCase;

class MauzoChatBrandTest extends TestCase
{
    public function test_landing_brand_is_isolated_from_app_name(): void
    {
        config(['app.name' => 'ConvoConnect']);
        config(['settings.site_name' => 'ConvoConnect']);

        $this->assertSame('MauzoChat', MauzoChatBrand::name());
        $this->assertSame('Connect. Chat. Close More Sales.', MauzoChatBrand::tagline());
        $this->assertStringContainsString('MauzoChat', MauzoChatBrand::metaTitle());
        $this->assertStringNotContainsString('ConvoConnect', MauzoChatBrand::description());
        $this->assertStringNotContainsString('ConvoConnect', MauzoChatBrand::metaTitle());
        $this->assertStringContainsString('/landing/mauzochat/mark.png', MauzoChatBrand::markUrl());
        $this->assertStringContainsString('/landing/mauzochat/logo.png', MauzoChatBrand::logoUrl());
        $this->assertStringContainsString('/landing/mauzochat/logo-on-dark.png', MauzoChatBrand::logoOnDarkUrl());
        $this->assertStringContainsString('/landing/mauzochat/og.png', MauzoChatBrand::ogImageUrl());
        $this->assertFileExists(public_path('landing/mauzochat/mark.png'));
        $this->assertFileExists(public_path('landing/mauzochat/logo.png'));
        $this->assertFileExists(public_path('landing/mauzochat/logo-on-dark.png'));
        $this->assertFileExists(public_path('landing/mauzochat/og.png'));
        $this->assertFileExists(public_path('landing/mauzochat/mark.svg'));
    }
}
