<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Satu pintu key sesi pendaftaran tertunda, dipakai penulis (register) dan pembaca (OTP).
 */
final class PendingRegistrationSession
{
    private const EMAIL_KEY = 'otp.pending_email';

    private const CANCEL_TOKEN_KEY = 'otp.cancel_token';

    /** Simpan email pending + token pembatalan baru ke sesi. */
    public static function remember(Request $request, string $email): void
    {
        $request->session()->put([
            self::EMAIL_KEY => $email,
            self::CANCEL_TOKEN_KEY => Str::random(32),
        ]);
    }

    /** Email pending yang tercatat di sesi; null bila tidak ada. */
    public static function email(Request $request): ?string
    {
        $email = $request->session()->get(self::EMAIL_KEY);

        return is_string($email) && $email !== '' ? $email : null;
    }

    /** Token pembatalan yang tercatat di sesi; null bila tidak ada. */
    public static function cancelToken(Request $request): ?string
    {
        $token = $request->session()->get(self::CANCEL_TOKEN_KEY);

        return is_string($token) && $token !== '' ? $token : null;
    }

    /** Hapus penanda pendaftaran tertunda dari sesi. */
    public static function forget(Request $request): void
    {
        $request->session()->forget([self::EMAIL_KEY, self::CANCEL_TOKEN_KEY]);
    }
}
