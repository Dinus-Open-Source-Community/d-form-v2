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
            'fixed top-0 right-0 left-0 z-50 transition-[background-color,border-color,backdrop-filter] duration-300 ease-[cubic-bezier(0.22,1,0.36,1)]',
            scrolled
                ? 'border-b border-border/80 bg-background/85 backdrop-blur-xl'
                : 'border-b border-transparent bg-background/55 backdrop-blur-md',
        ]"
    >
        <div class="mx-auto flex max-w-7xl items-center justify-between px-6 py-3.5 lg:px-8">
            <!-- Logo -->
            <a href="/" class="group flex items-center gap-2.5">
                <div
                    class="flex size-9 items-center justify-center rounded-xl border border-primary/15 bg-primary text-xs font-semibold text-primary-foreground shadow-sm transition-transform group-hover:-translate-y-px"
                >
                    DF
                </div>
                <span class="font-display text-xl font-semibold tracking-tight text-foreground">
                    D<span class="text-primary">Form</span>
                </span>
            </a>

            <!-- Desktop Nav -->
            <div class="hidden items-center gap-1 md:flex">
                <a
                    v-for="link in navLinks"
                    :key="link.href"
                    :href="link.href"
                    :class="[
                        'rounded-xl border px-3.5 py-2 text-sm font-medium transition-all duration-200',
                        currentPath === link.href
                            ? 'border-primary/15 bg-primary/10 text-primary shadow-xs'
                            : 'border-transparent text-muted-foreground hover:border-border hover:bg-accent hover:text-foreground',
                    ]"
                >
                    {{ link.label }}
                </a>
            </div>

            <!-- CTA -->
            <div class="hidden items-center gap-2.5 md:flex">
                <a
                    href="/auth/login"
                    class="rounded-lg px-3.5 py-2 text-sm font-medium text-muted-foreground transition-all hover:text-foreground"
                >
                    Sign In
                </a>
                <a
                    href="/auth/register"
                    class="rounded-xl border border-primary/10 bg-primary px-4.5 py-2 text-sm font-semibold text-primary-foreground shadow-sm transition-all duration-200 hover:-translate-y-px hover:bg-primary/90 active:scale-[0.98]"
                >
                    Get Started
                </a>
            </div>

            <!-- Mobile menu button -->
            <button
                class="flex size-9 flex-col items-center justify-center gap-1 rounded-xl border border-border bg-card shadow-xs transition-[transform,border-color] duration-200 ease-[cubic-bezier(0.22,1,0.36,1)] hover:border-primary/30 active:scale-[0.96] md:hidden"
                aria-label="Toggle menu"
                @click="mobileMenuOpen = !mobileMenuOpen"
            >
                <span :class="['h-0.5 w-4 rounded-full bg-foreground transition-transform', mobileMenuOpen ? 'translate-y-1.5 rotate-45' : '']"></span>
                <span :class="['h-0.5 w-4 rounded-full bg-foreground transition-opacity', mobileMenuOpen ? 'opacity-0' : '']"></span>
                <span :class="['h-0.5 w-4 rounded-full bg-foreground transition-transform', mobileMenuOpen ? '-translate-y-1.5 -rotate-45' : '']"></span>
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
            <div v-if="mobileMenuOpen" class="border-t border-border bg-card/95 px-6 py-4 backdrop-blur-xl md:hidden">
                <div class="flex flex-col gap-1">
                    <a
                        v-for="link in navLinks"
                        :key="link.href"
                        :href="link.href"
                        :class="[
                            'rounded-xl border px-3 py-2.5 text-sm font-medium transition-all',
                            currentPath === link.href
                                ? 'border-primary/15 bg-primary/10 text-primary'
                                : 'border-transparent text-muted-foreground hover:border-border hover:bg-accent hover:text-foreground',
                        ]"
                        @click="mobileMenuOpen = false"
                    >
                        {{ link.label }}
                    </a>
                    <hr class="my-2 border-border" />
                    <a href="/auth" class="rounded-lg px-3 py-2.5 text-sm font-medium text-foreground"
                        >Sign In</a
                    >
                    <a
                        href="/auth"
                        class="mt-1 rounded-xl border border-primary/10 bg-primary px-3 py-2.5 text-center text-sm font-semibold text-primary-foreground shadow-sm"
                        >Get Started</a
                    >
                </div>
            </div>
        </transition>
    </nav>
</template>
