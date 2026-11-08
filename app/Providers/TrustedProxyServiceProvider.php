<?php

namespace App\Providers;

use App\Support\Security\SecurityEnvironment;
use App\Support\Security\TrustedProxyPolicy;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Support\ServiceProvider;

class TrustedProxyServiceProvider extends ServiceProvider
{
    /**
     * Apply the validated proxy list before any request is handled.
     *
     * Provider boot runs for HTTP, Octane and every artisan command, so a bad
     * configuration fails the deploy instead of depending on HttpKernel being
     * resolved by a dev-only package. `Middleware::trustProxies()` only forwards
     * these two static setters (vendor/.../Configuration/Middleware.php), so the
     * behaviour is identical while staying reachable from CLI.
     */
    public function boot(): void
    {
        ['proxies' => $proxies, 'headers' => $headers] = (new TrustedProxyPolicy(
            SecurityEnvironment::isProduction(),
        ))->resolve(
            config('trusted_proxies.proxies'),
            (int) config('trusted_proxies.headers', 0),
        );

        TrustProxies::at($proxies);
        TrustProxies::withHeaders($headers);
    }
}
