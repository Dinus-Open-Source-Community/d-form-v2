<?php

namespace App\Enums;

enum EmailNotificationType: string
{
    case RegistrationSubmitted = 'registration_submitted';
    case RegistrationAccepted = 'registration_accepted';
    case RegistrationRejected = 'registration_rejected';
    case AttendanceConfirmed = 'attendance_confirmed';
    case TeamInvitation = 'team_invitation';
}
