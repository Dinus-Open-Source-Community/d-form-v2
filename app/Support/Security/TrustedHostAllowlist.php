<?php

namespace App\Support\Security;

use RuntimeException;

/** Build a fail-closed host allowlist; APP_URL is always included and invalid entries throw. */
final class TrustedHostAllowlist
{
    /**
     *
     * @param  bool  $includeLoopbackHosts  whether `localhost`, `127.0.0.1` and `[::1]` are trusted
     *
     */
    public function __construct(private readonly bool $includeLoopbackHosts)
    {
    }

    /** Compile anchored patterns; loopback trust is supplied by the caller, not read from APP_ENV.
     * @param  array<int, mixed>|string|bool|null  $configuredHosts  `TRUSTED_HOSTS` value
     * @param  string  $appUrl  `APP_URL`; its host is always trusted
     * @return list<string>
     * @throws RuntimeException when APP_URL has no host or an entry is not an exact host
     */
    public function patterns(mixed $configuredHosts, string $appUrl): array
    {
        $hosts = [$this->appUrlHost($appUrl)];

        foreach ($this->configuredEntries($configuredHosts) as $entry) {
            $hosts[] = $this->normalizeHost($entry, 'TRUSTED_HOSTS');
        }

        if ($this->includeLoopbackHosts) {
            foreach (['localhost', '127.0.0.1', '[::1]'] as $loopbackHost) {
                $hosts[] = $loopbackHost;
            }
        }

        $hosts = array_values(array_unique($hosts));

        return array_map(
            static fn (string $host): string => '^'.preg_quote($host, '/').'$',
            $hosts,
        );
    }

    /**
     *
     * @throws RuntimeException when APP_URL does not contain a usable host
     *
     */
    private function appUrlHost(string $appUrl): string
    {
        $host = parse_url($appUrl, PHP_URL_HOST);

        if (! is_string($host) || trim($host) === '') {
            throw new RuntimeException(sprintf(
                'APP_URL must contain a host so request host validation has a baseline, got: %s.',
                $appUrl === '' ? '(empty)' : $appUrl,
            ));
        }

        return $this->normalizeHost($host, 'APP_URL');
    }

    /**
     *
     * @return list<string>
     *
     * @throws RuntimeException when the value or one of its entries has the wrong type
     *
     */
    private function configuredEntries(mixed $configuredHosts): array
    {
        if ($configuredHosts === null) {
            return [];
        }

        if (is_bool($configuredHosts)) {
            throw new RuntimeException(
                'TRUSTED_HOSTS must be a comma-separated string or an array; boolean values are not accepted.',
            );
        }

        if (is_string($configuredHosts)) {
            $raw = explode(',', $configuredHosts);
        } elseif (is_array($configuredHosts)) {
            $raw = $configuredHosts;
        } else {
            throw new RuntimeException(sprintf(
                'TRUSTED_HOSTS must be a comma-separated string or an array; got %s.',
                get_debug_type($configuredHosts),
            ));
        }

        $entries = [];

        foreach ($raw as $entry) {
            if (! is_string($entry)) {
                throw new RuntimeException(sprintf(
                    'TRUSTED_HOSTS contains an invalid entry of type %s; entries must be exact host names.',
                    get_debug_type($entry),
                ));
            }

            $entry = trim($entry);

            if ($entry !== '') {
                $entries[] = $entry;
            }
        }

        return $entries;
    }

    /**
     *
     * Lowercase an entry and prove it is an exact host (name, IPv4 or IPv6).
     *
     * @throws RuntimeException when the entry is not an exact host
     *
     */
    private function normalizeHost(string $value, string $source): string
    {
        $host = strtolower(trim($value));

        if ($host === '') {
            throw $this->invalid($value, $source);
        }

        if (str_contains($host, '://')
            || str_contains($host, '/')
            || str_contains($host, '*')
            || preg_match('/\s/', $host) === 1
        ) {
            throw $this->invalid($value, $source);
        }

        if (str_starts_with($host, '[') || str_ends_with($host, ']')) {
            if (! str_starts_with($host, '[') || ! str_ends_with($host, ']')) {
                throw $this->invalid($value, $source);
            }

            $unbracketed = substr($host, 1, -1);

            if (filter_var($unbracketed, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) === false) {
                throw $this->invalid($value, $source);
            }

            return '['.$unbracketed.']';
        }

        if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false) {
            return '['.$host.']';
        }

        if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
            return $host;
        }

        // A colon at this point is a port, not part of an address: ports are not
        // part of the host, so accepting one would never match getHost().
        if (str_contains($host, ':')) {
            throw $this->invalid($value, $source);
        }

        if (! $this->isDnsHostname($host)) {
            throw $this->invalid($value, $source);
        }

        return $host;
    }

    private function isDnsHostname(string $host): bool
    {
        if (strlen($host) > 253) {
            return false;
        }

        // Underscores are accepted because Symfony's Request::isHostValid() accepts
        // them too and container/service names such as prod_d-form-app-core use them.
        return preg_match(
            '/^(?:[a-z0-9_](?:[a-z0-9_-]{0,61}[a-z0-9_])?)(?:\.(?:[a-z0-9_](?:[a-z0-9_-]{0,61}[a-z0-9_])?))*$/',
            $host,
        ) === 1;
    }

    private function invalid(string $value, string $source): RuntimeException
    {
        return new RuntimeException(sprintf(
            '%s contains an invalid host entry: %s. Use an exact host name, IPv4 or IPv6 address (no scheme, path, port or wildcard).',
            $source,
            $value,
        ));
    }
}
