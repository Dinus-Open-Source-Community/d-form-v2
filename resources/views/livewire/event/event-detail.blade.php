<div class="flex grid-cols-6 grid-rows-2 flex-wrap gap-8 md:grid md:grid-rows-1 md:gap-4">
    <div class="md:col-span-5 lg:col-span-4">
        {{ $this->eventDetailInfolist }}
    </div>

    <div class="top-24 flex h-fit w-full flex-col gap-4 md:sticky md:col-span-1 lg:col-span-2">
        <x-module::event.show.buttons-box :event="$event" />

        <x-module::event.show.forms-box :forms="$forms" :event="$event" />

        <x-module::event.show.schedules-box />
    </div>

    <x-filament-actions::modals />
</div>
