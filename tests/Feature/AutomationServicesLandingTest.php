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
        $response->assertSee('Automation that', false);
        $response->assertSee('runs the work.', false);
        $response->assertSee('Process automation practice', false);
        $response->assertSee('id="what-we-do"', false);
        $response->assertSee('id="how-it-works"', false);
        $response->assertSee('id="catalog"', false);
        $response->assertSee('id="integrations"', false);
        $response->assertSee('id="packages"', false);
        $response->assertSee('Business process audit', false);
        $response->assertSee('Workflow automation engine', false);
        $response->assertSee('AI workers that use your data', false);
        $response->assertSee('Stay on your network when it matters', false);
        $response->assertSee('Connect the tools you already use', false);
        $response->assertSee('Starter', false);
        $response->assertSee('Growth', false);
        $response->assertSee('Enterprise', false);
        $response->assertSee('$300–800', false);
        $response->assertSee('AI worker on your knowledge', false);
        $response->assertSee('On-network / local LLM option', false);
        $response->assertSee('Book a consult', false);
        $response->assertSee('Separate from', false);
        $response->assertSee('WhatsApp SaaS', false);
        $response->assertSee('Skip to content', false);
        $response->assertDontSee('AI customer support &amp; assistants', false);
        $response->assertDontSee('Meeting notes &amp; document summaries', false);
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
