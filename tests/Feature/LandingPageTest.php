<?php

namespace Tests\Feature;

use App\Support\Offering;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LandingPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'settings.disable_landing_page' => false,
            'settings.landing_page' => 'Wpsupportlanding',
            'offering.mode' => Offering::MODE_SOCIAL_COMMERCE,
        ]);
    }

    public function test_landing_page_highlights_social_commerce(): void
    {
        $response = $this->get(route('landing'));

        $response->assertOk();
        $response->assertSee('id="social"', false);
        $response->assertSee('id="bookings"', false);
        $response->assertSee('Publish social content that sells.', false);
        $response->assertSee('Unganisha<span style="color:#0E8A7A;">Hub</span>', false);
        $response->assertSee('Unganisha Social', false);
        $response->assertDontSee('ConvoConnect', false);
        $response->assertDontSee('id="channels"', false);
        $response->assertDontSee('Conversation channels: WhatsApp', false);
    }

    public function test_landing_page_avoids_stale_marketing_claims(): void
    {
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
        $response = $this->get(route('landing'));

        $response->assertOk();
        $response->assertSee('M-Pesa', false);
        $response->assertSee('Paystack', false);
        $response->assertSee('Unganisha Hub', false);
        $response->assertSee('Unganisha Store', false);
        $response->assertSee('Unganisha Pay', false);
        $response->assertSee('id="platform"', false);
        $response->assertSee('id="social"', false);
        $response->assertSee('Facebook', false);
        $response->assertSee('Instagram', false);
        $response->assertSee('LinkedIn', false);
        $response->assertSee('See Social', false);
        $response->assertDontSee('ChatDuka', false);
        $response->assertDontSee('MauzoChat', false);
        $response->assertSee('/landing/unganisha/og.png', false);
        $response->assertSee('property="og:title"', false);
        $response->assertSee('Skip to content', false);
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
        $response->assertSee('not a wallet or PSP', false);
        $response->assertDontSee('autonomous agent', false);
    }

    public function test_landing_nav_uses_social_and_store(): void
    {
        $response = $this->get(route('landing'));

        $response->assertOk();
        $response->assertSee('id="product-menu"', false);
        $response->assertSee('Product', false);
        $response->assertSee('>Social</a>', false);
        $response->assertSee('>Store</a>', false);
        $response->assertDontSee('>Channels</a>', false);
        $response->assertDontSee('>Campaigns</a>', false);
    }

    public function test_landing_shows_messaging_channels_when_offering_is_full(): void
    {
        config(['offering.mode' => Offering::MODE_FULL]);

        $response = $this->get(route('landing'));

        $response->assertOk();
        $response->assertSee('id="channels"', false);
        $response->assertSee('WhatsApp, Instagram &amp; Messenger', false);
        $response->assertSee('Where conversations become commerce.', false);
    }

    public function test_landing_page_hides_registration_ctas_when_disabled(): void
    {
        config(['settings.disable_registration_page' => true]);

        $response = $this->get(route('landing'));

        $response->assertOk();
        $response->assertDontSee('Start Free Today', false);
        $response->assertDontSee('Get Started Free', false);
        $response->assertSee('Sign In', false);
    }
}
