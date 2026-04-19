<?php

namespace App\Support;

use App\Enums\EventCategory;
use Illuminate\Support\Str;

/**
 * Helpers for event category CSV values: known {@see EventCategory} enums plus optional custom tokens from the DB.
 */
final class EventCategoryTokens
{
    /**
     * Resolve a single segment to an enum backing value, or null if it is not a known enum (including tolerant matching).
     */
    public static function tryResolveEnumBackingValue(string $token): ?string
    {
        $trimmed = trim($token);

        if ($trimmed === '') {
            return null;
        }

        $lower = Str::lower($trimmed);

        $fromLower = EventCategory::tryFrom($lower);

        if ($fromLower !== null) {
            return $fromLower->value;
        }

        $slug = str_replace(' ', '-', preg_replace('/\s+/u', ' ', $lower));
        $fromSlug = EventCategory::tryFrom($slug);

        if ($fromSlug !== null) {
            return $fromSlug->value;
        }

        $normalizedName = strtoupper(str_replace(['-', ' '], '_', $trimmed));

        foreach (EventCategory::cases() as $case) {
            if (strcasecmp($case->name, $normalizedName) === 0) {
                return $case->value;
            }
        }

        return null;
    }

    /**
     * Whether a token may be stored as a custom (non-enum) category segment.
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
