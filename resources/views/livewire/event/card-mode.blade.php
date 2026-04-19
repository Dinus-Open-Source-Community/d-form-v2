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
                        class="aspect-video object-cover object-center"
                    />

                    <div
                        class="absolute top-0 left-0 flex h-full w-full items-start justify-end gap-3 bg-linear-to-b from-slate-800/50 to-transparent"
                    >
                        <div class="relative h-full w-full">
                            <div class="absolute top-4 left-4 flex flex-wrap gap-1">
                                @foreach (array_values(array_filter(array_map('trim', explode(',', (string) ($event['category'] ?? ''))))) as $categoryToken)
                                    @php($categoryEnum = \App\Enums\EventCategory::tryFrom($categoryToken))
                                    <span class="badge badge-sm bg-secondary/80 text-secondary-content border-secondary capitalize shadow-sm backdrop-blur-md">
                                        {{ $categoryEnum?->getLabel() ?? \Illuminate\Support\Str::title(\Illuminate\Support\Str::of($categoryToken)->replace(['_', '-'], ' ')) }}
                                    </span>
                                @endforeach
                            </div>

                            <span
                                @class([
                                    'badge badge-sm absolute top-4 right-4 capitalize backdrop-blur-md',
                                    'bg-primary/80 border-primary text-primary-content shadow-sm' =>
                                        ! $event['deleted_at'] && $event['status'] === \App\Enums\EventStatus::Published->value,
                                    'bg-secondary/80 border-secondary text-secondary-content shadow-sm' =>
                                        ! $event['deleted_at'] && $event['status'] === \App\Enums\EventStatus::Draft->value,
                                    'bg-error/80 border-error text-error-content shadow-sm' => $event['deleted_at'],
                                ])
                            >
                                {{ $event['deleted_at'] ? __('enum.event.status.trashed') : __("enum.event.status.{$event['status']}") }}
                            </span>

                            <span
                                class="badge badge-sm bg-accent/70 text-accent-content border-accent absolute bottom-4 left-4 shadow-sm backdrop-blur-md"
                            >
                                {{ \Carbon\Carbon::parse($event['end_date'])->isoFormat('DD MMM YYYY') }}
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

                    <div class="flex flex-wrap justify-end gap-3">
                        @foreach (array_values(array_filter(array_map('trim', explode(',', (string) ($event['session'] ?? ''))))) as $sessionToken)
                            @php($sessionEnum = \App\Enums\EventSession::tryFrom($sessionToken))
                            <span class="badge-sm badge badge-ghost">
                                {{ $sessionEnum?->getLabel() ?? \Illuminate\Support\Str::title(\Illuminate\Support\Str::of($sessionToken)->replace(['_', '-'], ' ')) }}
                            </span>
                        @endforeach

                        <span
                            @class([
                                'badge-sm badge badge-soft',
                                'badge-success' => $event['price'] < 50000,
                                'badge-error' => $event['price'] >= 50000,
                            ])
                        >
                            {{ 'Rp. ' . number_format($event['price'], thousands_separator: '.') }}
                        </span>
                    </div>

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
