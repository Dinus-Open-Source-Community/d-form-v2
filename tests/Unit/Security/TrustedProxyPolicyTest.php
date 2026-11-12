<?php

namespace Tests\Unit\Security;

use App\Support\Security\TrustedProxyPolicy;
use Illuminate\Http\Request;
use RuntimeException;
use Tests\TestCase;

class TrustedProxyPolicyTest extends TestCase
{
    private const DEFAULT_PROXIES = [
        '10.0.0.0/8',
        '172.16.0.0/12',
        '192.168.0.0/16',
        '127.0.0.1',
        '::1',
    ];

    private const HEADERS = Request::HEADER_X_FORWARDED_FOR
        | Request::HEADER_X_FORWARDED_PROTO
        | Request::HEADER_X_FORWARDED_HOST
        | Request::HEADER_X_FORWARDED_PORT;

    private function policy(bool $isProduction = false): TrustedProxyPolicy
    {
        return new TrustedProxyPolicy($isProduction);
    }

    public function test_empty_input_falls_back_to_private_docker_defaults(): void
    {
        foreach ([null, '', '   ', ' , , '] as $value) {
            $resolved = $this->policy()->resolve($value, self::HEADERS);

            $this->assertSame(self::DEFAULT_PROXIES, $resolved['proxies'], 'Input: '.var_export($value, true));
        }
    }

    public function test_comma_separated_string_is_split_and_trimmed(): void
    {
        $resolved = $this->policy()->resolve(' 10.0.0.1 , 192.168.1.0/24 ', self::HEADERS);

        $this->assertSame(['10.0.0.1', '192.168.1.0/24'], $resolved['proxies']);
    }

    public function test_array_input_is_used_as_is(): void
    {
        $resolved = $this->policy()->resolve(['10.0.0.1', '::1'], self::HEADERS);

        $this->assertSame(['10.0.0.1', '::1'], $resolved['proxies']);
    }

    public function test_duplicates_and_blank_entries_are_dropped_preserving_order(): void
    {
        $resolved = $this->policy()->resolve(['10.0.0.1', '', '10.0.0.1', '  10.0.0.2  ', '10.0.0.2'], self::HEADERS);

        $this->assertSame(['10.0.0.1', '10.0.0.2'], $resolved['proxies']);
    }

    public function test_wildcard_is_rejected_in_production(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/TRUSTED_PROXIES/');

        $this->policy(true)->resolve('*', self::HEADERS);
    }

    public function test_double_wildcard_is_rejected_in_production(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/TRUSTED_PROXIES/');

        $this->policy(true)->resolve(['**'], self::HEADERS);
    }

    public function test_wildcard_is_accepted_outside_production(): void
    {
        $resolved = $this->policy()->resolve('*', self::HEADERS);

        $this->assertSame(['*'], $resolved['proxies']);
    }

    public function test_ipv4_cidr_is_accepted(): void
    {
        $resolved = $this->policy()->resolve(['10.0.0.0/8', '203.0.113.7/32'], self::HEADERS);

        $this->assertSame(['10.0.0.0/8', '203.0.113.7/32'], $resolved['proxies']);
    }

    public function test_ipv6_cidr_is_accepted(): void
    {
        $resolved = $this->policy()->resolve(['::1/128', '2001:db8::/32'], self::HEADERS);

        $this->assertSame(['::1/128', '2001:db8::/32'], $resolved['proxies']);
    }

    public function test_invalid_ip_throws(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/TRUSTED_PROXIES/');
        $this->expectExceptionMessageMatches('/not-an-ip/');

        $this->policy()->resolve('not-an-ip', self::HEADERS);
    }

    public function test_invalid_cidr_throws(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/10.0.0.0\/x/');

        $this->policy()->resolve('10.0.0.0/x', self::HEADERS);
    }

    public function test_out_of_range_prefix_throws(): void
    {
        $this->expectException(RuntimeException::class);

        $this->policy()->resolve('10.0.0.0/33', self::HEADERS);
    }

    public function test_ipv6_prefix_beyond_128_throws(): void
    {
        $this->expectException(RuntimeException::class);

        $this->policy()->resolve('2001:db8::/129', self::HEADERS);
    }

    public function test_remote_addr_token_is_passed_through(): void
    {
        $resolved = $this->policy()->resolve('REMOTE_ADDR', self::HEADERS);

        $this->assertSame(['REMOTE_ADDR'], $resolved['proxies']);
    }

    public function test_remote_addr_token_is_accepted_in_production(): void
    {
        $resolved = $this->policy(true)->resolve('REMOTE_ADDR', self::HEADERS);

        $this->assertSame(['REMOTE_ADDR'], $resolved['proxies']);
    }

    public function test_zero_prefix_cidr_is_rejected_in_production(): void
    {
        foreach (['0.0.0.0/0', '::/0', '10.0.0.0/0'] as $cidr) {
            try {
                $this->policy(true)->resolve($cidr, self::HEADERS);
                $this->fail('Expected RuntimeException for '.$cidr);
            } catch (RuntimeException $exception) {
                $this->assertStringContainsString('TRUSTED_PROXIES', $exception->getMessage(), $cidr);
            }
        }
    }

    public function test_zero_prefix_cidr_is_accepted_outside_production(): void
    {
        $resolved = $this->policy()->resolve(['0.0.0.0/0', '::/0', '10.0.0.0/0'], self::HEADERS);

        $this->assertSame(['0.0.0.0/0', '::/0', '10.0.0.0/0'], $resolved['proxies']);
    }

    public function test_non_string_array_entry_reports_its_type(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/TRUSTED_PROXIES/');
        $this->expectExceptionMessageMatches('/int/');

        $this->policy()->resolve([123], self::HEADERS);
    }

    public function test_boolean_env_value_is_rejected_instead_of_selecting_defaults(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/boolean/');

        $this->policy()->resolve(false, self::HEADERS);
    }

    public function test_non_positive_headers_throw_instead_of_falling_back(): void
    {
        foreach ([0, -1] as $headers) {
            try {
                $this->policy()->resolve('10.0.0.1', $headers);
                $this->fail('Expected RuntimeException for headers '.$headers);
            } catch (RuntimeException $exception) {
                $this->assertStringContainsString('TRUSTED_PROXIES', $exception->getMessage());
            }
        }
    }

    public function test_missing_default_proxies_throws(): void
    {
        config(['trusted_proxies.default_proxies' => null]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/TRUSTED_PROXIES/');

        $this->policy()->resolve(null, Request::HEADER_X_FORWARDED_FOR);
    }

    public function test_non_array_default_proxies_throws(): void
    {
        config(['trusted_proxies.default_proxies' => '10.0.0.0/8']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/TRUSTED_PROXIES/');

        $this->policy()->resolve('', Request::HEADER_X_FORWARDED_FOR);
    }

    public function test_empty_default_proxies_throws(): void
    {
        config(['trusted_proxies.default_proxies' => []]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/TRUSTED_PROXIES/');

        $this->policy()->resolve(null, Request::HEADER_X_FORWARDED_FOR);
    }

    public function test_default_proxies_with_only_non_strings_throws(): void
    {
        config(['trusted_proxies.default_proxies' => [123, false]]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/TRUSTED_PROXIES/');

        $this->policy()->resolve('   ', Request::HEADER_X_FORWARDED_FOR);
    }

    public function test_explicit_headers_are_used(): void
    {
        $resolved = $this->policy()->resolve(null, Request::HEADER_X_FORWARDED_FOR);

        $this->assertSame(Request::HEADER_X_FORWARDED_FOR, $resolved['headers']);
    }
}
