<x-layout::dashboard title="halo">
    <div class="container mx-auto">
        <x-core::breadcrumb.wrapper>
            <x-core::breadcrumb.item>Dashboard</x-core::breadcrumb.item>
            <x-core::breadcrumb.item>Home</x-core::breadcrumb.item>
        </x-core::breadcrumb.wrapper>

        <h1>Hello {{ auth()->guard()->user()->name }}</h1>
    </div>
</x-layout::dashboard>
