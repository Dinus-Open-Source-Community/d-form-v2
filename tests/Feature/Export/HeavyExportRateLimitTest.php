<?php

namespace Tests\Feature\Export;

use App\Enums\EventFormVisibility;
use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\EventAttendance;
use App\Models\Form;
use App\Models\FormAnswer;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Log;
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

        self::assertGreaterThanOrEqual(6, $exportRoutes->count(), 'Daftar export berubah; periksa tiket BE-P3-017.');

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
            'dashboard.events.exports.attendance-csv' => 'recruitment-export',
            'dashboard.scan.export' => 'scan-export',
        ];

        foreach ($expected as $routeName => $limiter) {
            $route = $this->router()->getRoutes()->getByName($routeName);

            self::assertInstanceOf(Route::class, $route, "Route {$routeName} hilang.");
            self::assertSame($limiter, $this->throttleLimiter($route), "Route {$routeName} memakai limiter lain.");
        }
    }

    public function test_be_p3_017_event_attendance_export_is_throttled(): void
    {
        config(['recruitment.export_rate_limit_per_minute' => 1]);
        Log::spy();

        $event = $this->eventWithAttendance();
        $admin = $this->admin();

        $this->actingAs($admin)
            ->get(route('dashboard.events.exports.attendance-csv', $event))
            ->assertOk();

        $this->actingAs($admin)
            ->get(route('dashboard.events.exports.attendance-csv', $event))
            ->assertStatus(429)
            ->assertHeader('Retry-After');

        // Kontrak observability BE-P3-017: 429 tercatat pada logger yang sudah ada.
        Log::shouldHaveReceived('warning')
            ->withArgs(static fn (string $message, array $context): bool => $message === 'http_429_throttle'
                && ($context['route'] ?? null) === 'dashboard.events.exports.attendance-csv')
            ->once();
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

    private function eventWithAttendance(): Event
    {
        $event = Event::factory()->create([
            'status' => EventStatus::Published,
            'registration_start' => now()->subDays(7),
            'registration_end' => now()->addDays(30),
        ]);
        $form = Form::factory()->create([
            'event_id' => $event->id,
            'title' => 'Registration',
            'visible_for' => [EventFormVisibility::Public->value],
            'closed_at' => now()->addDays(30),
        ]);
        $participant = User::factory()->create();
        $answer = FormAnswer::factory()->create([
            'form_id' => $form->id,
            'user_id' => $participant->id,
        ]);
        EventAttendance::query()->create([
            'event_id' => $event->id,
            'form_answer_id' => $answer->id,
            'scanned_by_user_id' => $this->admin()->id,
            'scanned_at' => now(),
        ]);

        return $event;
    }
}
