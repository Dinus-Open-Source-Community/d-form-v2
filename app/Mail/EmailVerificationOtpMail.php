<?php

namespace App\Mail;

use App\Services\Auth\EmailVerificationService;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Email kode OTP; dibuat oleh job setelah kode di-hash, tidak pernah ikut payload queue.
 * Satu pasang template dipakai untuk dua purpose: verifikasi email dan pemulihan PIN login.
 */
class EmailVerificationOtpMail extends Mailable
{
    use SerializesModels;

    public function __construct(
        public string $recipientName,
        public string $code,
        public int $ttlMinutes,
        public string $purpose = EmailVerificationService::PURPOSE_EMAIL_VERIFICATION,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->isPinRecovery() ? 'Kode pemulihan PIN DForm' : 'Verifikasi email DForm',
        );
    }

    public function content(): Content
    {
        return new Content(
            html: 'mail.email-verification-otp',
            text: 'mail.email-verification-otp-text',
            with: ['pinRecovery' => $this->isPinRecovery()],
        );
    }

    /** Apakah kode ini untuk mengatur ulang PIN login, bukan memverifikasi alamat email. */
    public function isPinRecovery(): bool
    {
        return $this->purpose === EmailVerificationService::PURPOSE_TWO_FACTOR_RECOVERY;
    }
}
