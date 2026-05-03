<script setup lang="ts">
import { ref, onMounted, onUnmounted, computed } from 'vue';

const scrolled = ref(false);
const mobileMenuOpen = ref(false);

const currentPath = computed(() => window.location.pathname);

const handleScroll = () => {
    scrolled.value = window.scrollY > 20;
};

onMounted(() => window.addEventListener('scroll', handleScroll));
onUnmounted(() => window.removeEventListener('scroll', handleScroll));

const navLinks = [
    { label: 'Home', href: '/' },
    { label: 'Features', href: '/features' },
    { label: 'Event', href: '/events' },
    { label: 'Docs', href: '/docs' },
];
</script>

<template>
    <nav
        :class="[
            'fixed top-0 right-0 left-0 z-50 transition-all duration-300',
            scrolled
                ? 'border-b-[1.5px] border-[var(--brutal-ink)] bg-white/95 shadow-[var(--brutal-shadow-sm)] backdrop-blur-lg'
                : 'bg-white/70 backdrop-blur-sm',
        ]"
    >
        <div class="mx-auto flex max-w-7xl items-center justify-between px-6 py-3.5 lg:px-8">
            <!-- Logo -->
            <a href="/" class="group flex items-center gap-2.5">
                <div
                    class="flex h-9 w-9 items-center justify-center rounded-lg border-[1.5px] border-[var(--brutal-ink)] bg-[var(--brutal-blue)] text-white shadow-[var(--brutal-shadow-sm)] transition-transform group-hover:-rotate-3"
                >
                    <svg
                        width="18"
                        height="18"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="white"
                        stroke-width="2.5"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                    >
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z" />
                        <path d="M14 2v6h6" />
                        <path d="M16 13H8" />
                        <path d="M16 17H8" />
                        <path d="M10 9H8" />
                    </svg>
                </div>
                <span class="font-display text-xl font-bold tracking-tight text-[var(--brutal-ink)]">
                    D<span class="text-[var(--brutal-blue)]">Form</span>
                </span>
            </a>

            <!-- Desktop Nav -->
            <div class="hidden items-center gap-1 md:flex">
                <a
                    v-for="link in navLinks"
                    :key="link.href"
                    :href="link.href"
                    :class="[
                        'rounded-lg border-[1.5px] px-3.5 py-2 text-sm font-bold transition-all duration-150',
                        currentPath === link.href
                            ? 'border-[var(--brutal-ink)] bg-[var(--brutal-yellow)] text-[var(--brutal-ink)] shadow-[var(--brutal-shadow-sm)]'
                            : 'border-transparent text-[var(--brutal-ink)]/70 hover:border-[var(--brutal-ink)] hover:bg-[var(--brutal-mint)]/10 hover:text-[var(--brutal-ink)] hover:shadow-[var(--brutal-shadow-sm)]',
                    ]"
                >
                    {{ link.label }}
                </a>
            </div>

            <!-- CTA -->
            <div class="hidden items-center gap-2.5 md:flex">
                <a
                    href="/auth/login"
                    class="rounded-lg px-3.5 py-2 text-sm font-bold text-[var(--brutal-ink)]/70 transition-all hover:text-[var(--brutal-ink)]"
                >
                    Sign In
                </a>
                <a
                    href="/auth/register"
                    class="rounded-lg border-[1.5px] border-[var(--brutal-ink)] bg-[var(--brutal-blue)] px-4.5 py-2 text-sm font-bold text-white shadow-[var(--brutal-shadow-sm)] transition-all duration-150 hover:-translate-y-0.5 hover:shadow-[var(--brutal-shadow)] active:translate-y-0 active:shadow-[1px_1px_0_var(--brutal-ink)]"
                >
                    Get Started
                </a>
            </div>

            <!-- Mobile menu button -->
            <button
                class="flex h-9 w-9 items-center justify-center rounded-lg border-[1.5px] border-[var(--brutal-ink)] bg-white shadow-[var(--brutal-shadow-sm)] transition-all active:translate-y-0.5 active:shadow-none md:hidden"
                @click="mobileMenuOpen = !mobileMenuOpen"
            >
                <svg
                    v-if="!mobileMenuOpen"
                    width="20"
                    height="20"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                    stroke-linecap="round"
                >
                    <line x1="3" y1="6" x2="21" y2="6" />
                    <line x1="3" y1="12" x2="21" y2="12" />
                    <line x1="3" y1="18" x2="21" y2="18" />
                </svg>
                <svg
                    v-else
                    width="20"
                    height="20"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                    stroke-linecap="round"
                >
                    <line x1="18" y1="6" x2="6" y2="18" />
                    <line x1="6" y1="6" x2="18" y2="18" />
                </svg>
            </button>
        </div>

        <!-- Mobile menu -->
        <transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="opacity-0 -translate-y-2"
            enter-to-class="opacity-100 translate-y-0"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="opacity-100 translate-y-0"
            leave-to-class="opacity-0 -translate-y-2"
        >
            <div v-if="mobileMenuOpen" class="border-t-[1.5px] border-[var(--brutal-ink)] bg-white px-6 py-4 shadow-[0_4px_0_var(--brutal-ink)] md:hidden">
                <div class="flex flex-col gap-1">
                    <a
                        v-for="link in navLinks"
                        :key="link.href"
                        :href="link.href"
                        :class="[
                            'rounded-lg border-[1.5px] px-3 py-2.5 text-sm font-bold transition-all',
                            currentPath === link.href
                                ? 'border-[var(--brutal-ink)] bg-[var(--brutal-yellow)] text-[var(--brutal-ink)]'
                                : 'border-transparent text-[var(--brutal-ink)] hover:border-[var(--brutal-ink)] hover:bg-[var(--brutal-mint)]/10',
                        ]"
                        @click="mobileMenuOpen = false"
                    >
                        {{ link.label }}
                    </a>
                    <hr class="my-2 border-[var(--brutal-ink)]/15" />
                    <a href="/auth" class="rounded-lg px-3 py-2.5 text-sm font-bold text-[var(--brutal-ink)]"
                        >Sign In</a
                    >
                    <a
                        href="/auth"
                        class="mt-1 rounded-lg border-[1.5px] border-[var(--brutal-ink)] bg-[var(--brutal-blue)] px-3 py-2.5 text-center text-sm font-bold text-white shadow-[var(--brutal-shadow-sm)]"
                        >Get Started</a
                    >
                </div>
            </div>
        </transition>
    </nav>
</template>
