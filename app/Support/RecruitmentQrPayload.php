<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * QR payload contract for OpRec interview attendance.
 */
final class RecruitmentQrPayload
{
    public const VERSION = 1;

    /**
     * @throws \JsonException
     */
    public static function encode(string $applicationId): string
    {
        return json_encode([
            'v' => self::VERSION,
            'application_id' => $applicationId,
        ], JSON_THROW_ON_ERROR);
    }

    public static function tryDecodeApplicationId(string $json): ?string
    {
        $trimmed = trim($json);
        if ($trimmed === '' || ! str_starts_with($trimmed, '{')) {
            return null;
        }

        try {
            /** @var mixed $data */
            $data = json_decode($trimmed, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        if (! is_array($data)) {
            return null;
        }

        $version = $data['v'] ?? null;
        if (! is_int($version) && ! is_string($version)) {
            return null;
        }

        if ((int) $version !== self::VERSION) {
            return null;
        }

        $applicationId = $data['application_id'] ?? null;
        if (! is_string($applicationId) || ! Str::isUuid($applicationId)) {
            return null;
        }

        return $applicationId;
    }
}
