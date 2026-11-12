<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Email kode OTP registrasi; dibuat oleh job setelah kode dibaca dari model, tidak pernah ikut payload queue.
 */
class RegistrationOtpMail extends Mailable
{
    use SerializesModels;

    public function __construct(
        public string $recipientName,
        public string $code,
        public int $ttlMinutes,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Kode verifikasi DForm',
        );
    }

    public function content(): Content
    {
        return new Content(
            html: 'mail.registration-otp',
            text: 'mail.registration-otp-text',
        );
    }
}
