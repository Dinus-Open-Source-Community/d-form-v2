<?php

namespace App\Mail;

use App\Models\FormAnswer;
use App\Support\RegistrationPortalLinks;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class RegistrationRejectedMail extends Mailable
{
    use SerializesModels;

    public function __construct(
        public FormAnswer $submission,
    ) {}

    public function envelope(): Envelope
    {
        $event = $this->submission->form->event;

        return new Envelope(
            subject: __('Registration update: :title', ['title' => $event->title]),
        );
    }

    public function content(): Content
    {
        return new Content(
            html: 'mail.registration-rejected',
            text: 'mail.registration-rejected-text',
            with: [
                'submission' => $this->submission,
                'event' => $this->submission->form->event,
                'form' => $this->submission->form,
                'user' => $this->submission->user,
                'registrationDetailsUrl' => RegistrationPortalLinks::registrationDetailsUrl($this->submission->form->event),
            ],
        );
    }
}
