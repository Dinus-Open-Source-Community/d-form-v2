<?php

namespace App\Http\Middleware;

use App\Support\Security\SecurityEnvironment;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    /**
     * Attach baseline security headers, HSTS and CSP to responses leaving the global stack.
     *
     * Prepended to the global middleware, so its response phase runs after every
     * other global middleware (including ones that short-circuit), and responses
     * produced outside the web group still receive the headers. HSTS is emitted
     * whenever it is enabled, independent of the request scheme: browsers ignore
     * HSTS received over plain HTTP, so gating on Request::isSecure() would only
     * drop the header on misconfigured deployments. Headers already set by a
     * controller keep their value.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $this->applyBaselineHeaders($response);

        if (SecurityEnvironment::flag(config('security.hsts.enabled'), false)) {
            $this->setUnlessPresent($response, 'Strict-Transport-Security', $this->hstsValue());
        }

        $this->applyCsp($response);

        return $response;
    }

    private function applyBaselineHeaders(Response $response): void
    {
        $headers = config('security.headers', []);

        if (! is_array($headers)) {
            return;
        }

        $mapping = [
            'x_content_type_options' => 'X-Content-Type-Options',
            'x_frame_options' => 'X-Frame-Options',
            'referrer_policy' => 'Referrer-Policy',
            'permissions_policy' => 'Permissions-Policy',
        ];

        foreach ($mapping as $key => $header) {
            $value = $headers[$key] ?? null;

            if (is_string($value) && $value !== '') {
                $this->setUnlessPresent($response, $header, $value);
            }
        }
    }

    private function applyCsp(Response $response): void
    {
        $mode = config('security.csp.mode', 'enforce');

        // Fail closed: `off` is the only value that skips the policy. An unknown or
        // non-string mode still emits the enforcing header instead of dropping CSP.
        if ($mode === 'off') {
            return;
        }

        $header = $mode === 'report-only'
            ? 'Content-Security-Policy-Report-Only'
            : 'Content-Security-Policy';

        $this->setUnlessPresent($response, $header, $this->cspValue());
    }

    private function hstsValue(): string
    {
        $maxAge = (int) config('security.hsts.max_age', 31536000);

        // max-age=0 would tell browsers to forget HSTS; fall back instead.
        if ($maxAge <= 0) {
            $maxAge = 31536000;
        }

        $value = 'max-age='.$maxAge;

        if (SecurityEnvironment::flag(config('security.hsts.include_subdomains'), false)) {
            $value .= '; includeSubDomains';
        }

        return $value;
    }

    private function cspValue(): string
    {
        $configured = config('security.csp.directives', []);
        $directives = [];

        if (is_array($configured)) {
            foreach ($configured as $name => $sources) {
                if (! is_string($name) || ! is_array($sources)) {
                    continue;
                }

                $directives[$name] = array_values(array_filter($sources, 'is_string'));
            }
        }

        if (SecurityEnvironment::flag(config('security.csp.dev_allowances'), false)) {
            $directives = $this->withDevAllowances($directives);
        }

        if (SecurityEnvironment::isProduction()) {
            $directives['upgrade-insecure-requests'] = [];
        }

        $parts = [];

        foreach ($directives as $name => $sources) {
            $parts[] = $sources === [] ? $name : $name.' '.implode(' ', $sources);
        }

        $reportUri = config('security.csp.report_uri');

        if (is_string($reportUri) && $reportUri !== '') {
            $parts[] = 'report-uri '.$reportUri;
        }

        return implode('; ', $parts);
    }

    /**
     * Allow the Vite dev server (HTTP + HMR websocket) and inline dev tooling scripts.
     *
     * @param  array<string, array<int, string>>  $directives
     * @return array<string, array<int, string>>
     */
    private function withDevAllowances(array $directives): array
    {
        $origins = $this->devServerOrigins();

        if ($origins === []) {
            return $directives;
        }

        foreach (['script-src', 'style-src', 'connect-src'] as $name) {
            $directives[$name] = $this->appendSources($directives[$name] ?? [], $origins);
        }

        $directives['script-src'] = $this->appendSources($directives['script-src'], ["'unsafe-inline'"]);

        return $directives;
    }

    /**
     * @return array<int, string>
     */
    private function devServerOrigins(): array
    {
        $origin = config('security.csp.dev_server_origin');

        if (! is_string($origin) || $origin === '') {
            return [];
        }

        $parts = parse_url($origin);

        if ($parts === false || ! isset($parts['host'])) {
            return [$origin];
        }

        $authority = $parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '');

        return ['http://'.$authority, 'ws://'.$authority];
    }

    /**
     * @param  array<int, string>  $sources
     * @param  array<int, string>  $extra
     * @return array<int, string>
     */
    private function appendSources(array $sources, array $extra): array
    {
        return array_values(array_unique([...$sources, ...$extra]));
    }

    private function setUnlessPresent(Response $response, string $header, string $value): void
    {
        if (! $response->headers->has($header)) {
            $response->headers->set($header, $value);
        }
    }
}
