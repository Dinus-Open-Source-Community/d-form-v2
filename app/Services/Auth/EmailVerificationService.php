<?php

namespace App\Services\Auth;

use App\Enums\OtpOutcome;
use App\Jobs\SendEmailVerificationOtpJob;
use App\Models\EmailVerificationCode;
use App\Models\User;
use App\Support\Otp\OtpCode;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/** Satu-satunya pemilik aturan waktu dan kuota kode OTP email (spec §6.2). */
class EmailVerificationService
{
    /** Alur standar: kode membuktikan kepemilikan alamat email akun. */
    public const PURPOSE_EMAIL_VERIFICATION = 'email_verification';

    /** Alur pemulihan PIN: kode membuktikan kendali email sebelum PIN baru disetel. */
    public const PURPOSE_TWO_FACTOR_RECOVERY = 'two_factor_recovery';

    /** Kiriman yang hash-nya belum tertulis setelah 15 detik dianggap gagal kirim, jadi resend boleh melewati cooldown. */
    public const DEAD_DELIVERY_RETRY_SECONDS = 15;

    /**
     *
     * Terbitkan kode baru untuk user; hormati lock, kuota kirim, dan cooldown antar-kirim.
     * Pada purpose verifikasi email, user yang emailnya sudah terverifikasi tidak pernah
     * menerima tantangan baru; purpose pemulihan PIN justru dipakai akun terverifikasi.
     *
     */
    public function issue(User $user, string $purpose = self::PURPOSE_EMAIL_VERIFICATION): OtpOutcome
    {
        if ($purpose === self::PURPOSE_EMAIL_VERIFICATION && $user->email_verified_at !== null) {
            return OtpOutcome::Verified;
        }

        return DB::transaction(function () use ($user, $purpose): OtpOutcome {
            $challenge = $this->findLocked($user);

            if ($challenge === null) {
                $challenge = EmailVerificationCode::create([
                    'user_id' => $user->getKey(),
                    'purpose' => $purpose,
                    'code_hash' => null,
                    'expires_at' => now()->addMinutes((int) config('otp.ttl_minutes')),
                    'attempts' => 0,
                    'sends' => 0,
                ]);
            } elseif ($challenge->purpose !== $purpose) {
                $this->restartForPurpose($challenge, $purpose);
            }

            $quotaOutcome = $this->guardSendQuota($challenge, (int) config('otp.resend_cooldown_seconds'));

            if ($quotaOutcome !== null) {
                return $quotaOutcome;
            }

            $this->issueCode($challenge, $user);

            return OtpOutcome::Issued;
        });
    }

    /**
     *
     * Cocokkan kode milik user untuk purpose yang diminta; sukses pada purpose verifikasi
     * email mengisi `email_verified_at`, sukses pada purpose apa pun menghapus tantangan.
     *
     */
    public function verify(User $user, string $code, string $purpose = self::PURPOSE_EMAIL_VERIFICATION): OtpOutcome
    {
        return DB::transaction(function () use ($user, $code, $purpose): OtpOutcome {
            $challenge = $this->findLocked($user);

            if ($challenge === null || $challenge->purpose !== $purpose) {
                return OtpOutcome::Missing;
            }

            if ($this->isCodeExhausted($challenge)) {
                return OtpOutcome::Expired;
            }

            // Kode yang belum pernah terkirim tidak boleh menghabiskan percobaan user.
            if ($challenge->code_hash === null || $challenge->code_hash === '') {
                return OtpOutcome::Missing;
            }

            if (! OtpCode::matches($code, (string) $challenge->code_hash)) {
                $this->registerFailedAttempt($challenge);

                return OtpOutcome::InvalidCode;
            }

            if ($purpose === self::PURPOSE_EMAIL_VERIFICATION) {
                $user->forceFill(['email_verified_at' => now()])->save();
            }

            $challenge->delete();
            Cache::forget(self::codeCacheKey((string) $user->getKey()));

            return OtpOutcome::Verified;
        });
    }

    /**
     *
     * Props halaman verifikasi; bentuknya sama dengan halaman OTP registrasi.
     *
     * @return array{expiresAt: string|null, serverNow: string, attemptsRemaining: int, sendsRemaining: int, lockedUntil: string|null, resendAvailableAt: string|null}
     *
     */
    public function status(User $user, string $purpose = self::PURPOSE_EMAIL_VERIFICATION): array
    {
        $challenge = $this->challengeForStatus($user, $purpose);

        if ($challenge === null) {
            return [
                'expiresAt' => null,
                'serverNow' => now()->toIso8601String(),
                'attemptsRemaining' => 0,
                'sendsRemaining' => (int) config('otp.max_sends'),
                'lockedUntil' => null,
                'resendAvailableAt' => null,
            ];
        }

        $exhausted = $this->isCodeExhausted($challenge);
        $sendsUsed = $this->isLockExpired($challenge) ? 0 : $challenge->sends;

        return [
            'expiresAt' => $exhausted ? null : $challenge->expires_at?->toIso8601String(),
            'serverNow' => now()->toIso8601String(),
            'attemptsRemaining' => $exhausted
                ? 0
                : max(0, (int) config('otp.max_attempts') - $challenge->attempts),
            'sendsRemaining' => max(0, (int) config('otp.max_sends') - $sendsUsed),
            'lockedUntil' => $this->activeLock($challenge)?->toIso8601String(),
            'resendAvailableAt' => $this->resendAvailableAt($challenge)?->toIso8601String(),
        ];
    }

    /**
     *
     * Kode plaintext untuk command lokal/E2E; ditulis job hanya di local/testing,
     * null di produksi sehingga tidak ada salinan terbaca di luar lingkungan uji.
     *
     */
    public function currentCode(User $user): ?string
    {
        if (! app()->environment(['local', 'testing'])) {
            return null;
        }

        $code = Cache::get(self::codeCacheKey((string) $user->getKey()));

        return is_string($code) && $code !== '' ? $code : null;
    }

    /**
     *
     * Kunci cache kode plaintext; dipakai bersama oleh service dan job pengirim.
     *
     */
    public static function codeCacheKey(string $userId): string
    {
        return 'email-verification-code:'.$userId;
    }

    /** Tantangan milik user sambil mengunci baris; wajib dipanggil di dalam transaksi penulis. */
    private function findLocked(User $user): ?EmailVerificationCode
    {
        return EmailVerificationCode::query()
            ->where('user_id', $user->getKey())
            ->lockForUpdate()
            ->first();
    }

    /**
     *
     * Mulai ulang tantangan untuk purpose lain: kuota kirim, percobaan, dan lock milik alur
     * sebelumnya tidak boleh menghukum alur yang baru diminta pengguna.
     *
     */
    private function restartForPurpose(EmailVerificationCode $challenge, string $purpose): void
    {
        $challenge->forceFill([
            'purpose' => $purpose,
            'code_hash' => null,
            'expires_at' => now()->addMinutes((int) config('otp.ttl_minutes')),
            'attempts' => 0,
            'sends' => 0,
            'last_sent_at' => null,
            'locked_until' => null,
        ])->save();

        Cache::forget(self::codeCacheKey((string) $challenge->user_id));
    }

    /** Tantangan yang relevan untuk status satu purpose; akun terverifikasi tidak punya status verifikasi email. */
    private function challengeForStatus(User $user, string $purpose): ?EmailVerificationCode
    {
        if ($purpose === self::PURPOSE_EMAIL_VERIFICATION && $user->email_verified_at !== null) {
            return null;
        }

        return EmailVerificationCode::query()
            ->where('user_id', $user->getKey())
            ->where('purpose', $purpose)
            ->first();
    }

    /** Tolak kirim saat lock aktif, kuota habis, atau cooldown belum lewat. */
    private function guardSendQuota(EmailVerificationCode $challenge, int $cooldownSeconds): ?OtpOutcome
    {
        if ($this->activeLock($challenge) !== null) {
            return OtpOutcome::Locked;
        }

        $this->recoverExpiredQuota($challenge);

        if ($challenge->sends >= (int) config('otp.max_sends')) {
            return OtpOutcome::Locked;
        }

        if ($cooldownSeconds > 0
            && $this->resendAvailableAt($challenge) !== null
            && ! $this->isDeliveryStalled($challenge)) {
            return OtpOutcome::Locked;
        }

        return null;
    }

    /** Kiriman macet: hash belum tertulis dan `last_sent_at` sudah lewat toleransi pengiriman. */
    private function isDeliveryStalled(EmailVerificationCode $challenge): bool
    {
        if ($challenge->code_hash !== null && $challenge->code_hash !== '') {
            return false;
        }

        if ($challenge->last_sent_at === null) {
            return false;
        }

        $retryDeadline = $challenge->last_sent_at
            ->copy()
            ->addSeconds(self::DEAD_DELIVERY_RETRY_SECONDS);

        return ! $retryDeadline->isFuture();
    }

    /** Siapkan kiriman baru: reset percobaan, naikkan kuota kirim, kunci bila kuota habis, lalu antrekan email. */
    private function issueCode(EmailVerificationCode $challenge, User $user): void
    {
        $now = now();
        $sends = $challenge->sends + 1;

        // Hash lama dicabut lebih dulu: kode baru baru diketahui job, bukan service.
        $challenge->forceFill([
            'code_hash' => null,
            'expires_at' => $now->copy()->addMinutes((int) config('otp.ttl_minutes')),
            'attempts' => 0,
            'sends' => $sends,
            'last_sent_at' => $now,
            'locked_until' => $sends >= (int) config('otp.max_sends')
                ? $now->copy()->addMinutes((int) config('otp.lock_minutes'))
                : null,
        ])->save();

        Cache::forget(self::codeCacheKey((string) $user->getKey()));

        SendEmailVerificationOtpJob::dispatch((string) $user->getKey(), $now->toIso8601String())->afterCommit();
    }

    /** Catat percobaan salah; percobaan terakhir menghanguskan kode. */
    private function registerFailedAttempt(EmailVerificationCode $challenge): void
    {
        $attempts = $challenge->attempts + 1;
        $exhausted = $attempts >= (int) config('otp.max_attempts');

        $challenge->forceFill([
            'attempts' => $attempts,
            'code_hash' => $exhausted ? null : $challenge->code_hash,
        ])->save();

        if ($exhausted) {
            // Kode hangus tidak boleh lagi ditawarkan command lokal.
            Cache::forget(self::codeCacheKey((string) $challenge->user_id));
        }
    }

    /**
     * Kode dianggap hangus bila percobaan sudah habis atau masa berlaku lewat.
     * Hash yang belum tertulis bukan alasan: pengiriman job bisa tertunda, dan
     * halaman tetap harus menampilkan hitung mundur sejak tantangan diterbitkan.
     */
    private function isCodeExhausted(EmailVerificationCode $challenge): bool
    {
        if ($challenge->attempts >= (int) config('otp.max_attempts')) {
            return true;
        }

        return $challenge->expires_at === null || $challenge->expires_at->isPast();
    }

    /** Lock kirim yang masih berlaku, atau null bila tidak ada. */
    private function activeLock(EmailVerificationCode $challenge): ?CarbonInterface
    {
        if ($challenge->locked_until === null || ! $challenge->locked_until->isFuture()) {
            return null;
        }

        return $challenge->locked_until;
    }

    /** Lock sudah lewat sehingga jatah kirim perlu dipulihkan. */
    private function isLockExpired(EmailVerificationCode $challenge): bool
    {
        return $challenge->locked_until !== null && ! $challenge->locked_until->isFuture();
    }

    /** Pulihkan jatah kirim penuh setelah lock lewat. */
    private function recoverExpiredQuota(EmailVerificationCode $challenge): void
    {
        if (! $this->isLockExpired($challenge)) {
            return;
        }

        $challenge->forceFill(['sends' => 0, 'locked_until' => null])->save();
    }

    /** Waktu paling awal kirim berikutnya diperbolehkan, atau null bila sudah boleh. */
    private function resendAvailableAt(EmailVerificationCode $challenge): ?CarbonInterface
    {
        $lock = $this->activeLock($challenge);

        if ($lock !== null) {
            return $lock;
        }

        if ($challenge->last_sent_at === null) {
            return null;
        }

        $cooldownEnds = $challenge->last_sent_at
            ->copy()
            ->addSeconds((int) config('otp.resend_cooldown_seconds'));

        return $cooldownEnds->isFuture() ? $cooldownEnds : null;
    }
}
