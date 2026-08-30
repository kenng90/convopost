<?php

namespace Tests\Unit;

use Modules\Wpsupportlanding\Support\ChatDukaBrand;
use Tests\TestCase;

class ChatDukaBrandTest extends TestCase
{
    public function test_landing_brand_is_isolated_from_app_name(): void
    {
        config(['app.name' => 'ConvoConnect']);
        config(['settings.site_name' => 'ConvoConnect']);

        $this->assertSame('ChatDuka', ChatDukaBrand::name());
        $this->assertSame('Your duka, in every chat.', ChatDukaBrand::tagline());
        $this->assertStringContainsString('ChatDuka', ChatDukaBrand::metaTitle());
        $this->assertStringNotContainsString('ConvoConnect', ChatDukaBrand::description());
        $this->assertStringNotContainsString('ConvoConnect', ChatDukaBrand::metaTitle());
        $this->assertStringContainsString('/landing/chatduka/mark.png', ChatDukaBrand::markUrl());
        $this->assertStringContainsString('/landing/chatduka/logo.png', ChatDukaBrand::logoUrl());
        $this->assertStringContainsString('/landing/chatduka/logo-on-dark.png', ChatDukaBrand::logoOnDarkUrl());
        $this->assertStringContainsString('/landing/chatduka/og.png', ChatDukaBrand::ogImageUrl());
        $this->assertFileExists(public_path('landing/chatduka/mark.png'));
        $this->assertFileExists(public_path('landing/chatduka/logo.png'));
        $this->assertFileExists(public_path('landing/chatduka/logo-on-dark.png'));
    }
}
