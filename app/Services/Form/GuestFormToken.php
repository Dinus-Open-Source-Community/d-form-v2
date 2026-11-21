<?php

namespace App\Services\Form;

use Symfony\Component\HttpFoundation\Cookie;

/** Issues and represents the browser token used for guest form submissions. */
final class GuestFormToken
{
    public const COOKIE_NAME = 'dform_guest_token';

    /** Return a cryptographically secure raw browser token. */
    public static function issue(): string
    {
        return bin2hex(random_bytes(32));
    }

    public static function isValid(string $token): bool
    {
        return preg_match('/^[a-f0-9]{64}$/', $token) === 1;
    }

    /** Return the lowercase SHA-256 digest persisted for a browser token. */
    public static function hash(string $rawToken): string
    {
        return hash('sha256', $rawToken);
    }

    /** Build the HttpOnly, same-site browser token cookie. */
    public static function cookie(string $rawToken): Cookie
    {
        return new Cookie(
            self::COOKIE_NAME,
            $rawToken,
            now()->addMinutes((int) config('guest.cookie_lifetime', 525600)),
            '/',
            config('session.domain'),
            (bool) config('session.secure', false),
            true,
            false,
            'lax',
        );
    }
}
