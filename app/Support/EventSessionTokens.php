<?php

namespace App\Support;

use App\Enums\EventSession;
use Illuminate\Support\Str;

/**
 * Helpers for event session values: known {@see EventSession} enums plus optional custom tokens from the DB.
 */
final class EventSessionTokens
{
    /**
     * Resolve a single value to an enum backing value, or null if it is not a known enum (tolerant matching).
     */
    public static function tryResolveEnumBackingValue(string $token): ?string
    {
        $trimmed = trim($token);

        if ($trimmed === '') {
            return null;
        }

        $lower = Str::lower($trimmed);

        $fromLower = EventSession::tryFrom($lower);

        if ($fromLower !== null) {
            return $fromLower->value;
        }

        $snake = Str::snake(preg_replace('/\s+/u', ' ', $lower));
        $fromSnake = EventSession::tryFrom($snake);

        if ($fromSnake !== null) {
            return $fromSnake->value;
        }

        $normalizedName = Str::of($trimmed)->replaceMatches('/[\s\-]+/u', '_')->upper()->toString();

        foreach (EventSession::cases() as $case) {
            if (strcasecmp($case->name, $normalizedName) === 0) {
                return $case->value;
            }
        }

        return null;
    }

    /**
     * Whether a value may be stored as a custom (non-enum) session.
     * Letters, numbers, spaces, hyphens and underscores only; 1–64 chars after trim.
     */
    public static function isValidCustomToken(string $token): bool
    {
        $t = trim($token);

        if ($t === '' || mb_strlen($t) > 64) {
            return false;
        }

        if (self::tryResolveEnumBackingValue($t) !== null) {
            return false;
        }

        return (bool) preg_match('/^[\p{L}\p{N}]+(?:[\p{L}\p{N} _-]*[\p{L}\p{N}]+)?$/u', $t);
    }

    /**
     * Canonical value stored for a custom token (lowercase trim).
     */
    public static function normalizeCustomValue(string $token): string
    {
        return Str::lower(trim($token));
    }
}
