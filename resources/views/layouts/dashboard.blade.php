@props([
    'title' => null,
])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="">
    <head>
        <meta charset="UTF-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1.0" />
        <title>{{ $title ? "DForm - $title" : 'Dform' }}</title>

        @vite('resources/css/app.css')
        @filamentStyles
    </head>
    <body>
        <div class="drawer lg:drawer-open">
            <input id="main-dashboard-drawer" type="checkbox" class="drawer-toggle" />

            <div class="drawer-content">
                <!-- Page content here -->
                {{-- <label for="main-dashboard-drawer" class="btn drawer-button">Open drawer</label> --}}

                <x-navbar.dashboard-navbar />

                {{ $slot }}
            </div>

            <div class="drawer-side">
                <label for="main-dashboard-drawer" aria-label="close sidebar" class="drawer-overlay"></label>

                <x-sidebar.dashboard-sidebar />
            </div>
        </div>

        @livewire('notifications')

        @vite('resources/js/app.js')
        @livewireScriptConfig
        @filamentScripts
    </body>
</html>
