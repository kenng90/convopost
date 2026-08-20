<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FaviconBrandingTest extends TestCase
{
    use RefreshDatabase;

    public function test_landing_page_declares_convoconnect_favicon_links(): void
    {
        config(['settings.disable_landing_page' => false]);
        config(['settings.landing_page' => 'Wpsupportlanding']);

        $response = $this->get(route('landing'));

        $response->assertOk();
        $this->assertSeeFaviconLinks($response->getContent());
    }

    public function test_login_page_declares_convoconnect_favicon_links(): void
    {
        $response = $this->get('/login');

        $response->assertOk();
        $this->assertSeeFaviconLinks($response->getContent());
    }

    public function test_public_favicon_ico_is_png_packed_not_legacy_dib(): void
    {
        $icoPath = public_path('favicon.ico');
        $this->assertFileExists($icoPath);

        $ico = file_get_contents($icoPath);
        $header = unpack('vreserved/vtype/vcount', substr($ico, 0, 6));

        $this->assertSame(0, $header['reserved']);
        $this->assertSame(1, $header['type']);
        $this->assertGreaterThanOrEqual(1, $header['count']);
        $this->assertStringContainsString("\x89PNG", $ico);

        $sixteen = file_get_contents(public_path('favicon-16x16.png'));
        $this->assertNotFalse($sixteen);
        $this->assertStringContainsString($sixteen, $ico);
    }

    public function test_web_manifest_names_convoconnect(): void
    {
        $manifest = json_decode(file_get_contents(public_path('site.webmanifest')), true);

        $this->assertSame('ConvoConnect', $manifest['name']);
        $this->assertSame('ConvoConnect', $manifest['short_name']);
        $this->assertNotEmpty($manifest['icons']);
    }

    private function assertSeeFaviconLinks(string $html): void
    {
        $this->assertStringContainsString('android-chrome-192x192.png', $html);
        $this->assertStringContainsString('favicon-32x32.png', $html);
        $this->assertStringContainsString('favicon-16x16.png', $html);
        $this->assertStringContainsString('favicon.ico', $html);
        $this->assertStringContainsString('apple-touch-icon.png', $html);
        $this->assertStringContainsString('site.webmanifest', $html);
        $this->assertStringNotContainsString('⚖️', $html);
    }
}
