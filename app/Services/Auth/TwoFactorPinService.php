<?php

namespace App\Services\Auth;

use App\Enums\TwoFactorVerifyResult;
use App\Models\User;
use App\Models\UserPinCredential;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

/** Satu-satunya pemilik penyimpanan, lockout, dan status PIN faktor kedua (spec §6.2, §6.5). */
class TwoFactorPinService
{
    /**
     * Percobaan PIN salah sebelum akun terkunci. Ini keputusan produk (spec §2 butir 5),
     * bukan angka waktu, jadi tidak diturunkan dari `config/otp.php`.
     */
    public const MAX_FAILED_ATTEMPTS = 5;

    /** Aktifkan PIN pertama kali; PIN hanya disimpan sebagai hash KDF. */
    public function enable(User $user, #[\SensitiveParameter] string $pin): void
    {
        $this->storePin($user, $pin);
    }

    /**
     *
     * Ganti PIN yang sudah ada. Kewajiban membuktikan PIN lama dipegang controller, sehingga
     * service tetap satu jalur tulis dengan `enable()`.
     *
     */
    public function change(User $user, #[\SensitiveParameter] string $pin): void
    {
        $this->storePin($user, $pin);
    }

    /** Nonaktifkan faktor kedua dengan menghapus kredensial milik user. */
    public function disable(User $user): void
    {
        UserPinCredential::query()->where('user_id', $user->getKey())->delete();
    }

    /** Nilai PIN saat ini dan perbarui penghitung percobaan/lock (spec §6.5). */
    public function verify(User $user, #[\SensitiveParameter] string $pin): TwoFactorVerifyResult
    {
        return DB::transaction(function () use ($user, $pin): TwoFactorVerifyResult {
            $credential = $this->findLocked($user);

            if ($credential === null) {
                return TwoFactorVerifyResult::NotEnabled;
            }

            if ($this->activeLock($credential) !== null) {
                return $this->recordOutcome($user, TwoFactorVerifyResult::Locked);
            }

            if ($this->isLockExpired($credential)) {
                // Lock sudah lewat: jatah percobaan dipulihkan sebelum PIN ini dinilai.
                $credential->forceFill(['failed_attempts' => 0, 'locked_until' => null])->save();
            }

            if (! Hash::check($pin, (string) $credential->pin_hash)) {
                return $this->recordOutcome($user, $this->registerFailedAttempt($credential));
            }

            $credential->forceFill([
                'failed_attempts' => 0,
                'locked_until' => null,
                'last_verified_at' => now(),
            ])->save();

            return TwoFactorVerifyResult::Verified;
        });
    }

    /**
     *
     * Status untuk halaman pengaturan dan halaman tantangan.
     *
     * @return array{enabled: bool, attemptsRemaining: int, lockedUntil: string|null}
     *
     */
    public function status(User $user): array
    {
        $credential = UserPinCredential::query()->where('user_id', $user->getKey())->first();

        if ($credential === null) {
            return [
                'enabled' => false,
                'attemptsRemaining' => self::MAX_FAILED_ATTEMPTS,
                'lockedUntil' => null,
            ];
        }

        $lock = $this->activeLock($credential);

        return [
            'enabled' => true,
            'attemptsRemaining' => $lock === null
                ? max(0, self::MAX_FAILED_ATTEMPTS - $credential->failed_attempts)
                : 0,
            'lockedUntil' => $lock?->toIso8601String(),
        ];
    }

    /** Simpan hash PIN baru dan bersihkan lockout; baris lama diperbarui, bukan ditambah. */
    private function storePin(User $user, string $pin): void
    {
        DB::transaction(function () use ($user, $pin): void {
            $credential = $this->findLocked($user);
            $attributes = [
                'pin_hash' => Hash::make($pin),
                'failed_attempts' => 0,
                'locked_until' => null,
            ];

            if ($credential === null) {
                UserPinCredential::create([
                    ...$attributes,
                    'user_id' => $user->getKey(),
                    'enabled_at' => now(),
                ]);

                return;
            }

            $credential->forceFill($attributes)->save();
        });
    }

    /** Catat satu percobaan salah; percobaan terakhir langsung mengunci akun. */
    private function registerFailedAttempt(UserPinCredential $credential): TwoFactorVerifyResult
    {
        $attempts = $credential->failed_attempts + 1;
        $locked = $attempts >= self::MAX_FAILED_ATTEMPTS;

        $credential->forceFill([
            'failed_attempts' => $attempts,
            'locked_until' => $locked
                ? now()->addMinutes((int) config('otp.lock_minutes'))
                : null,
        ])->save();

        return $locked ? TwoFactorVerifyResult::Locked : TwoFactorVerifyResult::InvalidPin;
    }

    /** Catat hasil verifikasi tanpa pernah menyertakan PIN (spec §6.5). */
    private function recordOutcome(User $user, TwoFactorVerifyResult $result): TwoFactorVerifyResult
    {
        Log::info('two_factor_pin_verify_failed', [
            'user_id' => $user->getKey(),
            'outcome' => $result->name,
        ]);

        return $result;
    }

    /** Kredensial milik user sambil mengunci baris; wajib dipanggil di dalam transaksi penulis. */
    private function findLocked(User $user): ?UserPinCredential
    {
        return UserPinCredential::query()
            ->where('user_id', $user->getKey())
            ->lockForUpdate()
            ->first();
    }

    /** Lock yang masih berlaku, atau null bila tidak ada. */
    private function activeLock(UserPinCredential $credential): ?CarbonInterface
    {
        if ($credential->locked_until === null || ! $credential->locked_until->isFuture()) {
            return null;
        }

        return $credential->locked_until;
    }

    /** Lock sudah lewat sehingga penghitung percobaan perlu dipulihkan. */
    private function isLockExpired(UserPinCredential $credential): bool
    {
        return $credential->locked_until !== null && ! $credential->locked_until->isFuture();
    }
}
