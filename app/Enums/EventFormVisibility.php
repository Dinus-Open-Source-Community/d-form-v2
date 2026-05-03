<?php

namespace App\Enums;

use Illuminate\Contracts\Support\Htmlable;

enum EventFormVisibility: string
{
    case Public = 'public';
    case Participant = 'participant';
    case Admin = 'admin';

    public function getLabel(): string|Htmlable|null
    {
        return ucfirst(__("enum.event.form.{$this->value}"));
    }
}
