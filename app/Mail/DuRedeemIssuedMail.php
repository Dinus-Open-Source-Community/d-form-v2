<?php

namespace App\Mail;

use App\Models\FormAnswer;
use App\Services\Registration\RegistrationNotificationMailData;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DuRedeemIssuedMail extends Mailable
{
    use SerializesModels;

    public function __construct(
        public FormAnswer $submission,
        public string $qrPngBinary,
        public string $redeemCode,
    ) {
        $this->submission->loadMissing(['form.event', 'user']);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('DU redeem code issued: :title', [
                'title' => $this->submission->form->event->title,
            ]),
        );
    }

    public function content(): Content
    {
        $mailData = app(RegistrationNotificationMailData::class)
            ->shared($this->submission);

        return new Content(
            html: 'mail.du-redeem-issued',
            text: 'mail.du-redeem-issued-text',
            with: array_merge($mailData, [
                'redeemCode' => $this->redeemCode,
            ]),
        );
    }

    /** @return array<int, Attachment> */
    public function attachments(): array
    {
        return [
            Attachment::fromData(fn (): string => $this->qrPngBinary, 'qr-code.png')
                ->withMime('image/png')
                ->as('qr-code.png'),
        ];
    }
}
