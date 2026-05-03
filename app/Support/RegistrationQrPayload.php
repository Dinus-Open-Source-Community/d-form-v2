<?php

namespace App\Support;

/**
 * QR payload contract for attendance (M6). Scanner reads JSON with version + submission id.
 */
final class RegistrationQrPayload
{
    public const VERSION = 1;

    /**
     * @throws \JsonException
     */
    public static function encode(string $formAnswerId): string
    {
        return json_encode([
            'v' => self::VERSION,
            'submission_id' => $formAnswerId,
        ], JSON_THROW_ON_ERROR);
    }
}
