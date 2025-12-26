<?php

namespace App\Livewire\Event;

use Filament\Actions\Action;
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

    public function editAction(): Action
    {
        return Action::make('edit')
            ->icon('heroicon-o-pencil-square')
            ->url(function (array $arguments) {
                return "?id=" . $arguments['id'];
            })
            ->color('warning');
    }

    public function deleteAction(): Action
    {
        return Action::make('delete')
            ->icon('heroicon-o-trash')
            ->requiresConfirmation()
            ->color('danger');
    }

    public function render()
    {
        return view('livewire.event.card-mode');
    }
}
