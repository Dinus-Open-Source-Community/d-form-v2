<?php

namespace App\Services\Form;

/** Format kode referral `PREFIX-BODY`: huruf/angka kapital, separator tetap satu hyphen. */
final class ReferralCodeNormalizer
{
    /** Alfabet tanpa karakter ambigu (0/O, 1/I/L) agar kode bisa dibacakan ulang. */
    private const BODY_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    public static function normalize(?string $raw): ?string
    {
        if ($raw === null) {
            return null;
        }

        $code = strtoupper(trim($raw));

        if ($code === '') {
            return null;
        }

        return preg_match(self::pattern(), $code) === 1 ? $code : null;
    }

    /** @return array{prefix: string, body: string} */
    public static function split(string $code): array
    {
        [$prefix, $body] = explode('-', strtoupper($code), 2);

        return ['prefix' => $prefix, 'body' => $body];
    }

    public static function codeFor(string $prefix, string $body): string
    {
        return strtoupper($prefix) . '-' . strtoupper($body);
    }

    /** Body acak dari CSPRNG; panjang dan alfabet dikunci config. */
    public static function generateBody(): string
    {
        $length = (int) config('events.forms.referral.generated_body_length', 8);
        $maxIndex = strlen(self::BODY_ALPHABET) - 1;
        $body = '';

        for ($i = 0; $i < $length; $i++) {
            $body .= self::BODY_ALPHABET[random_int(0, $maxIndex)];
        }

        return $body;
    }

    private static function pattern(): string
    {
        $prefixMin = (int) config('events.forms.referral.prefix_min_length', 2);
        $prefixMax = (int) config('events.forms.referral.prefix_max_length', 12);
        $bodyMin = (int) config('events.forms.referral.body_min_length', 4);
        $bodyMax = (int) config('events.forms.referral.body_max_length', 16);

        return sprintf(
            '/^[A-Z0-9]{%d,%d}-[A-Z0-9]{%d,%d}$/',
            $prefixMin,
            $prefixMax,
            $bodyMin,
            $bodyMax,
        );
    }
}
