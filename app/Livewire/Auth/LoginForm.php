<?php

namespace App\Livewire\Auth;

use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Redirector;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Component;

class LoginForm extends Component implements HasSchemas
{
    use InteractsWithSchemas;

    public array $data = [
        'email' => '',
        'password' => '',
    ];

    public function render(): View
    {
        return view('livewire.auth.login-form');
    }

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('email')
                ->label('Email')
                ->required()
                ->email(),
            TextInput::make('password')
                ->label('Password')
                ->required()
                ->password()
                ->revealable(),
        ])->statePath('data');
    }

    public function submit(Request $request): RedirectResponse | Redirector | null
    {
        $credentials = $this->form->getState();

        if (Auth::attempt($credentials)) {
            $request->session()->regenerate();

            Notification::make()
                ->success()
                ->title(explode(', ', __('auth.login_success'))[0])
                ->body(explode(', ', __('auth.login_success'))[1])
                ->send();

            $this->form->fill();

            return redirect()->intended('dashboard');
        }

        Notification::make()
            ->danger()
            ->title(explode(', ', __('auth.login_failed'))[0])
            ->body(explode(', ', __('auth.login_failed'))[1])
            ->send();

        $this->form->fill([
            'email' => $credentials['email'],
            'password' => ''
        ]);

        return null;
    }
}
