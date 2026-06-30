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
}
