<?php

namespace App\Services\Registration;

/**
 * Generates a human-friendly registration code when an answer is accepted.
 * Crockford-style alphabet: excludes 0, O, 1, I.
 */
final class RegistrationCodeIssuer
{
    private const ALPHABET = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';

    private const SEGMENT_LENGTH = 4;

    public function generate(): string
    {
        return $this->randomSegment(self::SEGMENT_LENGTH).'-'.$this->randomSegment(self::SEGMENT_LENGTH);
    }

    private function randomSegment(int $length): string
    {
        $max = strlen(self::ALPHABET) - 1;
        $chars = [];
        for ($i = 0; $i < $length; $i++) {
            $chars[] = self::ALPHABET[random_int(0, $max)];
        }

        return implode('', $chars);
    }
}
