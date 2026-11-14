<?php

namespace App\Support\Security;

use InvalidArgumentException;

/** Shared normalization seam for cached environment and security settings. */
final class SecurityEnvironment
{
    /** Resolve the application environment. */
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

    /** Whether the app runs in production. */
    public static function isProduction(): bool
    {
        return self::environment() === 'production';
    }

    /** Whether the app runs in the local environment (dev allowances enabled). */
    public static function isLocal(): bool
    {
        return self::environment() === 'local';
    }

    /** Interpret recognized environment boolean spellings; blank or unknown values use the default. */
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

    /** Normalize CSP mode and reject unknown values instead of silently disabling protection.
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

    /** Normalize SameSite mode and reject values Symfony would fail to apply.
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
