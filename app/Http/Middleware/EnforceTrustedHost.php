<?php

namespace App\Http\Middleware;

use App\Support\Security\SecurityEnvironment;
use App\Support\Security\TrustedHostAllowlist;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Exception\SuspiciousOperationException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Reject requests whose effective host is not on the allowlist.
 *
 * Laravel's trustHosts() middleware is a no-op in local/testing, so this one calls
 * Symfony's Request::setTrustedHosts() directly and therefore always runs. It is
 * appended to the global stack (not prepended) so it runs after TrustProxies and
 * sees the host resolved through trusted X-Forwarded-Host headers.
 */
final class EnforceTrustedHost
{
    private const MAX_LOGGED_VALUE_LENGTH = 255;

    /**
     * Validate the request host before routing, then reset the static allowlist.
     *
     * The eager getHost() call matters: getHost() is lazy, so without it a route
     * that never builds an absolute URL would go unvalidated. The finally block
     * keeps the static state from leaking into the next request (Octane) or test.
     */
    public function handle(Request $request, Closure $next): Response
    {
        try {
            if (! $this->hostIsTrusted($request)) {
                $this->logRejection($request);

                return $this->rejectionResponse($request);
            }

            return $next($request);
        } finally {
            Request::setTrustedHosts([]);
        }
    }

    private function hostIsTrusted(Request $request): bool
    {
        // Loopback hosts are a development convenience only. Staging/QA are not
        // "production" but are still exposed, so they must list their host explicitly.
        $includeLoopbackHosts = SecurityEnvironment::isLocal()
            || SecurityEnvironment::environment() === 'testing';

        $patterns = (new TrustedHostAllowlist($includeLoopbackHosts))->patterns(
            config('trusted_hosts.hosts'),
            (string) config('app.url'),
        );

        Request::setTrustedHosts($patterns);

        try {
            $request->getHost();

            return true;
        } catch (SuspiciousOperationException) {
            return false;
        }
    }

    /**
     * Plain 400 without reflecting the request host; JSON clients get a JSON body.
     */
    private function rejectionResponse(Request $request): Response
    {
        if ($request->expectsJson()) {
            $response = response()->json(['message' => 'Bad Request'], 400);
        } else {
            $response = response('Bad Request', 400)
                ->header('Content-Type', 'text/plain; charset=utf-8');
        }

        // A rejection must not be stored by shared caches under the attacked host.
        $response->headers->set('Cache-Control', 'no-store');

        return $response;
    }

    private function logRejection(Request $request): void
    {
        Log::warning('http_400_untrusted_host', [
            'host' => $this->truncate($request->headers->get('HOST')),
            'x_forwarded_host' => $this->truncate($request->headers->get('X-Forwarded-Host')),
            'ip' => $request->ip(),
            'path' => $this->truncate($request->path()),
        ]);
    }

    private function truncate(?string $value): ?string
    {
        return $value === null ? null : mb_substr($value, 0, self::MAX_LOGGED_VALUE_LENGTH);
    }
}
