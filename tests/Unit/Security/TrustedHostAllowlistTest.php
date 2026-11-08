<?php

namespace Tests\Unit\Security;

use App\Support\Security\TrustedHostAllowlist;
use RuntimeException;
use Tests\TestCase;

class TrustedHostAllowlistTest extends TestCase
{
    private function allowlist(bool $includeLoopbackHosts = false): TrustedHostAllowlist
    {
        return new TrustedHostAllowlist($includeLoopbackHosts);
    }

    /**
     * Evaluate a pattern the same way Symfony does: wrapped in `{...}i`.
     */
    private function patternMatches(string $pattern, string $host): bool
    {
        return preg_match('{'.$pattern.'}i', $host) === 1;
    }

    public function test_app_url_host_yields_a_single_anchored_pattern(): void
    {
        $patterns = $this->allowlist()->patterns('', 'http://localhost:3000');

        $this->assertCount(1, $patterns);
        $this->assertStringStartsWith('^', $patterns[0]);
        $this->assertStringEndsWith('$', $patterns[0]);
        $this->assertTrue($this->patternMatches($patterns[0], 'localhost'));
    }

    public function test_app_url_host_pattern_does_not_match_subdomains_or_similar_hosts(): void
    {
        $pattern = $this->allowlist()->patterns(null, 'https://dform.example')[0];

        foreach (['api.dform.example', 'dform.example.evil.com', 'evil-dform.example', 'dform.example.evil', 'dformxexample'] as $host) {
            $this->assertFalse($this->patternMatches($pattern, $host), 'Unexpected match for '.$host);
        }
    }

    public function test_unescaped_host_substring_does_not_match(): void
    {
        $pattern = $this->allowlist()->patterns(null, 'http://localhost')[0];

        $this->assertFalse(
            $this->patternMatches($pattern, 'evil-localhost.com'),
            'Pattern must be anchored and escaped so "localhost" cannot match a substring.',
        );
    }

    public function test_comma_separated_string_is_trimmed_lowercased_and_deduplicated(): void
    {
        $patterns = $this->allowlist()->patterns(
            ' DFORM.example , www.dform.example ,, DFORM.example ',
            'http://localhost',
        );

        $this->assertCount(3, $patterns);
        $this->assertTrue($this->patternMatches($patterns[1], 'dform.example'));
        $this->assertTrue($this->patternMatches($patterns[2], 'www.dform.example'));
    }

    public function test_array_input_is_accepted(): void
    {
        $patterns = $this->allowlist()->patterns(
            ['dform.example', 'www.dform.example'],
            'http://localhost',
        );

        $this->assertCount(3, $patterns);
        $this->assertTrue($this->patternMatches($patterns[1], 'dform.example'));
        $this->assertTrue($this->patternMatches($patterns[2], 'www.dform.example'));
    }

    public function test_boolean_value_is_rejected(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/TRUSTED_HOSTS/');

        $this->allowlist()->patterns(false, 'http://localhost');
    }

    public function test_non_string_array_entry_is_rejected(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/TRUSTED_HOSTS/');

        $this->allowlist()->patterns(['dform.example', 42], 'http://localhost');
    }

    public function test_app_url_without_host_is_rejected(): void
    {
        foreach (['', '/relative', 'localhost'] as $appUrl) {
            try {
                $this->allowlist()->patterns('', $appUrl);
                $this->fail('APP_URL without a host must throw: '.var_export($appUrl, true));
            } catch (RuntimeException $exception) {
                $this->assertStringContainsString('APP_URL', $exception->getMessage());
            }
        }
    }

    public function test_entries_with_scheme_path_port_wildcard_space_or_invalid_characters_are_rejected(): void
    {
        $invalid = [
            'https://dform.example',
            'dform.example/path',
            'dform.example:8443',
            '*.dform.example',
            'dform.example*',
            'dform example.com',
            '-dform.example',
            'dform..example',
            'dform_example.com',
        ];

        foreach ($invalid as $entry) {
            try {
                $this->allowlist()->patterns($entry, 'http://localhost');
                $this->fail('Invalid TRUSTED_HOSTS entry must throw: '.$entry);
            } catch (RuntimeException $exception) {
                $this->assertStringContainsString('TRUSTED_HOSTS', $exception->getMessage());
            }
        }
    }

    public function test_ipv4_address_is_accepted(): void
    {
        $patterns = $this->allowlist()->patterns('192.0.2.4', 'http://localhost');

        $this->assertCount(2, $patterns);
        $this->assertTrue($this->patternMatches($patterns[1], '192.0.2.4'));
        $this->assertFalse($this->patternMatches($patterns[1], '192.0.2.45'));
    }

    public function test_ipv6_loopback_is_normalized_to_brackets_for_both_spellings(): void
    {
        foreach (['::1', '[::1]'] as $entry) {
            $patterns = $this->allowlist()->patterns($entry, 'http://localhost');

            $this->assertCount(2, $patterns);
            $this->assertTrue($this->patternMatches($patterns[1], '[::1]'));
            $this->assertFalse($this->patternMatches($patterns[1], '::1'));
        }
    }

    public function test_loopback_hosts_are_added_when_requested(): void
    {
        $patterns = $this->allowlist(true)->patterns('', 'http://app.test');

        $this->assertCount(4, $patterns);
        $this->assertTrue($this->patternMatches($patterns[1], 'localhost'));
        $this->assertTrue($this->patternMatches($patterns[2], '127.0.0.1'));
        $this->assertTrue($this->patternMatches($patterns[3], '[::1]'));
    }

    public function test_loopback_hosts_are_not_added_by_default(): void
    {
        $patterns = $this->allowlist(false)->patterns('', 'https://app.test');

        $this->assertCount(1, $patterns);
        $this->assertFalse($this->patternMatches($patterns[0], 'localhost'));
        $this->assertFalse($this->patternMatches($patterns[0], '127.0.0.1'));
        $this->assertFalse($this->patternMatches($patterns[0], '[::1]'));
    }

    public function test_hostname_with_underscores_is_accepted_and_still_anchored(): void
    {
        $patterns = $this->allowlist()->patterns('prod_d-form-app-core', 'https://app.test');

        $this->assertCount(2, $patterns);
        $this->assertTrue($this->patternMatches($patterns[1], 'prod_d-form-app-core'));
        $this->assertFalse($this->patternMatches($patterns[1], 'evil-prod_d-form-app-core'));
        $this->assertFalse($this->patternMatches($patterns[1], 'prod_d-form-app-core.evil.example'));
    }

    public function test_host_present_in_both_app_url_and_trusted_hosts_is_deduplicated(): void
    {
        $patterns = $this->allowlist()->patterns('app.test, extra.test', 'https://app.test');

        $this->assertCount(2, $patterns);
        $this->assertTrue($this->patternMatches($patterns[0], 'app.test'));
        $this->assertTrue($this->patternMatches($patterns[1], 'extra.test'));
    }

    public function test_loopback_entries_are_accepted_from_config(): void
    {
        $patterns = $this->allowlist(true)->patterns(['localhost', '127.0.0.1', '[::1]'], 'http://localhost');

        $this->assertCount(3, $patterns);
    }
}
