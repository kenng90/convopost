<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SitemapTest extends TestCase
{
    use RefreshDatabase;

    public function test_sitemap_lists_public_marketing_pages(): void
    {
        $response = $this->get(route('sitemap'));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/xml; charset=UTF-8');
        $response->assertSee('<urlset', false);
        $response->assertSee(e(url('/')), false);
        $response->assertSee(e(route('services.automation')), false);
        $response->assertSee(e(route('policy.show')), false);
        $response->assertSee(e(route('terms.show')), false);
        $response->assertSee(e(route('login')), false);
        $response->assertSee(e(route('register')), false);
        $response->assertDontSee('/app/install', false);
    }

    public function test_sitemap_omits_register_when_registration_is_disabled(): void
    {
        config(['settings.disable_registration_page' => true]);

        $response = $this->get(route('sitemap'));

        $response->assertOk();
        $response->assertDontSee(e(route('register')), false);
        $response->assertSee(e(route('login')), false);
    }

    public function test_robots_txt_points_crawlers_at_the_sitemap(): void
    {
        $robots = file_get_contents(public_path('robots.txt'));

        $this->assertNotFalse($robots);
        $this->assertStringContainsString('Sitemap: https://www.convoconnect.tech/sitemap.xml', $robots);
        $this->assertStringContainsString('Disallow: /app/install', $robots);
    }
}
