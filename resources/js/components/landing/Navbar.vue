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
            'fixed top-0 left-0 right-0 z-50 transition-all duration-300',
            scrolled
                ? 'bg-white/95 backdrop-blur-md shadow-[0_1px_3px_rgba(0,0,0,0.04)] border-b border-[#F3F4F6]'
                : 'bg-white/80 backdrop-blur-sm',
        ]"
    >
        <div class="mx-auto flex max-w-7xl items-center justify-between px-6 py-4 lg:px-8">
            <!-- Logo -->
            <a href="/" class="flex items-center gap-2.5">
                <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-[#0A84DC]">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z" />
                        <path d="M14 2v6h6" />
                        <path d="M16 13H8" />
                        <path d="M16 17H8" />
                        <path d="M10 9H8" />
                    </svg>
                </div>
                <span class="text-xl font-bold tracking-tight text-[#111827]">
                    D<span class="text-[#0A84DC]">Form</span>
                </span>
            </a>

            <!-- Desktop Nav -->
            <div class="hidden items-center gap-1 md:flex">
                <a
                    v-for="link in navLinks"
                    :key="link.href"
                    :href="link.href"
                    :class="[
                        'rounded-lg px-4 py-2 text-sm font-medium transition-all duration-200',
                        currentPath === link.href
                            ? 'bg-[#0A84DC]/8 text-[#0A84DC]'
                            : 'text-[#6B7280] hover:bg-[#F9FAFB] hover:text-[#111827]',
                    ]"
                >
                    {{ link.label }}
                </a>
            </div>

            <!-- CTA -->
            <div class="hidden items-center gap-3 md:flex">
                <a href="/auth" class="text-sm font-semibold text-[#6B7280] transition-colors hover:text-[#111827]">
                    Sign In
                </a>
                <a
                    href="/auth"
                    class="rounded-lg bg-[#0A84DC] px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition-all duration-200 hover:bg-[#0972c0] hover:shadow-md active:scale-[0.97]"
                >
                    Get Started
                </a>
            </div>

            <!-- Mobile menu button -->
            <button
                class="flex h-10 w-10 items-center justify-center rounded-lg transition-colors hover:bg-[#F9FAFB] md:hidden"
                @click="mobileMenuOpen = !mobileMenuOpen"
            >
                <svg v-if="!mobileMenuOpen" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                    <line x1="3" y1="6" x2="21" y2="6" />
                    <line x1="3" y1="12" x2="21" y2="12" />
                    <line x1="3" y1="18" x2="21" y2="18" />
                </svg>
                <svg v-else width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
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
            <div v-if="mobileMenuOpen" class="border-t border-[#F3F4F6] bg-white px-6 py-4 shadow-lg md:hidden">
                <div class="flex flex-col gap-1">
                    <a
                        v-for="link in navLinks"
                        :key="link.href"
                        :href="link.href"
                        :class="[
                            'rounded-lg px-3 py-2.5 text-sm font-medium transition-colors',
                            currentPath === link.href
                                ? 'bg-[#0A84DC]/8 text-[#0A84DC]'
                                : 'text-[#111827] hover:bg-[#F9FAFB]',
                        ]"
                        @click="mobileMenuOpen = false"
                    >
                        {{ link.label }}
                    </a>
                    <hr class="my-2 border-[#F3F4F6]" />
                    <a href="/auth" class="rounded-lg px-3 py-2.5 text-sm font-medium text-[#111827] hover:bg-[#F9FAFB]">Sign In</a>
                    <a href="/auth" class="mt-1 rounded-lg bg-[#0A84DC] px-3 py-2.5 text-center text-sm font-semibold text-white hover:bg-[#0972c0]">Get Started</a>
                </div>
            </div>
        </transition>
    </nav>
</template>
