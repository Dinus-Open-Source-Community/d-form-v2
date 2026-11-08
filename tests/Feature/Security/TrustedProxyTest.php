<?php

namespace Tests\Feature\Security;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Requests resolve client IP, scheme and host through trusted X-Forwarded-* headers.
 *
 * TrustProxies::at() sets static state during boot, so config(['trusted_proxies.*'])
 * cannot change the proxy list inside a running application. The boundary under
 * test here is the peer address (REMOTE_ADDR): trusted peers are the private Docker
 * defaults (10.0.0.0/8, 172.16.0.0/12, 192.168.0.0/16, 127.0.0.1, ::1); anything
 * else is not. Overriding TRUSTED_PROXIES end-to-end is covered by
 * TrustedProxyOverrideTest (env + application rebuild).
 */
class TrustedProxyTest extends TestCase
{
    use RefreshDatabase;

    private const PROBE = '/__test/proxy-context';

    protected function setUp(): void
    {
        parent::setUp();

        Route::get(self::PROBE, static fn (Request $request) => response()->json([
            'ip' => $request->ip(),
            'ips' => $request->ips(),
            'secure' => $request->isSecure(),
            'url' => $request->url(),
            'path' => $request->path(),
        ]));
    }

    public function test_trusted_peer_headers_supply_client_ip_scheme_and_host(): void
    {
        config(['trusted_hosts.hosts' => 'dform.example']);

        $response = $this->withServerVariables(['REMOTE_ADDR' => '10.1.2.3'])
            ->withHeaders([
                'X-Forwarded-For' => '203.0.113.9',
                'X-Forwarded-Proto' => 'https',
                'X-Forwarded-Host' => 'dform.example',
            ])
            ->get(self::PROBE);

        $response->assertOk()
            ->assertJsonPath('ip', '203.0.113.9')
            ->assertJsonPath('secure', true);

        $this->assertStringStartsWith('https://dform.example', (string) $response->json('url'));
    }

    public function test_untrusted_peer_headers_are_ignored(): void
    {
        $response = $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.5'])
            ->withHeaders([
                'X-Forwarded-For' => '203.0.113.9',
                'X-Forwarded-Proto' => 'https',
                'X-Forwarded-Host' => 'dform.example',
            ])
            ->get(self::PROBE);

        $response->assertOk()
            ->assertJsonPath('ip', '198.51.100.5')
            ->assertJsonPath('secure', false);

        $this->assertStringStartsWith('http://', (string) $response->json('url'));
        $this->assertStringNotContainsString('dform.example', (string) $response->json('url'));
    }

    public function test_multi_hop_forwarded_for_skips_trusted_hops(): void
    {
        $response = $this->withServerVariables(['REMOTE_ADDR' => '10.1.2.3'])
            ->withHeaders(['X-Forwarded-For' => '203.0.113.9, 10.9.9.9'])
            ->get(self::PROBE);

        $response->assertOk()->assertJsonPath('ip', '203.0.113.9');
    }

    public function test_client_supplied_leftmost_forwarded_for_entry_is_not_used(): void
    {
        $response = $this->withServerVariables(['REMOTE_ADDR' => '10.1.2.3'])
            ->withHeaders(['X-Forwarded-For' => '1.2.3.4, 203.0.113.9'])
            ->get(self::PROBE);

        $response->assertOk()->assertJsonPath('ip', '203.0.113.9');
    }

    public function test_rate_limits_are_bucketed_per_forwarded_client_ip(): void
    {
        config(['events.search.rate_limit_per_minute' => 2]);

        $this->withServerVariables(['REMOTE_ADDR' => '10.1.2.3'])
            ->withHeaders(['X-Forwarded-For' => '203.0.113.10'])
            ->get('/events')
            ->assertOk();

        $this->get('/events')->assertOk();
        $this->get('/events')->assertStatus(429);

        $this->withHeaders(['X-Forwarded-For' => '203.0.113.11'])
            ->get('/events')
            ->assertOk();
    }

    public function test_trusted_peer_without_forwarded_proto_stays_insecure(): void
    {
        $response = $this->withServerVariables(['REMOTE_ADDR' => '10.1.2.3'])
            ->withHeaders(['X-Forwarded-For' => '203.0.113.9'])
            ->get(self::PROBE);

        $response->assertOk()
            ->assertJsonPath('ip', '203.0.113.9')
            ->assertJsonPath('secure', false);
    }

    public function test_forwarded_prefix_header_is_not_trusted(): void
    {
        $response = $this->withServerVariables(['REMOTE_ADDR' => '10.1.2.3'])
            ->withHeaders([
                'X-Forwarded-For' => '203.0.113.9',
                'X-Forwarded-Prefix' => '/evil',
            ])
            ->get(self::PROBE);

        $response->assertOk()
            ->assertJsonPath('path', '__test/proxy-context');

        $this->assertStringNotContainsString('/evil', (string) $response->json('url'));
    }

    public function test_ipv4_mapped_ipv6_peer_does_not_match_ipv4_cidr(): void
    {
        // Documented fail-closed behaviour: "::ffff:10.1.2.3" is IPv6 for IpUtils, so the
        // IPv4 allowlist does not match and the forwarded headers are ignored.
        $response = $this->withServerVariables(['REMOTE_ADDR' => '::ffff:10.1.2.3'])
            ->withHeaders([
                'X-Forwarded-For' => '203.0.113.9',
                'X-Forwarded-Proto' => 'https',
            ])
            ->get(self::PROBE);

        $response->assertOk()
            ->assertJsonPath('ip', '::ffff:10.1.2.3')
            ->assertJsonPath('secure', false);
    }

    public function test_forwarded_port_is_used_for_absolute_urls(): void
    {
        config(['trusted_hosts.hosts' => 'dform.example']);

        $response = $this->withServerVariables(['REMOTE_ADDR' => '10.1.2.3'])
            ->withHeaders([
                'X-Forwarded-Proto' => 'https',
                'X-Forwarded-Host' => 'dform.example',
                'X-Forwarded-Port' => '1337',
            ])
            ->get(self::PROBE);

        $response->assertOk()->assertJsonPath('secure', true);

        $this->assertStringStartsWith('https://dform.example:1337/', (string) $response->json('url'));
    }
}
