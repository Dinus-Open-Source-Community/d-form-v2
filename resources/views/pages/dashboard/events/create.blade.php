<x-layout::dashboard title="Create events">
    <div class="container mx-auto">
        <x-core::breadcrumb.wrapper>
            <x-core::breadcrumb.link :href="route('dashboard.home')">Dashboard</x-core::breadcrumb.link>
            <x-core::breadcrumb.link :href="route('dashboard.events.index')">Events</x-core::breadcrumb.link>
            <x-core::breadcrumb.item>Create</x-core::breadcrumb.item>
        </x-core::breadcrumb.wrapper>

        <h1 class="mb-3 block text-3xl font-bold lg:hidden">Create events</h1>

        @livewire('event.create-form')
    </div>
</x-layout::dashboard>
