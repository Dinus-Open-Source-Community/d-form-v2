<div>
    <div class="grid w-full grid-cols-3 gap-3">
        <form class="flex w-full items-center gap-3">
            <div class="flex-1">
                {{ $this->searchForm }}
            </div>
        </form>

        <div>
            {{ $this->filterAction }}
            {{ $this->sortingAction }}
            {{--
                <button class="btn dark:btn-soft rounded-lg">
                @svg('heroicon-o-funnel', 'size-6')
                </button>
            --}}

            {{--
                <button class="btn dark:btn-soft rounded-lg">
                @svg('heroicon-o-bars-arrow-down', 'size-6')
                </button>
            --}}
        </div>

        <div class="flex justify-end gap-3">
            <a href="{{ route('dashboard.events.create') }}" class="btn btn-primary rounded-lg">
                @svg('heroicon-o-plus', 'size-6')
                {{ __('Create new') }}
            </a>

            <div class="flex" x-data>
                <button
                    @class(['btn rounded-l-lg rounded-r-none', $this->mode === 'card' ? 'btn-primary' : 'dark:btn-soft'])
                    x-on:click="$wire.set('mode', 'card')"
                >
                    @svg('heroicon-o-squares-2x2', 'size-6')
                </button>
                <button
                    @class(['btn rounded-l-none rounded-r-lg', $this->mode === 'table' ? 'btn-primary' : 'dark:btn-soft'])
                    x-on:click="$wire.set('mode', 'table')"
                >
                    @svg('heroicon-o-table-cells', 'size-6')
                </button>
            </div>
        </div>
    </div>

    <div class="mt-3">
        @if ($this->search !== '')
            <button class="btn rounded-full font-semibold" wire:click="clearSearch">
                <span class="badge badge-primary badge-sm">Search:</span>
                {{ $this->search }}
                @svg('heroicon-o-x-mark', 'size-[1em]')
            </button>
        @endif
    </div>

    @if ($this->mode === 'card')
        <livewire:event.card-mode :events="$this->data" />
    @endif
</div>
