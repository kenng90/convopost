<?php

namespace Tests\Unit;

use Modules\Wpsupportlanding\Support\UnganishaBrand;
use Tests\TestCase;

class UnganishaBrandTest extends TestCase
{
    public function test_landing_brand_is_isolated_from_app_name(): void
    {
        config(['app.name' => 'ConvoConnect']);
        config(['settings.site_name' => 'ConvoConnect']);

        $this->assertSame('Unganisha', UnganishaBrand::name());
        $this->assertSame('Unganisha Hub', UnganishaBrand::hubName());
        $this->assertSame('Where conversations become commerce.', UnganishaBrand::tagline());
        $this->assertSame('The social commerce hub for modern businesses.', UnganishaBrand::positioning());
        $this->assertStringContainsString('Unganisha', UnganishaBrand::metaTitle());
        $this->assertStringContainsString('connect with customers', UnganishaBrand::description());
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
        $this->assertSame('Unganisha Inbox', UnganishaBrand::products()[1]['name']);
        $this->assertStringContainsString('WhatsApp Calls', UnganishaBrand::products()[1]['blurb']);
        $this->assertStringContainsString('programmable phone', UnganishaBrand::products()[1]['blurb']);
    }
}
