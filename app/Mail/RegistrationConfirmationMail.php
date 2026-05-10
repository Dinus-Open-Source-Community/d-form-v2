<?php

namespace App\Mail;

use App\Models\FormAnswer;
use App\Support\RegistrationPortalLinks;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class RegistrationConfirmationMail extends Mailable
{
    use SerializesModels;

    /**
     * @param  array<string, string>  $answersSummary  Label => display value
     * @param  string|null  $qrPngBinary  Omit for team/bundle leaders until an admin accepts the submission
     */
    public function __construct(
        public FormAnswer $submission,
        public array $answersSummary,
        public ?string $qrPngBinary = null,
    ) {
        $this->submission->loadMissing(['form.event', 'user']);
    }

    public function envelope(): Envelope
    {
        $event = $this->submission->form->event;

        return new Envelope(
            subject: __('Registration received: :title', ['title' => $event->title]),
        );
    }

    public function content(): Content
    {
        $showAttendanceQr = $this->qrPngBinary !== null && $this->qrPngBinary !== '';

        return new Content(
            html: 'mail.registration-confirmation',
            text: 'mail.registration-confirmation-text',
            with: [
                'submission' => $this->submission,
                'event' => $this->submission->form->event,
                'form' => $this->submission->form,
                'user' => $this->submission->user,
                'answersSummary' => $this->answersSummary,
                'showAttendanceQr' => $showAttendanceQr,
                'qrBase64' => $showAttendanceQr ? base64_encode($this->qrPngBinary) : null,
                'registrationDetailsUrl' => RegistrationPortalLinks::registrationDetailsUrl($this->submission->form->event),
            ],
        );
    }
}
