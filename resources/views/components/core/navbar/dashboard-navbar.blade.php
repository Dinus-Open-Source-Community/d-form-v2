@props([
    'title' => '',
])

<div class="navbar bg-base-100 sticky top-0 z-10 h-20 shadow-sm">
    <div class="container mx-auto flex items-center">
        <div class="hidden flex-none">
            <button class="btn btn-square btn-ghost">
                <svg
                    xmlns="http://www.w3.org/2000/svg"
                    fill="none"
                    viewBox="0 0 24 24"
                    class="inline-block h-5 w-5 stroke-current"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M4 6h16M4 12h16M4 18h16"
                    ></path>
                </svg>
            </button>
        </div>

        <div class="flex-1">
            <h3 class="text-2xl font-bold">
                <span class="hidden lg:inline">
                    {{ $title }}
                </span>

                <span class="inline lg:hidden">DForm</span>
            </h3>
        </div>

        <div class="flex-none">
            <x-utilities.theme-toggler />
        </div>
    </div>
</div>
