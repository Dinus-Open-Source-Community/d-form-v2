<?php

namespace App\Http\Requests\Concerns;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

/** Aturan bersama permintaan PIN faktor kedua: bentuk PIN dan bukti kepemilikan akun. */
trait ConfirmsTwoFactorCredentials
{
    /** Panjang PIN mengikuti panjang kode OTP yang sudah dikonfigurasi. */
    private function pinLength(): int
    {
        return max(1, (int) config('otp.length'));
    }

    /** Apakah akun punya kata sandi lokal (bukan akun OAuth-only). */
    protected function hasLocalPassword(): bool
    {
        $user = $this->user();

        if (! $user instanceof User) {
            return false;
        }

        return (bool) ($user->has_local_password ?? filled($user->getRawOriginal('password')));
    }

    /**
     *
     * Re-auth kata sandi: hash dibaca khusus untuk aksi ini karena `password` tidak ikut
     * di query sesi (`User::AUTH_SESSION_COLUMNS`).
     *
     * @return array<int, mixed>
     *
     */
    protected function currentPasswordRules(): array
    {
        return [
            'required',
            'string',
            function (string $attribute, mixed $value, \Closure $fail): void {
                $user = $this->user();
                $hash = $user instanceof User
                    ? $user->newQuery()->whereKey($user->getKey())->value('password')
                    : null;

                if (! is_string($hash) || ! is_string($value) || ! Hash::check($value, $hash)) {
                    $fail(__('validation.current_password'));
                }
            },
        ];
    }

    /** @return array<int, string> */
    protected function pinRules(): array
    {
        return ['required', 'string', 'digits:'.$this->pinLength()];
    }

    /** @return array<string, string> */
    protected function pinMessages(): array
    {
        return [
            'pin.required' => 'Masukkan PIN Anda.',
            'pin.string' => 'PIN harus berupa angka.',
            'pin.digits' => 'PIN harus terdiri dari '.$this->pinLength().' angka.',
        ];
    }
}
