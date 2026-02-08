<div>
    <div class="grid w-full grid-cols-2 grid-rows-2 gap-3 md:grid-cols-3 md:grid-rows-1">
        <div
            class="col-span-2 row-start-2 row-end-2 flex w-full items-center gap-3 md:col-span-1 md:row-start-1 md:row-end-1"
            {{-- wire:submit.prevent="" --}}
        >
            <div class="flex-1">
                {{ $this->searchForm }}
            </div>
        </div>

        <div>
            <button @class(['btn dark:btn-soft rounded-lg']) x-on:click="$wire.mountAction('filter')">
                @svg('heroicon-o-funnel', 'size-6')
            </button>
            {{-- {{ $this->filterAction }} --}}
            {{-- {{ $this->sortingAction }} --}}
        </div>

        <div class="flex justify-end gap-3">
            <a href="{{ route('dashboard.events.create') }}" class="btn btn-primary rounded-lg">
                @svg('heroicon-o-plus', 'size-6')
                <span class="hidden md:inline">
                    {{ __('Create new') }}
                </span>
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

    {{--
        <div class="mt-3">
        @if ($this->search !== '')
        <button class="btn rounded-full font-semibold" wire:click="clearSearch">
        <span class="badge badge-primary badge-sm">Search:</span>
        {{ $this->search }}
        @svg('heroicon-o-x-mark', 'size-[1em]')
        </button>
        @endif
        
        @if ($this->filter['categories'])
        @endif
        </div>
    --}}

    {{--
        <div class="mt-3">
        <span>total: {{ count($this->data) }}</span>
        </div>
    --}}

    @if ($this->mode === 'card')
        <livewire:event.card-mode :events="$this->data" />
    @endif

    <x-filament-actions::modals />
</div>
