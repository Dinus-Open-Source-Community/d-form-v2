<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeoEndpointsTest extends TestCase
{
    use RefreshDatabase;

    public function test_robots_txt_is_served_and_includes_sitemap(): void
    {
        $response = $this->get('/robots.txt');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/plain; charset=utf-8');
        $this->assertStringContainsString('User-agent:', $response->getContent());
        $this->assertStringContainsString('Sitemap:', $response->getContent());
        $this->assertStringContainsString('Disallow: /admin', $response->getContent());
    }

    public function test_sitemap_xml_is_valid_markup_and_lists_core_routes(): void
    {
        $response = $this->get('/sitemap.xml');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/xml; charset=utf-8');
        $body = $response->getContent();
        $this->assertStringContainsString('<urlset', $body);
        $this->assertStringContainsString('</urlset>', $body);
        $this->assertStringContainsString('/events</loc>', $body);
        $this->assertStringContainsString('/features</loc>', $body);
    }

    public function test_private_dashboard_responses_include_x_robots_tag(): void
    {
        $this->get('/auth/login')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive');
    }
}
