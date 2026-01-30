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
        @livewireStyles
    </head>
    <body>
        <div id="components"></div>

        <div class="drawer lg:drawer-open">
            <input id="main-dashboard-drawer" type="checkbox" class="drawer-toggle" />

            <div class="drawer-content pb-12">
                <!-- Page content here -->
                {{-- <label for="main-dashboard-drawer" class="btn drawer-button">Open drawer</label> --}}

                <x-core::navbar.dashboard-navbar :title="$title" />

                {{ $slot }}
            </div>

            <div class="drawer-side">
                <label for="main-dashboard-drawer" aria-label="close sidebar" class="drawer-overlay"></label>

                <x-core::sidebar.dashboard-sidebar />
            </div>
        </div>

        @livewire('auth.logout-btn', ['teleportToSidebar' => true])

        @livewire('notifications')

        @stack('components')

        @filamentScripts
        @livewireScriptConfig
        @vite('resources/js/app.js')
    </body>
</html>
