<?php

namespace App\Support\Security;

use RuntimeException;

/**
 * Fail-fast checks for security settings that must never be weakened in production.
 *
 * Invoked from AppServiceProvider::boot() so a misconfigured deployment crashes at
 * startup instead of silently serving cookies over plain HTTP or without CSP.
 * Messages name the env variable to change and never include secret values.
 */
final class ProductionSecurityPolicy
{
    /**
     * Require Secure, HttpOnly and a SameSite policy other than `none` in production.
     *
     * @throws RuntimeException when any production cookie invariant is violated
     */
    public function assertProductionSafe(SessionCookieSettings $settings): void
    {
        if (! $settings->isProduction) {
            return;
        }

        if (! $settings->secure) {
            throw new RuntimeException(
                'Session cookie is not HTTPS-only in production. Remove SESSION_SECURE_COOKIE=false (the production default is true) or set it to true, and serve the app over HTTPS.',
            );
        }

        if (! $settings->httpOnly) {
            throw new RuntimeException(
                'Session cookie is readable by JavaScript in production. Remove SESSION_HTTP_ONLY=false and keep the default true.',
            );
        }

        if ($settings->sameSite !== null && strtolower($settings->sameSite) === 'none') {
            throw new RuntimeException(
                'SESSION_SAME_SITE=none requires Secure and a cross-site flow this app does not have. Use SESSION_SAME_SITE=lax.',
            );
        }
    }

    /**
     * Refuse to disable CSP in production unless the operator opts in explicitly.
     *
     * $mode is expected to be already normalized by
     * {@see SecurityEnvironment::cspMode()}; no trimming happens here so an
     * unnormalized value cannot accidentally compare equal to `off`.
     *
     * @throws RuntimeException when CSP is off in production without SECURITY_CSP_ALLOW_OFF
     */
    public function assertCspModeSafe(string $mode, bool $allowOff): void
    {
        if (! SecurityEnvironment::isProduction() || $mode !== 'off') {
            return;
        }

        if ($allowOff) {
            return;
        }

        throw new RuntimeException(
            'CSP is disabled in production. Set SECURITY_CSP_MODE=enforce (or report-only); if this is genuinely intended, set SECURITY_CSP_ALLOW_OFF=true.',
        );
    }

    /**
     * Require the CSP report collector to be first party in production.
     *
     * Reports contain full document URLs, including password-reset tokens carried in
     * the path, so a foreign collector would leak them. Relative paths and absolute
     * URLs on the app host are accepted; no scheme requirement is imposed.
     *
     * @throws RuntimeException when the report URI host differs from APP_URL's host
     */
    public function assertCspReportUriFirstParty(?string $reportUri): void
    {
        if (! SecurityEnvironment::isProduction()) {
            return;
        }

        $reportUri = trim((string) $reportUri);

        if ($reportUri === '') {
            return;
        }

        $host = parse_url($reportUri, PHP_URL_HOST);

        // A relative path has no host and is served by the app itself.
        if ($host === null || $host === false) {
            return;
        }

        if (strcasecmp((string) $host, (string) parse_url((string) config('app.url'), PHP_URL_HOST)) !== 0) {
            throw new RuntimeException(
                'SECURITY_CSP_REPORT_URI must point at this application: CSP reports contain full document URLs, including password-reset tokens. Use a relative path or a URL on the APP_URL host.',
            );
        }
    }
}
