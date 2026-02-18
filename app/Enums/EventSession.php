<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum EventSession: string implements HasLabel
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
