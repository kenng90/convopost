<?php

namespace Tests\Feature;

use Tests\TestCase;

class AppInstallPageTest extends TestCase
{
    public function test_app_install_page_renders_without_token_when_not_required(): void
    {
        config([
            'wpbox.mobile_app_install_token' => '',
            'wpbox.mobile_app_android_url' => 'https://example.com/app.apk',
            'wpbox.mobile_app_ios_url' => '',
            'wpbox.mobile_app_version' => '4.2.0',
        ]);

        $response = $this->get(route('app.install'));

        $response->assertOk();
        $response->assertSee('Download Android app', false);
        $response->assertSee('share the invite link instead', false);
        $response->assertDontSee('it includes your access token', false);
    }

    public function test_app_install_page_requires_token_when_configured(): void
    {
        config([
            'wpbox.mobile_app_install_token' => 'secret-invite-token',
            'wpbox.mobile_app_android_url' => 'https://example.com/app.apk',
        ]);

        $this->get(route('app.install'))->assertForbidden();

        $response = $this->get(route('app.install', ['token' => 'secret-invite-token']));

        $response->assertOk();
        $response->assertSee('it includes your access token', false);
    }

    public function test_app_install_route_is_named_app_install(): void
    {
        $this->assertSame(url('/app/install'), route('app.install'));
    }
}
