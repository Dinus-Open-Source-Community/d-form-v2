<?php

namespace App\Support;

use App\Models\Event;

final class RegistrationPortalLinks
{
    public static function registrationDetailsUrl(Event $event): string
    {
        return route('dashboard.user.events.registration', [
            'event_segment' => $event->slug ?? $event->getKey(),
        ], absolute: true);
    }
}
