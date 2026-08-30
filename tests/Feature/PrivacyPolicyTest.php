<?php

namespace Tests\Feature;

use Tests\TestCase;

class PrivacyPolicyTest extends TestCase
{
    public function test_privacy_policy_page_is_publicly_accessible(): void
    {
        $response = $this->get('/privacy-policy');

        $response->assertOk();
        $response->assertSee('Privacy Policy', false);
        $response->assertSee('MauzoChat', false);
        $response->assertDontSee('ConvoConnect', false);
        $response->assertSee('WhatsApp', false);
    }

    public function test_privacy_policy_route_is_named_policy_show(): void
    {
        $this->assertSame(url('/privacy-policy'), route('policy.show'));
    }
}
