<?php

namespace Tests\Feature\Stress;

use App\Http\Middleware\StressBypassThrottle;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ThrottleBypassEnabledTest extends TestCase
{
    private ?string $originalAppEnvironment = null;

    protected function setUp(): void
    {
        $this->originalAppEnvironment = $_SERVER['APP_ENV'] ?? null;

        // Diterapkan sebelum aplikasi dibuat agar config/stress.php membaca flag ini saat boot.
        $_SERVER['STRESS_BYPASS_THROTTLE'] = 'true';
        $_ENV['STRESS_BYPASS_THROTTLE'] = 'true';

        parent::setUp();

        Route::middleware('throttle:1,1')->get('/__stress_bypass', fn (): string => 'ok');
    }

    protected function tearDown(): void
    {
        unset($_SERVER['STRESS_BYPASS_THROTTLE'], $_ENV['STRESS_BYPASS_THROTTLE']);

        if ($this->originalAppEnvironment !== null) {
            $_SERVER['APP_ENV'] = $this->originalAppEnvironment;
            $_ENV['APP_ENV'] = $this->originalAppEnvironment;
        }

        parent::tearDown();
    }

    public function test_bypass_keeps_requests_ok(): void
    {
        $this->get('/__stress_bypass')->assertOk();
        $this->get('/__stress_bypass')->assertOk();

        $this->assertSame(
            StressBypassThrottle::class,
            $this->app['router']->getMiddleware()['throttle']
        );
    }

    public function test_bypass_ignored_when_environment_is_production(): void
    {
        $_SERVER['APP_ENV'] = 'production';
        $_ENV['APP_ENV'] = 'production';

        $this->refreshApplication();

        $this->assertSame('production', config('app.env'));
        $this->assertTrue((bool) config('stress.bypass_throttle'));
        $this->assertSame(
            ThrottleRequests::class,
            $this->app['router']->getMiddleware()['throttle']
        );
    }
}
