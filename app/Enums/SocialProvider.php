<?php

namespace App\Enums;

/** Provider OAuth yang bisa dipakai untuk masuk dan dihubungkan ke akun yang sudah ada. */
enum SocialProvider: string
{
    case Google = 'google';
    case Github = 'github';

    /** Kolom `users` yang menyimpan provider id. */
    public function userColumn(): string
    {
        return match ($this) {
            self::Google => 'google_id',
            self::Github => 'github_id',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Google => 'Google',
            self::Github => 'GitHub',
        };
    }

    /** Nama route callback yang dipakai bersama alur login dan alur bind. */
    public function callbackRouteName(): string
    {
        return match ($this) {
            self::Google => 'auth.google.callback',
            self::Github => 'auth.github.callback',
        };
    }

    /** Validasi `{provider}` dari URL; nilai lain tidak dikenali. */
    public static function fromRoute(string $provider): ?self
    {
        return self::tryFrom($provider);
    }

    /** Provider hanya bisa dipakai bila client id dan secret terisi di config. */
    public function isConfigured(): bool
    {
        return filled(config('services.'.$this->value.'.client_id'))
            && filled(config('services.'.$this->value.'.client_secret'));
    }
}
