<x-layouts.auth>
    <div class="grid h-dvh max-h-dvh w-full grid-cols-1 gap-4 p-0 lg:grid-cols-2">
        <x-auth.banners />

        <div class="relative max-h-dvh overflow-x-hidden overflow-y-auto pt-20 md:pt-32 xl:pt-40">
            <x-auth.utility-bar />

            <section class="flex flex-col items-center" x-data="{ mode: 'login' }">
                <picture>
                    <source srcset="/images/logo.webp" />
                    <img src="/images/logo.png" alt="logo doscom" class="mb-4 w-28 md:w-32" />
                </picture>

                <div
                    class="relative mb-3 h-[2em] w-[90%] max-w-md text-3xl sm:mb-4 sm:h-[1.75em] sm:w-[60%] sm:max-w-none sm:text-4xl md:w-[50%] lg:w-[70%] xl:w-[55%]"
                >
                    {{-- Heading for Login --}}
                    <h3
                        class="absolute w-full text-center text-[1em] font-bold"
                        x-show="mode == 'login'"
                        x-transition:enter="transition delay-300 duration-300 ease-out"
                        x-transition:enter-start="translate-x-[-100px] opacity-0"
                        x-transition:enter-end="translate-x-0 opacity-100"
                        x-transition:leave="transition duration-300 ease-in"
                        x-transition:leave-start="translate-x-0 opacity-100"
                        x-transition:leave-end="translate-x-[-100px] opacity-0"
                    >
                        {{ __('auth.sign_in') }}
                    </h3>
                    {{-- End Of Heading for Login --}}

                    {{-- Heading for Registration --}}
                    <h3
                        class="text-center text-[1em] font-bold"
                        x-show="mode == 'register'"
                        x-transition:enter="transition delay-300 duration-300 ease-out"
                        x-transition:enter-start="translate-x-[100px] opacity-0"
                        x-transition:enter-end="translate-x-0 opacity-100"
                        x-transition:leave="transition duration-300 ease-in"
                        x-transition:leave-start="translate-x-0 opacity-100"
                        x-transition:leave-end="translate-x-[100px] opacity-0"
                    >
                        {{ __('auth.sign_up') }}
                    </h3>
                    {{-- End of Heading for Registration --}}

                    {{-- Button for Login --}}
                    <button
                        class="text-md absolute bottom-0 left-0 inline-flex text-base"
                        x-on:click="mode = 'login'"
                        x-show="mode != 'login'"
                        x-transition:enter="transition delay-750 duration-300 ease-out"
                        x-transition:enter-start=" opacity-0"
                        x-transition:enter-end="opacity-100"
                        x-transition:leave="transition duration-300 ease-in"
                        x-transition:leave-start="opacity-100"
                        x-transition:leave-end="opacity-0"
                    >
                        @svg('heroicon-o-chevron-left', 'w-[1em]')
                        {{ __('auth.sign_in') }}
                    </button>
                    {{-- End of Button for Login --}}

                    {{-- Button for Registration --}}
                    <button
                        class="text-md absolute right-0 bottom-0 inline-flex text-base"
                        x-on:click="mode = 'register'"
                        x-show="mode != 'register'"
                        x-transition:enter="transition delay-750 duration-300 ease-out"
                        x-transition:enter-start="opacity-0"
                        x-transition:enter-end="opacity-100"
                        x-transition:leave="transition duration-300 ease-in"
                        x-transition:leave-start="opacity-100"
                        x-transition:leave-end="opacity-0"
                    >
                        {{ __('auth.sign_up') }}
                        @svg('heroicon-o-chevron-right', 'w-[1em]')
                    </button>
                    {{-- End of Button for Registration --}}
                </div>

                <div class="relative w-[90%] max-w-md sm:w-[60%] sm:max-w-none md:w-[50%] lg:w-[70%] xl:w-[55%]">
                    {{-- Login Form --}}
                    <div
                        class="absolute top-0 w-full pb-4"
                        x-show="mode == 'login'"
                        x-transition:enter="transition delay-300 duration-300 ease-out"
                        x-transition:enter-start="translate-x-[-100px] opacity-0"
                        x-transition:enter-end="translate-x-0 opacity-100"
                        x-transition:leave="transition duration-300 ease-in"
                        x-transition:leave-start="translate-x-0 opacity-100"
                        x-transition:leave-end="translate-x-[-100px] opacity-0"
                    >
                        @livewire('auth.login-form')

                        <footer class="block py-3 pt-12 text-sm md:hidden">
                            <p class="text-neutral text-center">Created by Dinus Open Source Community</p>
                        </footer>
                    </div>
                    {{-- End of Login Form --}}

                    {{-- Registration Form --}}
                    <div
                        class="absolute top-0 w-full pb-4"
                        x-show="mode == 'register'"
                        x-transition:enter="transition delay-300 duration-300 ease-out"
                        x-transition:enter-start="translate-x-[100px] opacity-0"
                        x-transition:enter-end="translate-x-0 opacity-100"
                        x-transition:leave="transition duration-300 ease-in"
                        x-transition:leave-start="translate-x-0 opacity-100"
                        x-transition:leave-end="translate-x-[100px] opacity-0"
                    >
                        @livewire('auth.register-form')

                        <footer class="mt-12 block pb-8 text-sm md:hidden">
                            <p class="text-neutral text-center">Created by Dinus Open Source Community</p>
                        </footer>
                    </div>
                    {{-- End of Registration Form --}}
                </div>
            </section>
        </div>
    </div>
</x-layouts.auth>
