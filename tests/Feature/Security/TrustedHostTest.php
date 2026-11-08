<?php

namespace Tests\Feature\Security;

use Illuminate\Contracts\Http\Kernel as HttpKernelContract;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Tests\TestCase;

class TrustedHostTest extends TestCase
{
    private const PROBE = '/__test/host-probe';

    protected function setUp(): void
    {
        parent::setUp();

        config(['trusted_hosts.hosts' => '']);

        $this->registerProbeRoute();
    }

    private function registerProbeRoute(): void
    {
        Route::get(self::PROBE, static fn (Request $request) => response()->json([
            'host' => $request->getHost(),
            'url' => $request->url(),
        ]));
    }

    public function test_allowlisted_host_is_served(): void
    {
        $this->get(self::PROBE)
            ->assertOk()
            ->assertJsonPath('host', 'localhost');
    }

    public function test_untrusted_host_is_rejected_with_a_plain_bad_request(): void
    {
        $response = $this->get('http://evil.example'.self::PROBE);

        $response->assertStatus(400);
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $this->assertSame('Bad Request', $response->getContent());
        $this->assertStringNotContainsString('evil.example', (string) $response->getContent());
        $this->assertStringNotContainsString('Whoops', (string) $response->getContent());
        $this->assertStringNotContainsString('Stack trace', (string) $response->getContent());
        $this->assertStringNotContainsString('Illuminate\\', (string) $response->getContent());
    }

    public function test_host_lookalike_is_not_treated_as_localhost(): void
    {
        $this->get('http://evil-localhost.com'.self::PROBE)->assertStatus(400);
    }

    public function test_subdomain_of_an_allowlisted_host_is_not_trusted_automatically(): void
    {
        $this->get('http://api.localhost'.self::PROBE)->assertStatus(400);
    }

    public function test_host_comparison_is_case_insensitive(): void
    {
        $this->get('http://LOCALHOST'.self::PROBE)
            ->assertOk()
            ->assertJsonPath('host', 'localhost');
    }

    public function test_port_is_stripped_before_the_host_is_validated(): void
    {
        $this->get('http://localhost:8443'.self::PROBE)
            ->assertOk()
            ->assertJsonPath('host', 'localhost');
    }

    public function test_forwarded_host_from_a_trusted_peer_is_validated(): void
    {
        $response = $this->withServerVariables(['REMOTE_ADDR' => '10.1.2.3'])
            ->withHeaders(['X-Forwarded-Host' => 'evil.example'])
            ->get(self::PROBE);

        $response->assertStatus(400);
        $this->assertStringNotContainsString('evil.example', (string) $response->getContent());
    }

    public function test_forwarded_host_from_an_untrusted_peer_is_ignored(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.5'])
            ->withHeaders(['X-Forwarded-Host' => 'evil.example'])
            ->get(self::PROBE)
            ->assertOk()
            ->assertJsonPath('host', 'localhost');
    }

    public function test_configured_hosts_are_trusted_without_trusting_their_subdomains(): void
    {
        config(['trusted_hosts.hosts' => 'dform.example,www.dform.example']);

        $this->get('http://dform.example'.self::PROBE)->assertOk();
        $this->get('http://www.dform.example'.self::PROBE)->assertOk();
        $this->get('http://api.dform.example'.self::PROBE)->assertStatus(400);
    }

    public function test_host_with_invalid_characters_is_rejected_without_a_server_error(): void
    {
        // Request::create() rejects a malformed URI host, so the raw header is set
        // after creation to exercise the runtime path a real request takes.
        $symfonyRequest = SymfonyRequest::create('http://localhost'.self::PROBE);
        $symfonyRequest->headers->set('Host', 'evil example');
        $request = Request::createFromBase($symfonyRequest);

        $kernel = $this->app->make(HttpKernelContract::class);
        $response = $kernel->handle($request);
        $kernel->terminate($request, $response);

        $this->createTestResponse($response, $request)->assertStatus(400);
    }

    public function test_loopback_hosts_are_not_trusted_in_production(): void
    {
        config(['app.url' => 'https://dform.example', 'app.env' => 'production']);

        $this->get('http://localhost'.self::PROBE)->assertStatus(400);

        $this->get('https://dform.example'.self::PROBE)
            ->assertOk()
            ->assertJsonPath('host', 'dform.example');
    }

    public function test_loopback_hosts_are_not_trusted_in_staging(): void
    {
        config(['app.url' => 'https://dform.example', 'app.env' => 'staging']);

        $this->get('http://localhost'.self::PROBE)->assertStatus(400);

        $this->get('https://dform.example'.self::PROBE)
            ->assertOk()
            ->assertJsonPath('host', 'dform.example');
    }

    public function test_trusted_hosts_env_is_wired_end_to_end(): void
    {
        $original = $_SERVER['TRUSTED_HOSTS'] ?? null;
        $_SERVER['TRUSTED_HOSTS'] = 'env-host.example';

        try {
            $this->refreshApplication();
            $this->registerProbeRoute();

            $this->assertSame('env-host.example', config('trusted_hosts.hosts'));

            $this->get('http://env-host.example'.self::PROBE)
                ->assertOk()
                ->assertJsonPath('host', 'env-host.example');

            $this->get('http://evil.example'.self::PROBE)->assertStatus(400);
        } finally {
            if ($original === null) {
                unset($_SERVER['TRUSTED_HOSTS']);
            } else {
                $_SERVER['TRUSTED_HOSTS'] = $original;
            }

            $this->refreshApplication();
            $this->registerProbeRoute();
        }
    }

    public function test_static_trusted_host_patterns_are_reset_after_each_request(): void
    {
        $this->get('http://evil.example'.self::PROBE)->assertStatus(400);
        $this->assertSame([], SymfonyRequest::getTrustedHosts());

        $this->get('http://localhost'.self::PROBE)->assertOk();
        $this->assertSame([], SymfonyRequest::getTrustedHosts());
    }

    public function test_json_clients_receive_a_json_bad_request_without_the_reflected_host(): void
    {
        $response = $this->get('http://evil.example'.self::PROBE, ['Accept' => 'application/json']);

        $response->assertStatus(400);
        $response->assertHeader('Content-Type', 'application/json');
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $response->assertJson(['message' => 'Bad Request']);
        $this->assertStringNotContainsString('evil.example', (string) $response->getContent());
    }

    public function test_rejection_does_not_leak_trusted_host_state_into_later_requests(): void
    {
        $this->get('http://evil.example/up')->assertStatus(400);

        // Absolute URL: the url() helper between test requests still resolves the
        // root of the previous request, which is itself what this ticket forbids.
        $this->get('http://localhost/up')->assertOk();

        $this->get('http://localhost'.self::PROBE)
            ->assertOk()
            ->assertJsonPath('host', 'localhost');
    }

    public function test_untrusted_host_rejection_is_logged_without_reflecting_the_host_in_the_response(): void
    {
        Log::spy();

        $this->get('http://evil.example'.self::PROBE)->assertStatus(400);

        Log::shouldHaveReceived('warning')
            ->once()
            ->withArgs(static function (string $event, array $context): bool {
                return $event === 'http_400_untrusted_host'
                    && $context['host'] === 'evil.example'
                    && $context['x_forwarded_host'] === null
                    && $context['ip'] === '127.0.0.1'
                    && $context['path'] === '__test/host-probe';
            });
    }

    public function test_forwarded_host_rejection_logs_the_forwarded_value(): void
    {
        Log::spy();

        $this->withServerVariables(['REMOTE_ADDR' => '10.1.2.3'])
            ->withHeaders(['X-Forwarded-Host' => 'evil.example'])
            ->get('http://localhost'.self::PROBE)
            ->assertStatus(400);

        Log::shouldHaveReceived('warning')
            ->once()
            ->withArgs(static function (string $event, array $context): bool {
                return $event === 'http_400_untrusted_host'
                    && $context['host'] === 'localhost'
                    && $context['x_forwarded_host'] === 'evil.example'
                    && $context['ip'] === '10.1.2.3'
                    && $context['path'] === '__test/host-probe';
            });
    }
}
