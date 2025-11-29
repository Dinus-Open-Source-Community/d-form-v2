<?php

namespace App\Livewire\Auth;

use App\Models\User;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Notifications\Notification;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Redirector;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Livewire\Component;

class RegisterForm extends Component implements HasSchemas
{
    use InteractsWithForms;

    public array $data = [
        'name' => '',
        'email' => '',
        'password' => '',
        'password_confirmation' => '',
    ];

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label(fn () => __("Full Name"))
                    ->required()
                    ->regex('/^[a-zA-Z\s]+$/')
                    ->maxLength(150),
                TextInput::make('email')
                    ->label('Email')
                    ->email()
                    ->unique('users', 'email')
                    ->maxLength(255)
                    ->required(),
                TextInput::make('password')
                    ->label('Password')
                    ->password()
                    ->confirmed()
                    ->revealable()
                    ->required(),
                TextInput::make('password_confirmation')
                    ->label(fn () => __("Confirm Password"))
                    ->password()
                    ->revealable()
                    ->required(),
            ])->statePath('data');
    }

    public function render(): View
    {
        return view('livewire.auth.register-form');
    }

    public function submit(Request $request): RedirectResponse | Redirector | null
    {
        $newUserData = $this->form->getState();

        try {
            DB::transaction(function () use ($newUserData) {
                $user = User::create(collect($newUserData)->only(['name', 'email', 'password'])->all());

                $user->assignRole('member');

                Auth::login($user);
            });

            $request->session()->regenerate();

            Notification::make()
                ->success()
                ->title(explode(', ', __('auth.register_success'))[0])
                ->body(explode(', ', __('auth.register_success'))[1])
                ->send();

            $this->form->fill();

            return redirect()->route('dashboard.home');
        } catch (\Exception $e) {
            Notification::make()
                ->danger()
                ->title(explode(', ', __('auth.register_failed'))[0])
                ->body(explode(', ', __('auth.register_failed'))[1])
                ->send();
        }

        return null;
    }
}
