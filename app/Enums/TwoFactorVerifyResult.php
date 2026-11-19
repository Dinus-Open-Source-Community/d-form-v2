<?php

namespace App\Enums;

/** Hasil verifikasi PIN faktor kedua; dipetakan controller ke respons halaman. */
enum TwoFactorVerifyResult
{
    case Verified;
    case InvalidPin;
    case Locked;
    case NotEnabled;

    /** Pesan siap tampil untuk pemakai; satu sumber copy agar semua endpoint berbunyi sama. */
    public function message(): string
    {
        return match ($this) {
            self::Verified => 'PIN benar.',
            self::InvalidPin => 'PIN yang Anda masukkan salah.',
            self::Locked => 'PIN terkunci sementara karena terlalu banyak percobaan. Coba lagi nanti.',
            self::NotEnabled => 'Akun ini tidak memakai PIN.',
        };
    }
}
