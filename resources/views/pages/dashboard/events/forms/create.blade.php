<x-layout::dashboard title="Create form">
    <div class="container mx-auto">
        <x-core::breadcrumb.wrapper>
            <x-core::breadcrumb.link :href="route('dashboard.home')">Dashboard</x-core::breadcrumb.link>
            <x-core::breadcrumb.link :href="route('dashboard.events.index')">Events</x-core::breadcrumb.link>
            <x-core::breadcrumb.link :href="route('dashboard.events.show', ['event' => $event])">
                {{ str($event['title'])->ucfirst()->limit(10) }}
            </x-core::breadcrumb.link>
            <x-core::breadcrumb.item>Forms</x-core::breadcrumb.item>
            <x-core::breadcrumb.item>Create</x-core::breadcrumb.item>
        </x-core::breadcrumb.wrapper>

        <h1 class="mb-3 block text-3xl font-bold lg:hidden">Create form</h1>

        @livewire('event.form.create-form', ['event_id' => request()->route('event')])
    </div>
</x-layout::dashboard>
