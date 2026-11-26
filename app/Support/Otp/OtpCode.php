<?php

namespace App\Support\Otp;

/** Utilitas kode OTP: pembuatan angka, HMAC-SHA256, dan perbandingan waktu-konstan. */
final class OtpCode
{
    /** Kode angka acak sepanjang `$length`, di-zero-pad agar panjangnya selalu sama. */
    public static function generate(int $length): string
    {
        $digits = max(1, $length);

        return str_pad((string) random_int(0, (10 ** $digits) - 1), $digits, '0', STR_PAD_LEFT);
    }

    /** HMAC-SHA256 kode dengan APP_KEY; satu arah sehingga plaintext tidak bisa dipulihkan dari hash. */
    public static function hash(string $code): string
    {
        return hash_hmac('sha256', $code, (string) config('app.key'));
    }

    /** Bandingkan kode dengan hash tersimpan memakai perbandingan waktu-konstan. */
    public static function matches(string $code, string $hash): bool
    {
        if ($code === '' || $hash === '') {
            return false;
        }

        return hash_equals($hash, self::hash($code));
    }
}
