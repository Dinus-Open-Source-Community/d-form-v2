<div class="bg-base-100 border-base-200 flex h-full w-[90vw] flex-col border-r lg:max-w-2xs">
    <section id="sidebar-header" class="flex h-20 items-center px-8 shadow-sm">
        <h2 class="text-base-content text-3xl font-bold">DForm</h2>
    </section>

    <section class="flex-1 px-2 py-2 shadow-sm">
        <div class="py-2">
            <h4 class="mb-2 text-sm font-bold text-slate-600 dark:text-slate-400">Main Menu</h4>
            <ul class="menu w-full p-0">
                <li class="">
                    <a href="/" class="text-primary-content bg-primary items-center py-2 text-sm font-semibold">
                        @svg('heroicon-o-home', 'size-[2em]')
                        Dashboard
                    </a>
                </li>
            </ul>
        </div>

        <div class="py-2">
            <h4 class="mb-2 text-sm font-bold text-slate-600 dark:text-slate-400">Event Management</h4>
            <ul class="menu w-full p-0">
                <li class="">
                    <a
                        href="/"
                        class="active:bg-base-300 py-2 text-sm font-semibold text-slate-700 transition active:scale-95 dark:text-slate-200"
                    >
                        @svg('heroicon-o-calendar', 'size-[2em]')
                        All events
                    </a>
                </li>

                @can('events.create')
                    <li class="">
                        <a href="/" class="py-2 text-sm font-semibold text-slate-700 dark:text-slate-200">
                            @svg('heroicon-o-bookmark', 'size-[2em]')
                            Joined events
                        </a>
                    </li>
                @endcan

                @can('events.create')
                    <li class="">
                        <a href="/" class="py-2 text-sm font-semibold text-slate-700 dark:text-slate-200">
                            @svg('heroicon-o-plus', 'size-[2em]')
                            New event
                        </a>
                    </li>
                @endcan

                @can('events.export')
                    <li class="">
                        <a href="/" class="py-2 text-sm font-semibold text-slate-700 dark:text-slate-200">
                            @svg('heroicon-o-cloud', 'size-[2em]')
                            asd
                        </a>
                    </li>
                @endcan
            </ul>
        </div>
    </section>

    <section class="flex flex-col gap-3 px-2 py-2">
        <div class="bg-base-300 flex gap-3 overflow-hidden rounded-lg px-4 py-4">
            <div class="aspect-square size-12 shrink-0 overflow-hidden rounded-full uppercase">
                @if (auth()->user()->avatar)
                    <img src="{{ asset('/users/avatar/' . auth()->user()->avatar) }}" alt="avatar of user" />
                @else
                    <div
                        class="bg-primary text-primary-content flex h-full w-full items-center justify-center text-2xl leading-0 font-bold"
                    >
                        {{ str(auth()->user()->name)->substr(0, 1) }}
                    </div>
                @endif
            </div>

            <div class="grid w-full grid-cols-1">
                <h4
                    title="{{ auth()->user()->name }}"
                    class="w-full overflow-hidden text-base font-semibold text-ellipsis whitespace-nowrap"
                >
                    {{ auth()->user()->name }}
                </h4>

                <h6 class="overflow-clip text-sm text-ellipsis">
                    @php
                        [$emailName, $domain] = str(auth()->user()->email)->explode('@');
                    @endphp

                    {{ str($emailName)->limit(10) . '@' . $domain }}
                </h6>
            </div>
        </div>

        <form action="{{ route('auth.logout') }}" method="post">
            @csrf
            <button class="btn btn-ghost btn-lg w-full text-center">Logout</button>
        </form>
    </section>
</div>
