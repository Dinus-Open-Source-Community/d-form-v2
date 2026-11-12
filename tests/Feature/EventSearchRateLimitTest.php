<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Route index /events memakai limiter bernama `event-search` (config-driven).
 */
class EventSearchRateLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_requests_beyond_the_limit_return_429_with_retry_after(): void
    {
        config(['events.search.rate_limit_per_minute' => 2]);

        $this->get('/events')->assertOk();
        $this->get('/events')->assertOk();

        $response = $this->get('/events');

        $response->assertStatus(429);
        $this->assertNotNull($response->headers->get('Retry-After'));
    }
}
