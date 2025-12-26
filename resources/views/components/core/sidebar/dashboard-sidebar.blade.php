@php
    $linkGroups = [
        'Main Menu' => [
            [
                'label' => 'Dashboard',
                'href' => route('dashboard.home'),
                'isActive' => request()->routeIs('dashboard.home'),
                'icon' => 'heroicon-o-home',
            ],
        ],

        'Event Management' => [
            [
                'label' => 'All events',
                'href' => route('dashboard.events.index'),
                'isActive' => request()->routeIs('dashboard.events.*'),
                'icon' => 'heroicon-o-calendar',
            ],
        ],
    ];
@endphp

<div class="bg-base-100 border-base-200 flex h-full w-[90vw] flex-col border-r lg:max-w-2xs">
    <section id="sidebar-header" class="flex h-20 items-center px-8 shadow-sm">
        <h2 class="text-base-content text-3xl font-bold">
            <a href="/">DForm</a>
        </h2>
    </section>

    <section class="flex-1 px-2 py-2 shadow-sm">
        @foreach ($linkGroups as $title => $links)
            <div class="py-2">
                <h4 class="mb-2 text-sm font-bold text-slate-600 dark:text-slate-400">
                    {{ $title }}
                </h4>

                <ul class="menu w-full p-0">
                    @foreach ($links as $link)
                        <li class="">
                            <a
                                href="{{ $link['href'] }}"
                                @class([
                                    'items-center py-2 text-sm font-semibold transition active:scale-95',
                                    $link['isActive'] ? 'text-primary-content bg-primary' : 'text-base-content transparent active:bg-transparent',
                                ])
                            >
                                @svg($link['icon'], 'size-[2em]')
                                {{ $link['label'] }}
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endforeach
    </section>

    <section class="flex flex-col gap-3 px-2 py-2" id="sidebar-footer">
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

        {{-- Logout Button teleported from layouts/dashboard.blade.php --}}
    </section>
</div>
