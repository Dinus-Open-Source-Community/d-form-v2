<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;
use Illuminate\Contracts\Support\Htmlable;

enum EventCategory: string implements HasLabel
{
    case RKT = 'rkt';
    case NON_RKT = 'non-rkt';
    case RECRUITMENT = 'recruitment';
    case ETC = 'etc';

    public function getLabel(): string|Htmlable|null
    {
        return in_array($this->value, ['etc', 'recruitment']) ? ucfirst(__($this->value)) : strtoupper(implode(' ', explode('-', $this->value)));
    }
}
