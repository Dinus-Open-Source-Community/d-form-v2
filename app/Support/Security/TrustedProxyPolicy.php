<?php

namespace App\Support\Security;

use RuntimeException;

/** Resolve and validate the trusted proxy list used by Laravel's TrustProxies middleware. */
final class TrustedProxyPolicy
{
    public function __construct(private readonly bool $isProduction)
    {
    }

    /**
     *
     * Build the trusted proxy list and header mask for the middleware.
     *
     * @param  array<int, mixed>|string|bool|null  $configuredProxies  `TRUSTED_PROXIES` value
     * @param  int  $configuredHeaders  header bitmask; must be positive
     * @return array{proxies: list<string>, headers: int}
     *
     * @throws RuntimeException when the value is boolean, an entry is not a valid IP/CIDR,
     * a wildcard or /0 prefix is used in production, the header mask
     * is not positive, or the fallback list is missing/empty
     *
     */
    public function resolve(array|string|bool|null $configuredProxies, int $configuredHeaders): array
    {
        // env('TRUSTED_PROXIES=false') yields bool false; coercing it to '' would silently
        // select the default list, so reject the type instead of degrading.
        if (is_bool($configuredProxies)) {
            throw new RuntimeException(
                'TRUSTED_PROXIES must be a comma-separated string or an array; boolean values are not accepted.',
            );
        }

        $proxies = $this->normalize($configuredProxies);

        if ($proxies === []) {
            $proxies = $this->defaultProxies();
        }

        $resolved = [];

        foreach ($proxies as $proxy) {
            $this->assertValid($proxy);

            if (! in_array($proxy, $resolved, true)) {
                $resolved[] = $proxy;
            }
        }

        // A missing/zero mask makes TrustProxies::withHeaders(0) fall back to the framework
        // default, which also trusts X-Forwarded-Prefix. Never degrade silently.
        if ($configuredHeaders <= 0) {
            throw new RuntimeException(
                'TRUSTED_PROXIES header mask (trusted_proxies.headers) must be a positive bitmask; refusing to fall back to the permissive framework default.',
            );
        }

        return [
            'proxies' => $resolved,
            'headers' => $configuredHeaders,
        ];
    }

    /**
     *
     * @return list<string>
     *
     */
    private function normalize(array|string|null $configuredProxies): array
    {
        $raw = is_string($configuredProxies) ? explode(',', $configuredProxies) : ($configuredProxies ?? []);

        $entries = [];

        foreach ($raw as $entry) {
            if (! is_string($entry)) {
                throw $this->invalid($entry);
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
     * @return list<string>
     *
     * @throws RuntimeException when the fallback list is missing or empty
     *
     */
    private function defaultProxies(): array
    {
        $defaults = config('trusted_proxies.default_proxies');

        if (! is_array($defaults)) {
            throw new RuntimeException(
                'TRUSTED_PROXIES is empty and trusted_proxies.default_proxies is not a list; refusing to trust no proxies silently.',
            );
        }

        $proxies = array_values(array_filter(
            $defaults,
            static fn (mixed $entry): bool => is_string($entry) && trim($entry) !== '',
        ));

        if ($proxies === []) {
            throw new RuntimeException(
                'TRUSTED_PROXIES is empty and trusted_proxies.default_proxies is empty; refusing to trust no proxies silently.',
            );
        }

        return $proxies;
    }

    private function assertValid(string $proxy): void
    {
        if ($proxy === '*' || $proxy === '**') {
            if ($this->isProduction) {
                throw new RuntimeException(
                    'TRUSTED_PROXIES must not use a wildcard in production; list the ingress IPs or CIDR ranges instead.',
                );
            }

            return;
        }

        // TrustProxies replaces this token with the peer address, i.e. it trusts whoever connects directly.
        if ($proxy === 'REMOTE_ADDR') {
            return;
        }

        if (filter_var($proxy, FILTER_VALIDATE_IP) !== false) {
            return;
        }

        // Symfony's IpUtils accepts "address/prefix"; validate the prefix per family because
        // an out-of-range prefix makes checkIp() throw deep inside every request.
        $parts = explode('/', $proxy);

        if (count($parts) !== 2
            || filter_var($parts[0], FILTER_VALIDATE_IP) === false
            || ! ctype_digit($parts[1])
        ) {
            throw $this->invalid($proxy);
        }

        $maxPrefix = filter_var($parts[0], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false ? 32 : 128;
        $prefix = (int) $parts[1];

        if ($prefix > $maxPrefix) {
            throw $this->invalid($proxy);
        }

        // Prefix 0 is the same as trusting every address, so it is production-equivalent to "*".
        if ($prefix === 0 && $this->isProduction) {
            throw new RuntimeException(
                'TRUSTED_PROXIES must not use a /0 prefix in production; list the ingress IPs or CIDR ranges instead.',
            );
        }
    }

    private function invalid(mixed $entry): RuntimeException
    {
        return new RuntimeException(sprintf(
            'TRUSTED_PROXIES contains an invalid entry: %s. Use an IP, CIDR range or REMOTE_ADDR.',
            is_string($entry) ? $entry : get_debug_type($entry),
        ));
    }
}
