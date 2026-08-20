<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AutomationServicesLandingTest extends TestCase
{
    use RefreshDatabase;

    public function test_automation_services_landing_page_renders(): void
    {
        $response = $this->get(route('services.automation'));

        $response->assertOk();
        $response->assertSee('Custom AI operating systems', false);
        $response->assertSee('your real workflows.', false);
        $response->assertSee('id="what-we-do"', false);
        $response->assertSee('id="how-it-works"', false);
        $response->assertSee('id="deploy"', false);
        $response->assertSee('id="catalog"', false);
        $response->assertSee('id="integrations"', false);
        $response->assertSee('id="process"', false);
        $response->assertSee('Context is scattered', false);
        $response->assertSee('Human approval', false);
        $response->assertSee('Map the workflow', false);
        $response->assertSee('Deploy one workflow', false);
        $response->assertSee('We deploy first. Then we productize what repeats.', false);
        $response->assertSee('Built around the tools you already use', false);
        $response->assertSee('Hospitality', false);
        $response->assertSee('Professional services', false);
        $response->assertSee('Book a deployment', false);
        $response->assertSee('Separate from', false);
        $response->assertSee('WhatsApp SaaS', false);
        $response->assertSee('Skip to content', false);
        $response->assertSee('On-network inference when required', false);
        $response->assertDontSee('Book a consult', false);
        $response->assertDontSee('$300–800', false);
        $response->assertDontSee('AI workers that use your data', false);
        $response->assertDontSee('id="packages"', false);
    }

    public function test_saas_landing_links_to_automation_services_from_hero(): void
    {
        config(['settings.disable_landing_page' => false]);
        config(['settings.landing_page' => 'Wpsupportlanding']);

        $response = $this->get(route('landing'));

        $response->assertOk();
        $response->assertSee(route('services.automation', [], false), false);
        $response->assertSee('Need help beyond chat?', false);
        $response->assertSee('I want to automate processes across my business', false);
        $response->assertDontSee('>Services</a>', false);
    }
}
