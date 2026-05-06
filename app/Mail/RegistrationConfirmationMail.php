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
     */
    public function __construct(
        public FormAnswer $submission,
        public array $answersSummary,
    ) {}

    public function envelope(): Envelope
    {
        $event = $this->submission->form->event;

        return new Envelope(
            subject: __('Registration received: :title', ['title' => $event->title]),
        );
    }

    public function content(): Content
    {
        return new Content(
            html: 'mail.registration-confirmation',
            text: 'mail.registration-confirmation-text',
            with: [
                'submission' => $this->submission,
                'event' => $this->submission->form->event,
                'form' => $this->submission->form,
                'user' => $this->submission->user,
                'answersSummary' => $this->answersSummary,
                'registrationDetailsUrl' => RegistrationPortalLinks::registrationDetailsUrl($this->submission->form->event),
            ],
        );
    }
}
