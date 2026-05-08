<?php

namespace App\Support;

use Illuminate\Support\Str;

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

    /**
     * Decode M5/M6 QR JSON and return the submission (`form_answers.id`) UUID, or null if invalid.
     */
    public static function tryDecodeSubmissionId(string $json): ?string
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

        $submissionId = $data['submission_id'] ?? null;
        if (! is_string($submissionId) || ! Str::isUuid($submissionId)) {
            return null;
        }

        return $submissionId;
    }
}
