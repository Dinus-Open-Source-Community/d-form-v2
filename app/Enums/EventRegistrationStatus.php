<?php

namespace App\Enums;

enum EventRegistrationStatus: string
{
    case NotYetOpen = 'not_yet_open';
    case Open = 'open';
    case Closed = 'closed';
    case Full = 'full';
}
