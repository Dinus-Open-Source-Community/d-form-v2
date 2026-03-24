<x-layout::dashboard :title="$form['title']">
    <div class="container mx-auto">
        <x-core::breadcrumb.wrapper>
            <x-core::breadcrumb.link :href="route('dashboard.home')">Dashboard</x-core::breadcrumb.link>
            <x-core::breadcrumb.link :href="route('dashboard.events.index')">Events</x-core::breadcrumb.link>
            <x-core::breadcrumb.item>
                {{ $event['title'] }}
            </x-core::breadcrumb.item>
        </x-core::breadcrumb.wrapper>

        @livewire(
            'event.form.form-detail',
            [
                'event' => $event,
                'form' => $form,
            ]
        )
    </div>
</x-layout::dashboard>
