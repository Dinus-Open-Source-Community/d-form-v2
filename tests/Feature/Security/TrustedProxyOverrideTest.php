<?php

namespace Tests\Feature\Security;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Proves TRUSTED_PROXIES is wired end-to-end: config -> policy -> TrustProxies middleware.
 *
 * The list is applied during provider boot, so a case that changes it rebuilds the
 * application. phpunit.xml pins TRUSTED_PROXIES to "" so a developer .env cannot leak in.
 */
class TrustedProxyOverrideTest extends TestCase
{
    private const PROBE = '/__test/proxy-override';

    protected function setUp(): void
    {
        parent::setUp();

        $this->registerProbe();
    }

    public function test_override_replaces_the_default_list_instead_of_extending_it(): void
    {
        $this->withTrustedProxiesEnv('203.0.113.0/24', function (): void {
            // a. Peer inside the override subnet is trusted.
            $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.60'])
                ->withHeaders(['X-Forwarded-For' => '198.51.100.9'])
                ->get(self::PROBE)
                ->assertOk()
                ->assertJsonPath('ip', '198.51.100.9');

            // b. Peer from the old default (10.0.0.0/8) is no longer trusted.
            $this->withServerVariables(['REMOTE_ADDR' => '10.1.2.3'])
                ->withHeaders(['X-Forwarded-For' => '198.51.100.9'])
                ->get(self::PROBE)
                ->assertOk()
                ->assertJsonPath('ip', '10.1.2.3');
        });
    }

    public function test_default_ipv6_loopback_peer_is_trusted(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '::1'])
            ->withHeaders([
                'X-Forwarded-For' => '198.51.100.9',
                'X-Forwarded-Proto' => 'https',
            ])
            ->get(self::PROBE)
            ->assertOk()
            ->assertJsonPath('ip', '198.51.100.9')
            ->assertJsonPath('secure', true);
    }

    private function registerProbe(): void
    {
        Route::get(self::PROBE, static fn (Request $request) => response()->json([
            'ip' => $request->ip(),
            'secure' => $request->isSecure(),
        ]));
    }

    /**
     * Boot a new application with TRUSTED_PROXIES set, then restore the shared static state.
     *
     * @param  callable(): void  $assertions
     */
    private function withTrustedProxiesEnv(string $proxies, callable $assertions): void
    {
        $original = $_SERVER['TRUSTED_PROXIES'] ?? null;
        $_SERVER['TRUSTED_PROXIES'] = $proxies;

        try {
            $this->refreshApplication();
            $this->registerProbe();
            $assertions();
        } finally {
            if ($original === null) {
                unset($_SERVER['TRUSTED_PROXIES']);
            } else {
                $_SERVER['TRUSTED_PROXIES'] = $original;
            }

            $this->refreshApplication();
            $this->registerProbe();
        }
    }
}
