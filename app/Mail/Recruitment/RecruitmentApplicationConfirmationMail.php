<?php

namespace App\Mail\Recruitment;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class RecruitmentApplicationConfirmationMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public string $subjectLine,
        public string $bodyHtml,
        public string $bodyText,
        public ?string $qrPngBinary = null,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->subjectLine,
        );
    }

    public function content(): Content
    {
        return new Content(
            html: 'mail.recruitment-notification',
            text: 'mail.recruitment-plain',
            with: [
                'bodyHtml' => $this->bodyHtml,
                'bodyText' => $this->bodyText,
                'subjectLine' => $this->subjectLine,
            ],
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        if ($this->qrPngBinary === null || $this->qrPngBinary === '') {
            return [];
        }

        return [
            Attachment::fromData(fn () => $this->qrPngBinary, 'qr-code-interview.png')
                ->withMime('image/png')
                ->as('qr-code-interview.png'),
        ];
    }
}
