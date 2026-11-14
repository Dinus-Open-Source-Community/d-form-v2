<?php

namespace App\Providers;

use App\Support\Security\SecurityEnvironment;
use App\Support\Security\TrustedProxyPolicy;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Support\ServiceProvider;

class TrustedProxyServiceProvider extends ServiceProvider
{
    /** Apply the validated proxy list before any request is handled. */
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
