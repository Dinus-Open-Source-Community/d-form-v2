<div class="my-6 grid grid-cols-3 gap-4">
    @if ($this->events->isEmpty())
        <div class="col-span-3 flex flex-col items-center pt-12 md:pt-20">
            @svg('heroicon-o-exclamation-triangle', 'text-base-content size-12 md:size-16 lg:size-24')
            <h4 class="text-base-content text-3xl md:text-4xl">
                {{ __('There is no content.') }}
            </h4>
        </div>
    @else
        @foreach ($this->events as $event)
            <div class="card bg-base-100 border-base-300 border shadow-sm">
                <figure class="relative">
                    <img
                        src="{{ asset('storage/' . $event['banner']) }}"
                        alt="{{ $event['title'] }}"
                        class="aspect-video"
                    />

                    <div
                        class="absolute top-0 left-0 flex h-full w-full items-start justify-end gap-3 bg-linear-to-b from-slate-800/50 to-transparent px-4 pt-4"
                    >
                        <div class="flex items-center gap-3">
                            <span class="bg-accent/30 inline-flex rounded-full px-3 py-1 text-slate-100">
                                {{ $event['status'] }}
                            </span>

                            <x-filament-actions::group
                                :actions="[
                                    ($this->editAction)(['id' => $event['id']]),
                                    $this->deleteAction
                                ]"
                                size="lg"
                                iconSize="2xl"
                                color="accent"
                            />
                        </div>
                    </div>
                </figure>
                <div class="card-body">
                    <h2 class="card-title">
                        {{ $event['title'] }}
                    </h2>
                    <p>
                        {{ str($event['description'])->limit(100) }}
                    </p>

                    <div class="card-actions items-center justify-end">
                        <button class="btn btn-primary">Detail</button>
                    </div>
                </div>
            </div>
        @endforeach
    @endif

    <x-filament-actions::modals />
</div>
