@props([
    'event',
])

<div class="border-base-300 grid grid-cols-2 gap-4 rounded-lg md:grid-cols-1 md:border md:p-6 xl:grid-cols-2">
    @if ($event->deleted_at !== null)
        <button
            class="btn btn-success btn-soft group relative col-span-2 md:col-span-1 xl:col-span-2"
            x-on:click="$wire.mountAction('restore')"
            wire:key="restore-btn-{{ $event->id }}"
        >
            @svg('heroicon-o-arrow-path', 'size-[1.4em] opacity-100 transition-all duration-300 md:absolute lg:group-hover:opacity-0')
            <span class="transition-all duration-300 md:opacity-0 lg:group-hover:opacity-100">
                {{ __('Restore') }}
            </span>
        </button>
    @else
        <a
            href="{{ route('dashboard.events.edit', $event['id']) }}"
            class="btn btn-warning btn-soft group md:relative"
        >
            @svg('heroicon-o-pencil-square', 'size-[1.4em] opacity-100 transition-all duration-300 md:absolute lg:group-hover:opacity-0')
            <span class="transition-all duration-300 md:opacity-0 lg:group-hover:opacity-100">
                {{ __('Edit') }}
            </span>
        </a>

        <button
            class="btn btn-error btn-soft group relative"
            x-on:click="$wire.mountAction('delete')"
            wire:key="delete-btn-{{ $event->id }}"
        >
            @svg('heroicon-o-trash', 'size-[1.4em] opacity-100 transition-all duration-300 md:absolute lg:group-hover:opacity-0')
            <span class="transition-all duration-300 md:opacity-0 lg:group-hover:opacity-100">
                {{ __('Delete') }}
            </span>
        </button>

        <button class="btn btn-primary btn-soft group relative">
            @svg('heroicon-o-arrow-down-tray', 'size-[1.4em] opacity-100 transition-all duration-300 md:absolute lg:group-hover:opacity-0')
            <span class="transition-all duration-300 md:opacity-0 lg:group-hover:opacity-100">
                {{ __('Export') }}
            </span>
        </button>

        <button class="btn btn-primary btn-soft group relative">
            @svg('heroicon-o-arrow-up-tray', 'size-[1.4em] opacity-100 transition-all duration-300 md:absolute lg:group-hover:opacity-0')
            <span class="transition-all duration-300 md:opacity-0 lg:group-hover:opacity-100">
                {{ __('Import') }}
            </span>
        </button>
    @endif

    {{--
        <button class="btn btn-success btn-soft group relative">
        @svg('heroicon-o-qr-code', 'size-[1.4em] opacity-100 transition-all duration-300 md:absolute lg:group-hover:opacity-0')
        <span class="transition-all duration-300 md:opacity-0 lg:group-hover:opacity-100">
        {{ __('Show QR') }}
        </span>
        </button>
    --}}
</div>
