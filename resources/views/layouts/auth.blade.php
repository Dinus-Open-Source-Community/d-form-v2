<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="">
    <head>
        <meta charset="UTF-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1.0" />
        <title>DForm - Auth Page</title>

        @vite('resources/css/app.css')
        @filamentStyles
    </head>
    <body>
        {{ $slot }}

        @livewire('notifications')

        @vite('resources/js/app.js')
        @livewireScriptConfig
        @filamentScripts
    </body>
</html>
