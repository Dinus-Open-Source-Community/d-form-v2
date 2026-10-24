<?php

namespace App\Services\Auth;

use App\Enums\OtpOutcome;
use App\Jobs\SendRegistrationOtpJob;
use App\Models\PendingRegistration;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Satu-satunya pemilik aturan waktu dan kuota OTP registrasi (spec §6/§8).
 *
 * Semua keputusan waktu memakai jam server; front-end hanya menampilkan hasil `status()`.
 */
class RegistrationOtpService
{
    /**
     * Mulai atau lanjutkan pendaftaran tertunda: buat pending baru atau terbitkan OTP baru.
     *
     * Resume tidak menimpa `name`/`password`; kata sandi harus cocok agar OTP baru dikirim.
     *
     * @param  array{name: string, email: string, password: string}  $data
     */
    public function start(array $data): OtpOutcome
    {
        $pending = PendingRegistration::query()->firstOrNew(['email' => $data['email']]);

        if (! $pending->exists) {
            $pending->fill([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
            ])->save();

            $this->issue($pending);

            return OtpOutcome::Issued;
        }

        if (! Hash::check($data['password'], $pending->password)) {
            return OtpOutcome::PasswordMismatch;
        }

        $quotaOutcome = $this->guardSendQuota($pending, 0);

        if ($quotaOutcome !== null) {
            return $quotaOutcome;
        }

        $this->issue($pending);

        return OtpOutcome::Issued;
    }

    /**
     * Verifikasi kode OTP; pada sukses akun dibuat terverifikasi tanpa login (controller yang login).
     *
     * Kunci kirim (`otp_locked_until`) sengaja tidak memblokir verifikasi: kode dari kiriman
     * ketiga tetap sah sampai masa berlakunya habis. Baris pending dikunci agar counter
     * percobaan tidak balapan antar-request.
     */
    public function verify(string $email, string $code): OtpOutcome
    {
        return DB::transaction(function () use ($email, $code): OtpOutcome {
            $pending = PendingRegistration::query()
                ->where('email', $email)
                ->lockForUpdate()
                ->first();

            if ($pending === null) {
                return OtpOutcome::Missing;
            }

            if ($this->isCodeExhausted($pending)) {
                return OtpOutcome::Expired;
            }

            if (! hash_equals((string) $pending->otp_code, $code)) {
                $this->registerFailedAttempt($pending);

                return OtpOutcome::InvalidCode;
            }

            $this->createVerifiedUser($pending);

            return OtpOutcome::Verified;
        });
    }

    /**
     * Kirim ulang OTP; hormati lock, kuota kirim, dan cooldown antar-kirim.
     */
    public function resend(string $email): OtpOutcome
    {
        $pending = $this->find($email);

        if ($pending === null) {
            return OtpOutcome::Missing;
        }

        $quotaOutcome = $this->guardSendQuota($pending, (int) config('otp.resend_cooldown_seconds'));

        if ($quotaOutcome !== null) {
            return $quotaOutcome;
        }

        $this->issue($pending);

        return OtpOutcome::Issued;
    }

    /**
     * Props halaman verifikasi OTP (spec §7); seluruh waktu ISO8601 dari jam server.
     *
     * @return array{email: string, expiresAt: string|null, serverNow: string, lockedUntil: string|null, sendsRemaining: int, attemptsRemaining: int, resendAvailableAt: string|null}
     */
    public function status(PendingRegistration $pending): array
    {
        $sendsUsed = $this->isLockExpired($pending) ? 0 : $pending->otp_sends;

        return [
            'email' => $pending->email,
            'expiresAt' => $pending->otp_expires_at?->toIso8601String(),
            'serverNow' => now()->toIso8601String(),
            'lockedUntil' => $this->activeLock($pending)?->toIso8601String(),
            'sendsRemaining' => max(0, (int) config('otp.max_sends') - $sendsUsed),
            'attemptsRemaining' => $pending->otp_code === null
                ? 0
                : max(0, (int) config('otp.max_attempts') - $pending->otp_attempts),
            'resendAvailableAt' => $this->resendAvailableAt($pending)?->toIso8601String(),
        ];
    }

    /** Hapus pending milik email; no-op bila tidak ada. */
    public function cancel(string $email): void
    {
        PendingRegistration::query()->where('email', $email)->delete();
    }

    /**
     * Kode aktif milik email untuk command lokal/E2E; null di luar local/testing atau tanpa kode.
     */
    public function currentCode(string $email): ?string
    {
        if (! app()->environment(['local', 'testing'])) {
            return null;
        }

        return $this->find($email)?->otp_code;
    }

    /** Cari pending berdasarkan email; email unik sehingga maksimal satu baris. */
    private function find(string $email): ?PendingRegistration
    {
        return PendingRegistration::query()->where('email', $email)->first();
    }

    /**
     * Tolak kirim saat lock aktif, kuota habis, atau cooldown belum lewat.
     *
     * Kuota yang pulih setelah lock lewat diterapkan di sini agar berlaku untuk start dan resend.
     */
    private function guardSendQuota(PendingRegistration $pending, int $cooldownSeconds): ?OtpOutcome
    {
        if ($this->activeLock($pending) !== null) {
            return OtpOutcome::Locked;
        }

        $this->recoverExpiredQuota($pending);

        if ($pending->otp_sends >= (int) config('otp.max_sends')) {
            return OtpOutcome::Locked;
        }

        if ($cooldownSeconds > 0 && $this->resendAvailableAt($pending) !== null) {
            return OtpOutcome::Locked;
        }

        return null;
    }

    /**
     * Terbitkan kode baru: reset percobaan, naikkan kuota kirim, kunci bila kuota habis, lalu antrekan email.
     */
    private function issue(PendingRegistration $pending): void
    {
        $now = now();
        $code = $this->generateCode();
        $sends = $pending->otp_sends + 1;

        $pending->forceFill([
            'otp_code' => $code,
            'otp_expires_at' => $now->copy()->addMinutes((int) config('otp.ttl_minutes')),
            'otp_attempts' => 0,
            'otp_sends' => $sends,
            'otp_last_sent_at' => $now,
            'otp_locked_until' => $sends >= (int) config('otp.max_sends')
                ? $now->copy()->addMinutes((int) config('otp.lock_minutes'))
                : null,
        ])->save();

        SendRegistrationOtpJob::dispatch($pending->id);
    }

    /** Kode angka acak sepanjang `otp.length`, di-zero-pad. */
    private function generateCode(): string
    {
        $length = max(1, (int) config('otp.length'));

        return str_pad((string) random_int(0, (10 ** $length) - 1), $length, '0', STR_PAD_LEFT);
    }

    /**
     * Catat percobaan salah; percobaan terakhir menghanguskan kode.
     *
     * Hanya dipanggil di dalam transaksi `verify()` yang sudah mengunci baris pending.
     */
    private function registerFailedAttempt(PendingRegistration $pending): void
    {
        $attempts = $pending->otp_attempts + 1;
        $exhausted = $attempts >= (int) config('otp.max_attempts');

        $pending->forceFill([
            'otp_attempts' => $attempts,
            'otp_code' => $exhausted ? null : $pending->otp_code,
            'otp_expires_at' => $exhausted ? null : $pending->otp_expires_at,
        ])->save();
    }

    /**
     * Buat akun terverifikasi dari pending lalu hapus pending.
     *
     * Dipanggil di dalam transaksi `verify()` agar akun dan pending berubah bersama.
     */
    private function createVerifiedUser(PendingRegistration $pending): void
    {
        $user = User::create([
            'name' => $pending->name,
            'email' => $pending->email,
            'password' => $pending->password,
            'email_verified_at' => now(),
        ]);

        $user->assignRole('member');

        $pending->delete();
    }

    /** Kode dianggap hangus bila tidak ada atau sudah lewat masa berlaku. */
    private function isCodeExhausted(PendingRegistration $pending): bool
    {
        if ($pending->otp_code === null || $pending->otp_expires_at === null) {
            return true;
        }

        if ($pending->otp_attempts >= (int) config('otp.max_attempts')) {
            return true;
        }

        return $pending->otp_expires_at->isPast();
    }

    /** Lock kirim yang masih berlaku, atau null bila tidak ada. */
    private function activeLock(PendingRegistration $pending): ?CarbonInterface
    {
        if ($pending->otp_locked_until === null || ! $pending->otp_locked_until->isFuture()) {
            return null;
        }

        return $pending->otp_locked_until;
    }

    /** Lock sudah lewat sehingga jatah kirim perlu dipulihkan. */
    private function isLockExpired(PendingRegistration $pending): bool
    {
        return $pending->otp_locked_until !== null && ! $pending->otp_locked_until->isFuture();
    }

    /** Pulihkan jatah kirim penuh setelah lock 15 menit lewat. */
    private function recoverExpiredQuota(PendingRegistration $pending): void
    {
        if (! $this->isLockExpired($pending)) {
            return;
        }

        $pending->forceFill(['otp_sends' => 0, 'otp_locked_until' => null])->save();
    }

    /** Waktu paling awal kirim berikutnya diperbolehkan, atau null bila sudah boleh. */
    private function resendAvailableAt(PendingRegistration $pending): ?CarbonInterface
    {
        $lock = $this->activeLock($pending);

        if ($lock !== null) {
            return $lock;
        }

        if ($pending->otp_last_sent_at === null) {
            return null;
        }

        $cooldownEnds = $pending->otp_last_sent_at
            ->copy()
            ->addSeconds((int) config('otp.resend_cooldown_seconds'));

        return $cooldownEnds->isFuture() ? $cooldownEnds : null;
    }
}
