<div class="flex grid-cols-6 grid-rows-2 flex-wrap gap-8 md:grid md:grid-rows-1 md:gap-4">
    <div class="md:col-span-5 lg:col-span-4">
        {{ $this->eventDetailInfolist }}
    </div>

    <div class="top-24 flex h-fit w-full flex-col gap-4 md:sticky md:col-span-1 lg:col-span-2">
        <div class="border-base-300 grid grid-cols-2 gap-4 rounded-lg md:grid-cols-1 md:border md:p-6 2xl:grid-cols-2">
            <a
                href="{{ route('dashboard.events.edit', $event['id']) }}"
                class="btn btn-warning btn-soft group md:relative"
            >
                @svg('heroicon-o-pencil-square', 'size-[1.4em] opacity-100 transition-all duration-300 md:absolute lg:group-hover:opacity-0')
                <span class="transition-all duration-300 md:opacity-0 lg:group-hover:opacity-100">Edit</span>
            </a>

            @if ($event->deleted_at !== null)
                <button class="btn btn-success btn-soft group relative" x-on:click="$wire.mountAction('restore')">
                    @svg('heroicon-o-arrow-path', 'size-[1.4em] opacity-100 transition-all duration-300 md:absolute lg:group-hover:opacity-0')
                    <span class="transition-all duration-300 md:opacity-0 lg:group-hover:opacity-100">Restore</span>
                </button>
            @else
                <button class="btn btn-error btn-soft group relative" x-on:click="$wire.mountAction('delete')">
                    @svg('heroicon-o-trash', 'size-[1.4em] opacity-100 transition-all duration-300 md:absolute lg:group-hover:opacity-0')
                    <span class="transition-all duration-300 md:opacity-0 lg:group-hover:opacity-100">Delete</span>
                </button>
            @endif

            <button class="btn btn-primary btn-soft group relative">
                @svg('heroicon-o-arrow-down-tray', 'size-[1.4em] opacity-100 transition-all duration-300 md:absolute lg:group-hover:opacity-0')
                <span class="transition-all duration-300 md:opacity-0 lg:group-hover:opacity-100">Export</span>
            </button>

            <button class="btn btn-primary btn-soft group relative">
                @svg('heroicon-o-arrow-up-tray', 'size-[1.4em] opacity-100 transition-all duration-300 md:absolute lg:group-hover:opacity-0')
                <span class="transition-all duration-300 md:opacity-0 lg:group-hover:opacity-100">Import</span>
            </button>

            <button class="btn btn-success btn-soft group relative">
                @svg('heroicon-o-qr-code', 'size-[1.4em] opacity-100 transition-all duration-300 md:absolute lg:group-hover:opacity-0')
                <span class="transition-all duration-300 md:opacity-0 lg:group-hover:opacity-100">Show QR</span>
            </button>
        </div>

        <div class="border-base-300 grid h-fit w-full grid-cols-1 gap-4 rounded-lg md:border md:p-6">
            <h4 class="text-base-content font-bold">{{ __('Forms') }}</h4>

            <div class="flex flex-col gap-3">
                <p>{{ __('No forms found.') }}</p>
            </div>

            <button class="btn btn-primary btn-soft w-full flex-1">
                @svg('heroicon-o-plus', 'size-[1em]')
                <span>
                    {{ __('Create new form') }}
                </span>
            </button>
        </div>

        <div class="border-base-300 grid h-fit w-full grid-cols-1 gap-4 rounded-lg md:border md:p-6">
            <h4 class="text-base-content font-bold">{{ __('Schedules') }}</h4>

            <div class="flex flex-col gap-3">
                <p>{{ __('No schedules found.') }}</p>
            </div>

            <button class="btn btn-primary btn-soft w-full flex-1">
                @svg('heroicon-o-plus', 'size-[1em]')
                <span>
                    {{ __('Create new schedule') }}
                </span>
            </button>
        </div>
    </div>

    <x-filament-actions::modals />
</div>
