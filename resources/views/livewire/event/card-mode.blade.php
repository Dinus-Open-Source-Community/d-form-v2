<div class="my-6 grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
    @empty($this->events)
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
                        class="absolute top-0 left-0 flex h-full w-full items-start justify-end gap-3 bg-linear-to-b from-slate-800/50 to-transparent pt-4"
                    >
                        <div
                            @class([
                                'relative inline-flex h-0 w-[10rem] border-[15px] border-l-[15px] border-l-transparent bg-transparent ',
                                'border-primary text-primary-content' =>
                                    ! $event['deleted_at'] && $event['status'] === \App\Enums\EventStatus::Published,
                                'border-slate-300 text-slate-900' => ! $event['deleted_at'] && $event['status'] === \App\Enums\EventStatus::Draft,
                                'border-error text-error-content' => $event['deleted_at'],
                            ])
                        >
                            <span class="absolute top-0 right-0 left-0 translate-y-[-50%] text-center">
                                {{ $event['deleted_at'] ? 'Trashed' : $event['status'] }}
                            </span>
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
                        <a
                            href="{{ route('dashboard.events.show', ['event' => $event['id']]) }}"
                            class="btn btn-primary"
                        >
                            Detail
                        </a>
                    </div>
                </div>
            </div>
        @endforeach
    @endempty

    <x-filament-actions::modals />
</div>
