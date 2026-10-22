<?php

namespace Tests\Feature\Stress;

use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ThrottleBypassDisabledTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware('throttle:1,1')->get('/__stress_throttled', fn (): string => 'ok');
    }

    public function test_second_request_is_throttled_by_default(): void
    {
        $this->get('/__stress_throttled')->assertOk();
        $this->get('/__stress_throttled')->assertStatus(429);
    }

    public function test_alias_points_to_throttle_requests_by_default(): void
    {
        $this->assertSame(
            ThrottleRequests::class,
            $this->app['router']->getMiddleware()['throttle']
        );
    }
}
