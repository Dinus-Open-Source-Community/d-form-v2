<?php

namespace App\Mail;

use App\Models\FormAnswer;
use App\Support\RegistrationPortalLinks;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TeamInvitationMail extends Mailable
{
    use SerializesModels;

    public function __construct(
        public FormAnswer $memberSubmission,
    ) {
        $this->memberSubmission->loadMissing(['form.event', 'user', 'teamLeaderSubmission.user']);
    }

    public function envelope(): Envelope
    {
        $event = $this->memberSubmission->form->event;

        return new Envelope(
            subject: __('Team invitation: :title', ['title' => $event->title]),
        );
    }

    public function content(): Content
    {
        $token = $this->memberSubmission->invitation_token;
        if ($token === null || $token === '') {
            throw new \LogicException('Member submission is missing invitation_token.');
        }

        return new Content(
            html: 'mail.team-invitation',
            text: 'mail.team-invitation-text',
            with: [
                'memberSubmission' => $this->memberSubmission,
                'event' => $this->memberSubmission->form->event,
                'form' => $this->memberSubmission->form,
                'member' => $this->memberSubmission->user,
                'leader' => $this->memberSubmission->teamLeaderSubmission?->user,
                'confirmUrl' => RegistrationPortalLinks::teamInvitationConfirmUrl($token),
            ],
        );
    }
}
