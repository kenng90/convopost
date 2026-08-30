<?php

namespace Tests\Feature;

use Tests\TestCase;

class TermsOfServiceTest extends TestCase
{
    public function test_terms_of_service_page_is_publicly_accessible(): void
    {
        $response = $this->get('/terms-of-service');

        $response->assertOk();
        $response->assertSee('Terms of Service', false);
        $response->assertSee('MauzoChat', false);
        $response->assertDontSee('ConvoConnect', false);
        $response->assertSee('WhatsApp', false);
    }

    public function test_terms_of_service_route_is_named_terms_show(): void
    {
        $this->assertSame(url('/terms-of-service'), route('terms.show'));
    }

    public function test_terms_page_links_to_privacy_policy(): void
    {
        $response = $this->get('/terms-of-service');

        $response->assertSee(route('policy.show'), false);
    }
}
