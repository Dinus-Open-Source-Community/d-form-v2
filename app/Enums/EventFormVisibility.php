<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;
use Illuminate\Contracts\Support\Htmlable;

enum EventFormVisibility: string implements HasLabel
{
    case Public = 'public';
    case Participant = 'participant';
    case Admin = 'admin';

    public function getLabel(): string|Htmlable|null
    {
        return ucfirst(__("enum.event.form.{$this->value}"));
    }
}
