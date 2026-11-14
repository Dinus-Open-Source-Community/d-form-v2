<?php

namespace App\Support\Security;

/** Immutable session cookie attributes evaluated by ProductionSecurityPolicy. */
final class SessionCookieSettings
{
    public function __construct(
        public readonly bool $isProduction,
        public readonly bool $secure,
        public readonly bool $httpOnly,
        public readonly ?string $sameSite,
    ) {
    }
}
