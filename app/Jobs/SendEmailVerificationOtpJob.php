<?php

namespace App\Jobs;

use App\Exceptions\RedactedMailDeliveryException;
use App\Mail\EmailVerificationOtpMail;
use App\Models\EmailVerificationCode;
use App\Models\User;
use App\Services\Auth\EmailVerificationService;
use App\Support\LogRedaction;
use App\Support\Otp\OtpCode;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Antrean pengiriman kode verifikasi email; payload hanya memuat user id + waktu terbit.
 * Kode dibuat di dalam job supaya plaintext tidak pernah tersimpan di tabel maupun payload.
 * Purpose diambil dari tantangan agar email pemulihan PIN tidak memakai salinan verifikasi email.
 */
class SendEmailVerificationOtpJob implements ShouldQueue
{
    use Dispatchable;
    use Queueable;

    public int $tries = 3;

    public int $backoff = 30;

    public int $timeout = 60;

    /**
     *
     * @param  string  $sentAt  ISO8601 `last_sent_at` saat job diantrekan; dipakai mendeteksi job yang sudah disuperseded.
     *
     */
    public function __construct(
        public string $userId,
        public string $sentAt,
    ) {
    }

    /** Kirim kode untuk tantangan yang masih berlaku; berhenti tanpa kirim bila tantangan/job sudah basi. */
    public function handle(): void
    {
        $challenge = EmailVerificationCode::query()->where('user_id', $this->userId)->first();

        if ($challenge === null) {
            return;
        }

        if ($challenge->expires_at === null || $challenge->expires_at->isPast()) {
            return;
        }

        if ($challenge->last_sent_at === null) {
            return;
        }

        if ($challenge->last_sent_at->greaterThan(CarbonImmutable::parse($this->sentAt))) {
            return;
        }

        $user = User::query()->find($this->userId);

        // Alur verifikasi email berhenti bila alamat sudah terverifikasi; alur pemulihan PIN
        // justru hanya berjalan untuk akun terverifikasi, jadi penjaga ini tidak berlaku.
        if ($user === null) {
            return;
        }

        if ($challenge->purpose !== EmailVerificationService::PURPOSE_TWO_FACTOR_RECOVERY
            && $user->email_verified_at !== null) {
            return;
        }

        $ttlMinutes = (int) config('otp.ttl_minutes');
        $code = OtpCode::generate((int) config('otp.length'));

        if (! $this->storeHash($challenge, $code, $ttlMinutes)) {
            return;
        }

        $this->rememberLocalCode($code, $ttlMinutes);

        if ($this->isDisposableTestAddress($user->email)) {
            Log::info('Pengiriman kode verifikasi email dilewati untuk alamat uji.', ['user_id' => $user->getKey()]);

            return;
        }

        try {
            Mail::to($user->email)->send(
                new EmailVerificationOtpMail($user->name, $code, $ttlMinutes, $challenge->purpose),
            );
        } catch (Throwable $exception) {
            Log::error('Gagal mengirim kode verifikasi email.', [
                'user_id' => $user->getKey(),
                'job_id' => $this->job?->getJobId(),
                'attempt' => $this->attempts(),
                'exception_class' => $exception::class,
                'error' => LogRedaction::exception($exception, 1000),
            ]);

            throw RedactedMailDeliveryException::fromThrowable($exception);
        }

        Log::info('[SendEmailVerificationOtpJob] verification code sent.', [
            'user_id' => $user->getKey(),
            'job_id' => $this->job?->getJobId(),
            'attempt' => $this->attempts(),
        ]);
    }

    public function failed(?Throwable $exception = null): void
    {
        Log::error('[SendEmailVerificationOtpJob] Job failed permanently.', [
            'job' => static::class,
            'user_id' => $this->userId,
            'job_id' => $this->job?->getJobId(),
            'exception_class' => $exception === null ? null : $exception::class,
        ]);
    }

    /** Tulis HMAC kode dan segarkan masa berlaku sesuai saat kirim; menolak menimpa tantangan yang sudah diganti kiriman lebih baru. */
    private function storeHash(EmailVerificationCode $challenge, string $code, int $ttlMinutes): bool
    {
        return DB::transaction(function () use ($challenge, $code, $ttlMinutes): bool {
            $fresh = EmailVerificationCode::query()
                ->whereKey($challenge->getKey())
                ->lockForUpdate()
                ->first();

            if ($fresh === null || $fresh->last_sent_at === null) {
                return false;
            }

            if (! $fresh->last_sent_at->equalTo(CarbonImmutable::parse($this->sentAt))) {
                return false;
            }

            // `sends` sudah dinaikkan service saat menerbitkan; job hanya melengkapi hash dan TTL nyata.
            $fresh->forceFill([
                'code_hash' => OtpCode::hash($code),
                'expires_at' => now()->addMinutes($ttlMinutes),
            ])->save();

            return true;
        });
    }

    /** Simpan kode plaintext hanya di local/testing agar command bantu E2E bisa mencetaknya. */
    private function rememberLocalCode(string $code, int $ttlMinutes): void
    {
        if (! app()->environment(['local', 'testing'])) {
            return;
        }

        Cache::put(
            EmailVerificationService::codeCacheKey($this->userId),
            $code,
            now()->addMinutes($ttlMinutes),
        );
    }

    /** Alamat `.test` dicadangkan untuk pengujian; jangan kirim email nyata saat dev/E2E. */
    private function isDisposableTestAddress(string $email): bool
    {
        return ! app()->runningUnitTests()
            && app()->environment(['local', 'testing'])
            && str_ends_with(strtolower($email), '.test');
    }
}
