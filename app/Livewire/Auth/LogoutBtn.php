<?php

namespace App\Livewire\Auth;

use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Support\Enums\Size;
use Livewire\Component;

class LogoutBtn extends Component implements HasActions, HasSchemas
{
    use InteractsWithActions;
    use InteractsWithSchemas;

    public bool $teleportToSidebar = false;

    public function logoutAction(): Action
    {
        return Action::make('logout')
            ->button()
            ->label('Sign out')
            ->icon(null)
            ->color('danger')
            ->requiresConfirmation()
            ->size(Size::Large)
            ->extraAttributes([
                'class' => "text-center w-full btn btn-base-100 dark:btn-ghost"
            ])
            ->action(function () {
                auth()->guard()->logout();

                request()->session()->invalidate();

                request()->session()->regenerateToken();

                return to_route('home');
            });
    }

    public function render()
    {
        return view('livewire.auth.logout-btn');
    }
}
