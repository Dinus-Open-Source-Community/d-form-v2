<x-layout::dashboard title="halo">
    <div class="container mx-auto">
        <x-breadcrumb.wrapper>
            <x-breadcrumb.item>Dashboard</x-breadcrumb.item>
            <x-breadcrumb.item>Home</x-breadcrumb.item>
        </x-breadcrumb.wrapper>

        <h1>Hello {{ auth()->guard()->user()->name }}</h1>
    </div>
</x-layout::dashboard>
