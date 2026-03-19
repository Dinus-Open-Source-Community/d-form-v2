<div class="my-6 grid grid-cols-1">
    @empty($this->events)
        <div class="col-span-3 flex flex-col items-center pt-12 md:pt-20">
            @svg('heroicon-o-exclamation-triangle', 'text-base-content size-12 md:size-16 lg:size-24')
            <h4 class="text-base-content text-3xl md:text-4xl">
                {{ __('There is no content.') }}
            </h4>
        </div>
    @else
        {{ $this->eventsInfolist }}
    @endempty

    <x-filament-actions::modals />
</div>
