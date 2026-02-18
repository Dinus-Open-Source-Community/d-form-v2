<x-layout::dashboard title="All events">
    <div class="container mx-auto">
        <x-core::breadcrumb.wrapper>
            <x-core::breadcrumb.link :href="route('dashboard.home')">Dashboard</x-core::breadcrumb.link>
            <x-core::breadcrumb.item>Events</x-core::breadcrumb.item>
        </x-core::breadcrumb.wrapper>

        <h1 class="mb-3 block text-3xl font-bold lg:hidden">All events</h1>

        @livewire('event.list-page')
    </div>
</x-layout::dashboard>
