<?php

namespace App\Support;

use App\Models\Event;

final class RegistrationPortalLinks
{
    public static function registrationDetailsUrl(Event $event): string
    {
        $segment = $event->slug !== null && $event->slug !== ''
            ? $event->slug
            : (string) $event->getKey();

        return route('dashboard.user.events.registration', [
            'event_segment' => $segment,
        ], absolute: true);
    }

    public static function teamInvitationConfirmUrl(string $token): string
    {
        return route('dashboard.user.team-invitations.show', ['token' => $token], absolute: true);
    }
}
