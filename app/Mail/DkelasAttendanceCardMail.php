<?php

namespace App\Mail;

use App\Models\FormAnswer;
use App\Services\Registration\FormReceiptContext;
use App\Services\Registration\RegistrationNotificationMailData;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

final class DkelasAttendanceCardMail extends Mailable
{
    use SerializesModels;

    public function __construct(
        public FormAnswer $submission,
        public FormReceiptContext $context,
    ) {
        $this->submission->loadMissing(['form.event', 'user']);
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('Kartu absen kelas: :class', ['class' => $this->submission->form->title]));
    }

    public function content(): Content
    {
        return new Content(
            html: 'mail.form-receipt',
            with: array_merge(app(RegistrationNotificationMailData::class)->shared($this->submission), [
                'receiptKind' => 'd_kelas',
                'answersSummary' => $this->context->answersSummary,
                'sessionDates' => $this->context->sessionDates,
                'showAttendanceQr' => $this->context->qrPngBinary !== null && $this->context->qrPngBinary !== '',
            ]),
        );
    }

    /** @return array<int, Attachment> */
    public function attachments(): array
    {
        if ($this->context->qrPngBinary === null || $this->context->qrPngBinary === '') {
            return [];
        }

        return [Attachment::fromData(fn (): string => $this->context->qrPngBinary ?? '', 'qr-code.png')->withMime('image/png')];
    }
}
