<?php

namespace Tests\Feature\Export;

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use Tests\TestCase;

/**
 * BE-P3-017: seluruh endpoint export berat memakai limiter bernama.
 *
 * Database test tidak menjalankan MySQL/Octane, jadi biaya sebenarnya tidak
 * relevan di sini; yang dijaga adalah kontrak rute: export baru tidak boleh
 * terdaftar tanpa `throttle:*`.
 */
class HeavyExportRateLimitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_be_p3_017_every_export_route_has_a_named_limiter(): void
    {
        $exportRoutes = collect($this->router()->getRoutes()->getRoutes())
            ->filter(fn (Route $route): bool => $this->isExportRoute($route))
            ->values();

        self::assertGreaterThanOrEqual(5, $exportRoutes->count(), 'Daftar export berubah; periksa tiket BE-P3-017.');

        foreach ($exportRoutes as $route) {
            $limiter = $this->throttleLimiter($route);

            self::assertNotNull(
                $limiter,
                sprintf('Route %s (%s) tidak memiliki throttle bernama.', $route->getName() ?? '-', $route->uri()),
            );
            self::assertNotSame('', $limiter);
        }

        $expected = [
            'dashboard.recruitment.reports.export.funnel' => 'recruitment-export',
            'dashboard.recruitment.reports.export.applicants' => 'recruitment-export',
            'dashboard.recruitment.periods.interviews.export' => 'recruitment-export',
            'dashboard.events.exports.registrations-csv' => 'recruitment-export',
            'dashboard.scan.export' => 'scan-export',
        ];

        foreach ($expected as $routeName => $limiter) {
            $route = $this->router()->getRoutes()->getByName($routeName);

            self::assertInstanceOf(Route::class, $route, "Route {$routeName} hilang.");
            self::assertSame($limiter, $this->throttleLimiter($route), "Route {$routeName} memakai limiter lain.");
        }
    }

    public function test_be_p3_017_global_scan_export_is_throttled(): void
    {
        $admin = $this->admin();
        $path = route('dashboard.scan.export');

        // Permintaan tanpa parameter cukup: limiter dihitung sebelum controller
        // dan respons 422 tetap memakai kuota.
        for ($attempt = 1; $attempt <= 30; $attempt++) {
            $this->actingAs($admin)->get($path)->assertStatus(422);
        }

        $this->actingAs($admin)->get($path)->assertStatus(429)->assertHeader('Retry-After');
    }

    private function router(): Router
    {
        return app(Router::class);
    }

    private function isExportRoute(Route $route): bool
    {
        $name = $route->getName() ?? '';
        $uri = $route->uri();

        return str_contains($name, 'export')
            || str_contains($uri, '/export')
            || str_ends_with($uri, '.csv');
    }

    /**
     * Nama limiter dari middleware throttle yang benar-benar dieksekusi rute.
     * `gatherRouteMiddleware()` mengembang group middleware seperti `web`.
     */
    private function throttleLimiter(Route $route): ?string
    {
        foreach ($this->router()->gatherRouteMiddleware($route) as $middleware) {
            if (is_string($middleware) && str_starts_with($middleware, 'Illuminate\Routing\Middleware\ThrottleRequests:')) {
                return substr($middleware, strrpos($middleware, ':') + 1);
            }
        }

        return null;
    }

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        return $admin;
    }
}
