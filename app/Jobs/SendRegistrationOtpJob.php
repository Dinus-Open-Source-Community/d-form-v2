<?php

namespace App\Jobs;

use App\Exceptions\RedactedMailDeliveryException;
use App\Mail\RegistrationOtpMail;
use App\Models\PendingRegistration;
use App\Models\User;
use App\Support\LogRedaction;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Antrean pengiriman kode OTP registrasi; payload hanya memuat id pending + waktu terbit, kode dibaca dari model.
 */
class SendRegistrationOtpJob implements ShouldQueue
{
    use Dispatchable;
    use Queueable;

    public int $tries = 3;

    public int $backoff = 30;

    public int $timeout = 60;

    /**
     * @param  string  $sentAt  ISO8601 `otp_last_sent_at` saat job diantrekan; dipakai mendeteksi job yang sudah disuperseded.
     */
    public function __construct(
        public string $pendingId,
        public string $sentAt,
    ) {
    }

    /**
     * Kirim OTP untuk pending yang masih berlaku; berhenti tanpa kirim bila pending/kode/job sudah basi.
     *
     * Kegagalan transport dicatat tanpa kode lalu dilempar ulang agar queue melakukan retry.
     */
    public function handle(): void
    {
        $pending = PendingRegistration::query()->find($this->pendingId);

        if ($pending === null) {
            return;
        }

        if ($pending->otp_code === null || $pending->otp_code === '') {
            return;
        }

        if ($pending->otp_expires_at === null || $pending->otp_expires_at->isPast()) {
            return;
        }

        if ($pending->otp_last_sent_at !== null && $pending->otp_last_sent_at->greaterThan(CarbonImmutable::parse($this->sentAt))) {
            return;
        }

        if (User::query()->where('email', $pending->email)->exists()) {
            return;
        }

        if ($this->isDisposableTestAddress($pending->email)) {
            Log::info('Pengiriman OTP dilewati untuk alamat uji.', ['pending_id' => $pending->id]);

            return;
        }

        try {
            Mail::to($pending->email)->send(
                new RegistrationOtpMail($pending->name, $pending->otp_code, (int) config('otp.ttl_minutes')),
            );
        } catch (Throwable $exception) {
            Log::error('Gagal mengirim kode verifikasi OTP registrasi.', [
                'pending_id' => $pending->id,
                'job_id' => $this->job?->getJobId(),
                'attempt' => $this->attempts(),
                'exception_class' => $exception::class,
                'error' => LogRedaction::exception($exception, 1000),
            ]);

            throw RedactedMailDeliveryException::fromThrowable($exception);
        }

        Log::info('[SendRegistrationOtpJob] OTP sent.', [
            'pending_id' => $pending->id,
            'job_id' => $this->job?->getJobId(),
            'attempt' => $this->attempts(),
        ]);
    }

    public function failed(?Throwable $exception = null): void
    {
        Log::error('[SendRegistrationOtpJob] Job failed permanently.', [
            'job' => static::class,
            'pending_id' => $this->pendingId,
            'job_id' => $this->job?->getJobId(),
            'exception_class' => $exception === null ? null : $exception::class,
        ]);
    }

    /**
     * Alamat `.test` dicadangkan untuk pengujian; jangan kirim email nyata saat dev/E2E.
     * Unit test tetap berjalan karena memakai Mail::fake (`runningUnitTests`).
     */
    private function isDisposableTestAddress(string $email): bool
    {
        return ! app()->runningUnitTests()
            && app()->environment(['local', 'testing'])
            && str_ends_with(strtolower($email), '.test');
    }
}
