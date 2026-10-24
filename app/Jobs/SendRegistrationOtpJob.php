<?php

namespace App\Jobs;

use App\Models\PendingRegistration;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Antrean pengiriman kode OTP registrasi; payload hanya memuat id pending, kode dibaca dari model.
 */
class SendRegistrationOtpJob implements ShouldQueue
{
    use Dispatchable;
    use Queueable;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(
        public string $pendingId,
    ) {
    }

    /**
     * Kirim OTP untuk pending yang masih berlaku; berhenti tanpa kirim bila pending/kode sudah basi.
     */
    public function handle(): void
    {
        $pending = PendingRegistration::query()->find($this->pendingId);

        if ($pending === null) {
            return;
        }

        if ($pending->otp_code === null || $pending->otp_expires_at === null || $pending->otp_expires_at->isPast()) {
            return;
        }

        if (User::query()->where('email', $pending->email)->exists()) {
            return;
        }
    }
}
