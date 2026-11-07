<?php

namespace Tests\Feature\Security;

use App\Http\Middleware\SecurityHeaders;
use App\Providers\AppServiceProvider;
use Closure;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Foundation\Http\Kernel as FoundationHttpKernel;
use Illuminate\Foundation\Http\Middleware\PreventRequestsDuringMaintenance;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Http\Middleware\HandleCors;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Middleware\ValidatePathEncoding;
use Illuminate\Http\Middleware\ValidatePostSize;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    public function test_public_response_carries_baseline_security_headers(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $this->assertStringContainsString(
            'camera=(self)',
            (string) $response->headers->get('Permissions-Policy'),
        );
    }

    public function test_csp_contains_expected_hardening_directives(): void
    {
        $response = $this->get('/');

        $directives = $this->cspDirectives($response);

        $this->assertSame(["'self'"], $directives['default-src']);
        $this->assertSame(["'self'"], $directives['frame-ancestors']);
        $this->assertSame(["'none'"], $directives['object-src']);
        $this->assertNotContains("'unsafe-eval'", $directives['script-src']);
        $this->assertNotContains("'unsafe-inline'", $directives['script-src']);
    }

    public function test_csp_dev_allowances_extend_script_and_connect_sources(): void
    {
        config(['security.csp.dev_allowances' => true]);

        $directives = $this->cspDirectives($this->get('/'));

        $this->assertContains("'unsafe-inline'", $directives['script-src']);
        $this->assertContains('http://localhost:5173', $directives['script-src']);
        $this->assertContains('ws://localhost:5173', $directives['connect-src']);
        $this->assertNotContains("'unsafe-eval'", $directives['script-src']);
    }

    public function test_production_csp_adds_upgrade_insecure_requests_and_drops_dev_origins(): void
    {
        // security.hsts.* and security.csp.dev_allowances are not re-read here because
        // config is evaluated at boot; dev allowances default to off outside local.
        $this->withConfiguredEnv('production', function (): void {
            $header = (string) $this->get('/')->headers->get('Content-Security-Policy');

            $this->assertStringContainsString('upgrade-insecure-requests', $header);
            $this->assertStringNotContainsString('localhost:5173', $header);
            $this->assertStringContainsString("object-src 'none'", $header);
            $this->assertStringContainsString("frame-ancestors 'self'", $header);
        });
    }

    public function test_csp_dev_allowances_cannot_be_enabled_in_production_by_env(): void
    {
        // config/security.php is evaluated once at boot, so re-evaluate it with the flag set
        // and app.env=production: the env flag may only ever tighten dev allowances.
        $securityConfig = $this->securityConfigWithEnvironment(
            ['SECURITY_CSP_DEV_ALLOWANCES' => 'true'],
            'production',
        );

        $this->assertFalse($securityConfig['csp']['dev_allowances']);

        config(['security' => $securityConfig]);

        $response = $this->get('/');
        $header = (string) $response->headers->get('Content-Security-Policy');

        $this->assertNotContains("'unsafe-inline'", $this->cspDirectives($response)['script-src']);
        $this->assertStringNotContainsString('localhost:5173', $header);
    }

    public function test_non_string_csp_mode_fails_config_evaluation_instead_of_disabling_csp(): void
    {
        // SECURITY_CSP_MODE=false reaches the config as a boolean (Laravel's Env mapper),
        // which must fail loudly rather than normalize to '' and silently disable CSP.
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/SECURITY_CSP_MODE/');

        $this->securityConfigWithEnvironment(['SECURITY_CSP_MODE' => 'false']);
    }

    public function test_csp_has_no_upgrade_insecure_requests_outside_production(): void
    {
        $this->assertStringNotContainsString(
            'upgrade-insecure-requests',
            (string) $this->get('/')->headers->get('Content-Security-Policy'),
        );
    }

    public function test_every_csp_directive_covers_the_external_origins_the_app_uses(): void
    {
        $directives = $this->cspDirectives($this->get('/'));

        $this->assertContains('https://seeyu.doscom.org', $directives['script-src']);
        $this->assertContains('https://seeyu.doscom.org', $directives['connect-src']);
        $this->assertContains('https://fonts.googleapis.com', $directives['style-src']);
        $this->assertContains('https://fonts.gstatic.com', $directives['font-src']);
        $this->assertContains('https://maps.google.com', $directives['frame-src']);
        // The maps embed redirects to www.google.com; the browser checks the redirect target.
        $this->assertContains('https://www.google.com', $directives['frame-src']);
        $this->assertContains('https://www.youtube-nocookie.com', $directives['frame-src']);
        $this->assertContains('data:', $directives['img-src']);
        $this->assertContains('blob:', $directives['img-src']);
    }

    public function test_csp_report_only_mode_uses_report_only_header(): void
    {
        config(['security.csp.mode' => 'report-only']);

        $response = $this->get('/');

        $response->assertHeader('Content-Security-Policy-Report-Only');
        $response->assertHeaderMissing('Content-Security-Policy');
        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
    }

    public function test_csp_off_mode_omits_csp_headers_but_keeps_other_headers(): void
    {
        config(['security.csp.mode' => 'off']);

        $response = $this->get('/');

        $response->assertHeaderMissing('Content-Security-Policy');
        $response->assertHeaderMissing('Content-Security-Policy-Report-Only');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
    }

    public function test_unrecognized_csp_mode_fails_closed_with_the_enforcing_header(): void
    {
        // A non-string mode (e.g. a stale cached config or a boolean) must never silently
        // drop the policy: only the literal 'off' skips CSP.
        config(['security.csp.mode' => false]);

        $response = $this->get('/');

        $response->assertHeader('Content-Security-Policy');
        $response->assertHeaderMissing('Content-Security-Policy-Report-Only');
        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
    }

    public function test_csp_report_uri_is_appended_when_configured(): void
    {
        config(['security.csp.report_uri' => 'https://seeyu.doscom.org/csp-report']);

        $this->assertStringContainsString(
            'report-uri https://seeyu.doscom.org/csp-report',
            (string) $this->get('/')->headers->get('Content-Security-Policy'),
        );
    }

    public function test_hsts_is_emitted_with_configured_max_age(): void
    {
        // The config default follows APP_ENV, so testing must opt in explicitly.
        config(['security.hsts.enabled' => true, 'security.hsts.max_age' => 31536000]);

        $this->get('/')->assertHeader('Strict-Transport-Security', 'max-age=31536000');
    }

    public function test_hsts_can_be_disabled(): void
    {
        config(['security.hsts.enabled' => false]);

        $this->get('/')->assertHeaderMissing('Strict-Transport-Security');
    }

    public function test_hsts_include_subdomains_follows_config(): void
    {
        config(['security.hsts.enabled' => true, 'security.hsts.include_subdomains' => true]);

        $this->get('/')->assertHeader(
            'Strict-Transport-Security',
            'max-age=31536000; includeSubDomains',
        );
    }

    public function test_hsts_falls_back_when_max_age_is_not_positive(): void
    {
        config(['security.hsts.enabled' => true, 'security.hsts.max_age' => 0]);

        $this->get('/')->assertHeader('Strict-Transport-Security', 'max-age=31536000');
    }

    public function test_short_circuited_503_response_still_receives_headers(): void
    {
        // The middleware is prepended globally, so its response phase runs after any
        // middleware that short-circuits (maintenance, throttling, redirects).
        $response = (new SecurityHeaders())->handle(
            Request::create('/'),
            static fn (): Response => new Response('maintenance', 503),
        );

        $this->assertSame(503, $response->getStatusCode());
        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        $this->assertSame('SAMEORIGIN', $response->headers->get('X-Frame-Options'));
        $this->assertNotNull($response->headers->get('Content-Security-Policy'));
    }

    public function test_security_headers_is_prepended_to_the_global_middleware_stack(): void
    {
        $kernel = $this->app->make(HttpKernel::class);

        $this->assertInstanceOf(FoundationHttpKernel::class, $kernel);

        // getGlobalMiddleware() exposes the resolved, ordered list on
        // Illuminate\Foundation\Http\Kernel; the contract itself does not declare it.
        $global = $kernel->getGlobalMiddleware();
        $position = array_search(SecurityHeaders::class, $global, true);

        $this->assertIsInt($position, SecurityHeaders::class.' must be registered as global middleware.');

        // No application middleware may run before it, so the headers cannot be skipped by
        // an app-level short circuit either.
        foreach (array_slice($global, 0, $position) as $preceding) {
            $this->assertStringStartsNotWith(
                'App\\',
                (string) $preceding,
                'Only package middleware may precede '.SecurityHeaders::class.'.',
            );
        }

        // Inertia prepends its own deferred-callback middleware at package boot, so the
        // literal first slot belongs to Inertia. What must hold is that ours precedes every
        // middleware Laravel registers globally: prepend() puts it there, while append()
        // would place it after all of them and short-circuited responses (maintenance 503,
        // CORS preflight, oversize POST 413) would lose the headers.
        foreach ([
            ValidatePathEncoding::class,
            TrustProxies::class,
            HandleCors::class,
            PreventRequestsDuringMaintenance::class,
            ValidatePostSize::class,
        ] as $frameworkMiddleware) {
            $frameworkPosition = array_search($frameworkMiddleware, $global, true);

            $this->assertIsInt($frameworkPosition);
            $this->assertLessThan(
                $frameworkPosition,
                $position,
                SecurityHeaders::class.' must precede '.$frameworkMiddleware.' so short-circuited responses still get headers.',
            );
        }
    }

    public function test_production_boot_fails_when_session_cookie_is_insecure(): void
    {
        // The policy resolves production through config('app.env'), which is also what
        // survives `config:cache` at runtime.
        $this->withConfiguredEnv('production', function (): void {
            config(['session.secure' => false]);

            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessageMatches('/SESSION_SECURE_COOKIE/');

            (new AppServiceProvider($this->app))->boot();
        });
    }

    public function test_not_found_response_carries_security_headers(): void
    {
        $response = $this->get('/this-route-does-not-exist-be-p3-009');

        $response->assertNotFound();
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
    }

    public function test_health_endpoint_carries_security_headers(): void
    {
        $response = $this->get('/up');

        $response->assertOk();
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
    }

    public function test_session_and_xsrf_cookies_carry_secure_and_httponly_attributes_when_enabled(): void
    {
        // The base TestCase stubs out CSRF validation, so restore it to emit XSRF-TOKEN.
        $this->app->forgetInstance(ValidateCsrfToken::class);
        config(['session.secure' => true]);

        $response = $this->get('/');

        $cookies = $response->headers->getCookies();
        $session = $this->findCookie($cookies, (string) config('session.cookie'));
        $xsrf = $this->findCookie($cookies, 'XSRF-TOKEN');

        $this->assertNotNull($session, 'Session cookie must be present.');
        $this->assertNotNull($xsrf, 'XSRF-TOKEN cookie must be present.');

        // Secure follows session.secure; XSRF-TOKEN is intentionally readable by JS (httpOnly false).
        foreach ([$session, $xsrf] as $cookie) {
            $this->assertTrue($cookie->isSecure());
            $this->assertSame('lax', $cookie->getSameSite());
        }

        $this->assertTrue($session->isHttpOnly());
        $this->assertFalse($xsrf->isHttpOnly());
    }

    public function test_session_cookie_is_not_secure_outside_https_environments(): void
    {
        // Local/testing serves over plain http, so the default session cookie must not be Secure.
        $response = $this->get('/');

        $session = $this->findCookie($response->headers->getCookies(), (string) config('session.cookie'));

        $this->assertNotNull($session, 'Session cookie must be present.');
        $this->assertFalse($session->isSecure());
    }

    public function test_controller_set_header_is_not_overwritten_or_duplicated(): void
    {
        Route::middleware('web')->get(
            '/__p3_009_header_probe',
            static fn (): HttpResponse => response('probe', 200, [
                'X-Content-Type-Options' => 'controller-value',
            ]),
        );

        $response = $this->get('/__p3_009_header_probe');

        $response->assertOk();
        $response->assertHeader('X-Content-Type-Options', 'controller-value');
        $this->assertCount(1, $response->headers->all('X-Content-Type-Options'));
    }

    /**
     * Re-evaluate config/security.php with env overrides and an optional configured APP_ENV.
     *
     * Config files are plain arrays evaluated once at boot, so re-requiring the file is the
     * only way to prove the env fail-safes without restarting the application.
     *
     * @param  array<string, string>  $envOverrides
     * @return array<string, mixed>
     */
    private function securityConfigWithEnvironment(array $envOverrides, ?string $appEnv = null): array
    {
        $previousConfigEnv = config('app.env');
        $previousEnvValues = [];

        foreach ($envOverrides as $name => $value) {
            $previousEnvValues[$name] = [
                'server' => $_SERVER[$name] ?? null,
                'env' => $_ENV[$name] ?? null,
                'putenv' => getenv($name),
            ];

            $_SERVER[$name] = $value;
            $_ENV[$name] = $value;
            putenv($name.'='.$value);
        }

        if ($appEnv !== null) {
            config(['app.env' => $appEnv]);
        }

        try {
            /** @var array<string, mixed> $evaluated */
            $evaluated = require config_path('security.php');

            return $evaluated;
        } finally {
            config(['app.env' => $previousConfigEnv]);

            foreach ($previousEnvValues as $name => $previous) {
                $this->restoreEnvValue($name, $previous);
            }
        }
    }

    /**
     * @param  array{server: mixed, env: mixed, putenv: string|false}  $previous
     */
    private function restoreEnvValue(string $name, array $previous): void
    {
        if ($previous['server'] === null) {
            unset($_SERVER[$name]);
        } else {
            $_SERVER[$name] = $previous['server'];
        }

        if ($previous['env'] === null) {
            unset($_ENV[$name]);
        } else {
            $_ENV[$name] = $previous['env'];
        }

        if ($previous['putenv'] === false) {
            putenv($name);
        } else {
            putenv($name.'='.$previous['putenv']);
        }
    }

    /**
     * Run the callback with a different configured app environment.
     */
    private function withConfiguredEnv(string $env, Closure $callback): void
    {
        $original = config('app.env');

        config(['app.env' => $env]);

        try {
            $callback();
        } finally {
            config(['app.env' => $original]);
        }
    }

    /**
     * @param  array<int, Cookie>  $cookies
     */
    private function findCookie(array $cookies, string $name): ?Cookie
    {
        foreach ($cookies as $cookie) {
            if ($cookie->getName() === $name) {
                return $cookie;
            }
        }

        return null;
    }

    /**
     * @return array<string, list<string>>
     */
    private function cspDirectives(TestResponse $response, string $header = 'Content-Security-Policy'): array
    {
        $headerValue = (string) $response->headers->get($header, '');
        $this->assertNotSame('', $headerValue, "Header {$header} must be present.");

        $directives = [];

        foreach (explode(';', $headerValue) as $segment) {
            $segment = trim($segment);

            if ($segment === '') {
                continue;
            }

            $parts = preg_split('/\s+/', $segment) ?: [];
            $name = (string) array_shift($parts);
            $directives[$name] = array_values($parts);
        }

        return $directives;
    }
}
