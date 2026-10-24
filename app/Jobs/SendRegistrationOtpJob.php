<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Antrean pengiriman kode OTP registrasi; pengiriman email diisi bersama mailable OTP.
 */
class SendRegistrationOtpJob implements ShouldQueue
{
    use Dispatchable;
    use Queueable;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(
        public string $pendingId,
        public string $code,
    ) {
    }

    /** Titik masuk worker; isi pengiriman email diisi pada perubahan mailable OTP. */
    public function handle(): void
    {
    }
}
