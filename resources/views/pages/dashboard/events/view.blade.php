<x-layout::dashboard :title="$event['title']">
    <div class="container mx-auto">
        <x-core::breadcrumb.wrapper>
            <x-core::breadcrumb.link :href="route('dashboard.home')">Dashboard</x-core::breadcrumb.link>
            <x-core::breadcrumb.link :href="route('dashboard.events.index')">Events</x-core::breadcrumb.link>
            <x-core::breadcrumb.item>
                {{ $event['title'] }}
            </x-core::breadcrumb.item>
        </x-core::breadcrumb.wrapper>

        {{--
            <h1 class="mb-3 block text-3xl font-bold lg:hidden">
            {{ $event['title'] }}
            </h1>
        --}}

        @livewire(
            'event.event-detail',
            [
                'event' => $event,
            ]
        )
    </div>
</x-layout::dashboard>
