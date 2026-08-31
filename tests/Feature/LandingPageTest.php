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
        $response->assertSee('The Bookings app inside Unganisha', false);
        $response->assertSee('Where conversations become commerce.', false);
        $response->assertSee('Unganisha<span style="color:#0E8A7A;">Hub</span>', false);
        $response->assertDontSee('ConvoConnect', false);
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
        $response->assertSee('Unganisha SMS', false);
        $response->assertSee('Unganisha Hub', false);
        $response->assertSee('Unganisha Inbox', false);
        $response->assertSee('Unganisha Store', false);
        $response->assertSee('Unganisha Pay', false);
        $response->assertSee('id="platform"', false);
        $response->assertSee('Outfit', false);
        $response->assertDontSee('ChatDuka', false);
        $response->assertDontSee('MauzoChat', false);
        $response->assertSee('/landing/unganisha/og.png', false);
        $response->assertSee('Sender ID', false);
        $response->assertSee('KES 0.6', false);
        $response->assertDontSee('Twilio SMS', false);
        $response->assertSee('/api/wpbox/sendmessage', false);
        $response->assertSee('/api/v1/docs', false);
        $response->assertSee('/popup/whatsapp', false);
        $response->assertSee('property="og:title"', false);
        $response->assertSee('Skip to content', false);
        $response->assertSee('Google Calendar sync', false);
        $response->assertSee('managed AI credits', false);
        $response->assertSee('application/ld+json', false);
        $response->assertSee('"@type":"WebSite"', false);
        $response->assertSee('"@type":"Organization"', false);
        $response->assertSee('/landing/unganisha/android-chrome-192x192.png', false);
        $response->assertSee('/landing/unganisha/mark.svg', false);
        $response->assertSee('/landing/unganisha/mark.png', false);
        $response->assertDontSee('href="/favicon.ico"', false);
        $response->assertDontSee('href="/android-chrome-192x192.png"', false);
        $response->assertSee('id="collections"', false);
        $response->assertSee('Collections board', false);
        $response->assertSee('STK retry', false);
        $response->assertSee('Paystack fallback', false);
        $response->assertSee('WhatsApp chase', false);
        $response->assertSee('Cart Recovery', false);
        $response->assertSee('Booking Convert', false);
        $response->assertSee('Lead-to-Cash', false);
        $response->assertSee('not a wallet or PSP', false);
        $response->assertDontSee('autonomous agent', false);
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
        $response->assertSee('Conversation channels: WhatsApp, Instagram, Messenger, WhatsApp Calls, phone', false);
        $response->assertSee('Campaign channels: WhatsApp, SMS, email', false);
        $response->assertSee('Search name, channel, message', false);
        $response->assertSee('>WhatsApp</span>', false);
        $response->assertSee('>Instagram</span>', false);
        $response->assertSee('>Messenger</span>', false);
        $response->assertSee('WhatsApp Calls', false);
        $response->assertSee('Programmable phone', false);
        $response->assertSee('programmable phone', false);
        $response->assertSee('id="product-menu"', false);
        $response->assertSee('Product', false);
        $response->assertSee('Channels', false);
        $response->assertSee('Campaigns', false);
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
