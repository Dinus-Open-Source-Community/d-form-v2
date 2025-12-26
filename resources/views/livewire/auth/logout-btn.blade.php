<div>
    @if ($this->teleportToSidebar)
        @teleport('#sidebar-footer')
            {{ $this->logoutAction }}
        @endteleport
    @else
        {{ $this->logoutAction }}
    @endif

    <x-filament-actions::modals class="fixed w-screen" />
</div>
