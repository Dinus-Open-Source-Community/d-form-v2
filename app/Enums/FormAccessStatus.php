<?php

namespace App\Enums;

enum FormAccessStatus: string
{
    case Allowed = 'allowed';
    case NotVisible = 'not_visible';
    case FormClosed = 'form_closed';
    case RegistrationNotOpen = 'registration_not_open';
    case QuotaFull = 'quota_full';
    case UnsupportedRegistrationMode = 'unsupported_registration_mode';
    case PendingTeamConfirmation = 'pending_team_confirmation';
    case AlreadySubmitted = 'already_submitted';

    public function message(): string
    {
        return match ($this) {
            self::Allowed           => '',
            self::NotVisible        => 'You do not have access to this form.',
            self::FormClosed        => 'This form is no longer accepting submissions.',
            self::RegistrationNotOpen => 'Registration for this event is not currently open.',
            self::QuotaFull         => 'Registration is full. No more submissions are being accepted.',
            self::UnsupportedRegistrationMode => 'This registration type is not available yet. Please check back later or contact the organizer.',
            self::PendingTeamConfirmation => 'You have a pending team invitation for this form. Please confirm your registration using the link we emailed you.',
            self::AlreadySubmitted  => 'You have already submitted this form.',
        };
    }

    public function isBlocked(): bool
    {
        return $this !== self::Allowed;
    }
}
