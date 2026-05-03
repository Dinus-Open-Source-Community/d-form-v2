<?php

namespace App\Enums;

enum EventSession: string
{
    case General = 'general';
    case Programming = 'programming';
    case Networking = 'network';
    case MediaCreative = 'media_creative';
    case Data = 'data';

    public function getLabel(): string
    {
        return ucwords(__('enum.event.session.' . $this->value));
    }
}
