<?php

namespace App\Mail;

use App\Models\FormAnswer;
use App\Services\Registration\RegistrationNotificationMailData;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class RegistrationAcceptedMail extends Mailable
{
    use SerializesModels;

    public function __construct(
        public FormAnswer $submission,
        public string $qrPngBinary,
        public string $registrationCode,
    ) {
    }

    public function envelope(): Envelope
    {
        $event = $this->submission->form->event;

        return new Envelope(
            subject: __('Registration accepted: :title', ['title' => $event->title]),
        );
    }

    public function content(): Content
    {
        $mailData = app(RegistrationNotificationMailData::class)
            ->shared($this->submission);

        return new Content(
            html: 'mail.registration-accepted',
            text: 'mail.registration-accepted-text',
            with: array_merge($mailData, [
                'registrationCode' => $this->registrationCode,
            ]),
        );
    }

    /**
     *
     * @return array<int, Attachment>
     *
     */
    public function attachments(): array
    {
        return [
            Attachment::fromData(fn () => $this->qrPngBinary, 'qr-code.png')
                ->withMime('image/png')
                ->as('qr-code.png'),
        ];
    }
}
