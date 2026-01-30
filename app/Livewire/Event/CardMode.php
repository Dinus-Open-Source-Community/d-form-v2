<?php

namespace App\Livewire\Event;

use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Livewire\Attributes\Reactive;
use Livewire\Component;

class CardMode extends Component implements HasActions, HasSchemas
{
    use InteractsWithActions;
    use InteractsWithSchemas;

    #[Reactive]
    public $events = [];

    public function render()
    {
        return view('livewire.event.card-mode');
    }
}
