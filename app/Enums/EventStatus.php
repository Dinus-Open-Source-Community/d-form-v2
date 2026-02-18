<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum EventStatus: string implements HasLabel
{
    case Draft = 'draft';
    case Published = 'published';

    public function getLabel(): string
    {
        return ucwords(__('enum.event.status.' . $this->value));
    }
}
