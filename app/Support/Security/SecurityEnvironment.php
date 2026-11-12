<?php

namespace App\Support\Security;

use InvalidArgumentException;

/**
 * Single seam for environment and boolean interpretation.
 *
 * Config files and the production policy both use it so that `APP_ENV`,
 * `SESSION_*` and `SECURITY_*` values are interpreted identically everywhere:
 * case and surrounding whitespace are ignored, blank values mean "unset", and
 * only recognized boolean spellings are accepted.
 */
final class SecurityEnvironment
{
    /**
     * Resolve the application environment.
     *
     * Prefers the (cache-safe) configured value so behaviour survives
     * `php artisan config:cache`, where `.env` is no longer loaded at runtime.
     * Falls back to the raw env value while config files are being evaluated.
     */
    public static function environment(): string
    {
        $configured = config('app.env');

        if (is_string($configured) && trim($configured) !== '') {
            return strtolower(trim($configured));
        }

        $fromEnv = strtolower(trim((string) env('APP_ENV', 'production')));

        // A blank APP_ENV means "unset", never "not production". Returning '' would make
        // isProduction() false and silently disable every production-only guard.
        if ($fromEnv === '') {
            return 'production';
        }

        return $fromEnv;
    }

    /**
     * Whether the app runs in production.
     *
     * Normalized so `PRODUCTION`, `Production ` and ` production` cannot silently
     * disable production-only protections.
     */
    public static function isProduction(): bool
    {
        return self::environment() === 'production';
    }

    /**
     * Whether the app runs in the local environment (dev allowances enabled).
     */
    public static function isLocal(): bool
    {
        return self::environment() === 'local';
    }

    /**
     * Interpret an environment value as a boolean.
     *
     * Returns $default for null, booleans as-is, and the default for blank strings
     * (`''`), because an empty `.env` entry means "not configured", not `false`.
     * Values such as `no`, `off`, `1` and `0` are parsed by FILTER_VALIDATE_BOOLEAN;
     * anything unrecognized also falls back to $default.
     */
    public static function flag(mixed $value, bool $default): bool
    {
        if ($value === null || is_bool($value)) {
            return $value ?? $default;
        }

        if (is_string($value) && trim($value) === '') {
            return $default;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $default;
    }

    /**
     * Normalize the Content-Security-Policy mode.
     *
     * Returns exactly one of `enforce`, `report-only` or `off`. Null or blank means
     * "unset" and returns $default. Anything else throws instead of falling back,
     * because a silent fallback on a security switch turns a typo (or a boolean from
     * the Env mapper) into a disabled CSP.
     *
     * @throws InvalidArgumentException when the value is not a supported CSP mode
     */
    public static function cspMode(mixed $value, string $default = 'enforce'): string
    {
        if ($value === null) {
            return $default;
        }

        if (! is_string($value)) {
            throw new InvalidArgumentException(
                'SECURITY_CSP_MODE must be a string: enforce, report-only, or off.',
            );
        }

        $normalized = strtolower(trim($value));

        if ($normalized === '') {
            return $default;
        }

        if (! in_array($normalized, ['enforce', 'report-only', 'off'], true)) {
            throw new InvalidArgumentException(
                'SECURITY_CSP_MODE must be one of: enforce, report-only, off (empty means the default).',
            );
        }

        return $normalized;
    }

    /**
     * Normalize a SameSite cookie policy value.
     *
     * Null or blank means "unset" and returns $default. Accepted values are
     * `lax`, `strict` and `none`; anything else throws instead of reaching
     * Symfony, which would fail on every cookie-setting response.
     *
     * @throws InvalidArgumentException when the value is not a supported SameSite policy
     */
    public static function sameSite(mixed $value, ?string $default = 'lax'): ?string
    {
        if ($value === null) {
            return $default;
        }

        if (! is_string($value)) {
            throw new InvalidArgumentException('SESSION_SAME_SITE must be a string: lax, strict, none, or empty for the default.');
        }

        $normalized = strtolower(trim($value));

        if ($normalized === '') {
            return $default;
        }

        if (! in_array($normalized, ['lax', 'strict', 'none'], true)) {
            throw new InvalidArgumentException(
                'SESSION_SAME_SITE must be one of: lax, strict, none (empty means the default).',
            );
        }

        return $normalized;
    }
}
