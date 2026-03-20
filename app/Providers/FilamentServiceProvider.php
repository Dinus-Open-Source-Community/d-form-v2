<?php

namespace App\Providers;

use Filament\Support\Colors\Color;
use Filament\Support\Facades\FilamentColor;
use Illuminate\Support\ServiceProvider;

class FilamentServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        FilamentColor::register([
            'gray' => Color::Neutral,
            'primary' => Color::hex('#748eb4'),
            'secondary' => Color::hex('#9ea2ad'),
            'accent' => Color::hex('#68a2ae'),
            'success' => Color::hex('#7da57f'),
            'warning' => Color::hex('#c6ac71'),
            'danger' => Color::hex('#ba7e84'),
        ]);
    }
}
