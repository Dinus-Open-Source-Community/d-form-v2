<?php

namespace App\Jobs;

use App\Mail\RegistrationOtpMail;
use App\Models\PendingRegistration;
use App\Models\User;
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

        try {
            Mail::to($pending->email)->send(
                new RegistrationOtpMail($pending->name, $pending->otp_code, (int) config('otp.ttl_minutes')),
            );
        } catch (Throwable $exception) {
            Log::error('Gagal mengirim kode verifikasi OTP registrasi.', [
                'pending_id' => $pending->id,
                'error' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }
}
