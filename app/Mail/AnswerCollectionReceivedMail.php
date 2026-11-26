<?php

namespace App\Mail;

use App\Models\FormAnswer;
use App\Services\Registration\FormReceiptContext;
use App\Services\Registration\RegistrationNotificationMailData;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

final class AnswerCollectionReceivedMail extends Mailable
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
        return new Envelope(subject: __('Jawabanmu sudah kami terima: :form', ['form' => $this->submission->form->title]));
    }

    public function content(): Content
    {
        return new Content(
            html: 'mail.form-receipt',
            with: array_merge(app(RegistrationNotificationMailData::class)->shared($this->submission), [
                'receiptKind' => 'answer_collection',
                'answersSummary' => $this->context->answersSummary,
                'sessionDates' => $this->context->sessionDates,
            ]),
        );
    }
}
