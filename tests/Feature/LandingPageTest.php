<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LandingPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_landing_page_highlights_journeys_and_bookings(): void
    {
        config(['settings.disable_landing_page' => false]);
        config(['settings.landing_page' => 'Wpsupportlanding']);

        $response = $this->get(route('landing'));

        $response->assertOk();
        $response->assertSee('id="journeys"', false);
        $response->assertSee('id="bookings"', false);
        $response->assertSee('Journey Pipelines', false);
        $response->assertSee('The Bookings app inside ConvoConnect', false);
        $response->assertSee('Journeys tab in Company Apps', false);
        $response->assertSee('Bookings tab in Company Apps', false);
        $response->assertDontSee('Appointments &amp; Events', false);
        $response->assertDontSee('Reminders &amp; Reservations', false);
    }

    public function test_landing_page_avoids_stale_marketing_claims(): void
    {
        config(['settings.disable_landing_page' => false]);
        config(['settings.landing_page' => 'Wpsupportlanding']);

        $response = $this->get(route('landing'));

        $response->assertOk();
        $response->assertDontSee('/popup/whatsapp?id=0OPfclU79P', false);
        $response->assertDontSee('bookingIframe', false);
        $response->assertDontSee('cdn.convoconnect.io/widget.js', false);
        $response->assertDontSee('api.convoconnect.io/v1/messages', false);
        $response->assertDontSee('/v1/messages/send', false);
        $response->assertDontSee('ConvoWidget', false);
        $response->assertDontSee('Every feature available in the UI is also accessible via API', false);
        $response->assertDontSee('6 powerful node types', false);
        $response->assertDontSee('2,400+', false);
        $response->assertDontSee('Contact Sales', false);
        $response->assertDontSee('Stripe card payment links', false);
    }

    public function test_landing_page_highlights_accurate_product_capabilities(): void
    {
        config(['settings.disable_landing_page' => false]);
        config(['settings.landing_page' => 'Wpsupportlanding']);

        $response = $this->get(route('landing'));

        $response->assertOk();
        $response->assertSee('M-Pesa', false);
        $response->assertSee('Paystack', false);
        $response->assertSee('Forms ↔ Flow Builder bridge', false);
        $response->assertSee('38+ automation nodes', false);
        $response->assertSee('WhatsApp, Instagram &amp; Messenger', false);
        $response->assertSee('id="channels"', false);
        $response->assertSee('id="campaigns"', false);
        $response->assertSee('WhatsApp, SMS &amp; email campaigns', false);
        $response->assertSee('WhatsApp, SMS &amp; email channels', false);
        $response->assertSee('ConvoConnect SMS', false);
        $response->assertSee('Sender ID', false);
        $response->assertSee('KES 0.6', false);
        $response->assertDontSee('Twilio SMS', false);
        $response->assertSee('/api/wpbox/sendmessage', false);
        $response->assertSee('/popup/whatsapp', false);
        $response->assertSee('property="og:title"', false);
        $response->assertSee('Skip to content', false);
        $response->assertSee('Google Calendar sync', false);
        $response->assertSee('managed AI credits', false);
    }

    public function test_landing_page_highlights_omnichannel_and_campaigns(): void
    {
        config(['settings.disable_landing_page' => false]);
        config(['settings.landing_page' => 'Wpsupportlanding']);

        $response = $this->get(route('landing'));

        $response->assertOk();
        $response->assertSee('We support', false);
        $response->assertSee('Instagram', false);
        $response->assertSee('Messenger', false);
        $response->assertSee('SMS campaigns', false);
        $response->assertSee('Email campaigns', false);
        $response->assertSee('WhatsApp campaigns', false);
        $response->assertSee('Messaging channels: WhatsApp, Instagram, Messenger', false);
        $response->assertSee('Campaign channels: WhatsApp, SMS, email', false);
    }

    public function test_landing_page_hides_registration_ctas_when_disabled(): void
    {
        config(['settings.disable_landing_page' => false]);
        config(['settings.landing_page' => 'Wpsupportlanding']);
        config(['settings.disable_registration_page' => true]);

        $response = $this->get(route('landing'));

        $response->assertOk();
        $response->assertDontSee('Start Free Today', false);
        $response->assertDontSee('Get Started Free', false);
        $response->assertSee('Sign In', false);
    }
}
