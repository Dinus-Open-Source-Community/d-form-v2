<script setup lang="ts">
import { ref, onMounted } from 'vue';
import LocalLottie from '@/components/core/LocalLottie.vue';

const visible = ref(false);
onMounted(() => setTimeout(() => (visible.value = true), 100));
</script>

<template>
    <section class="relative overflow-hidden pt-32 pb-20 lg:pt-40 lg:pb-28">
        <div class="app-grid pointer-events-none absolute inset-0 opacity-40"></div>
        <div class="pointer-events-none absolute right-0 top-20 h-80 w-80 rounded-full bg-primary/10 blur-3xl"></div>
        <div class="pointer-events-none absolute bottom-10 left-0 hidden h-64 w-64 rounded-full bg-sky-200/40 blur-3xl lg:block"></div>

        <div class="relative mx-auto grid max-w-7xl items-center gap-14 px-6 lg:grid-cols-[1.04fr_0.96fr] lg:px-8">
            <div
                :class="[
                    'transition-all duration-700 ease-[cubic-bezier(0.22,1,0.36,1)]',
                    visible ? 'translate-y-0 opacity-100' : 'translate-y-8 opacity-0',
                ]"
            >
                <div
                    class="app-kicker mb-6"
                >
                    Live event workspace
                </div>

                <h1
                    class="font-display text-balance max-w-4xl text-[2.8rem] leading-[0.98] font-semibold tracking-[-0.055em] text-foreground sm:text-6xl lg:text-7xl"
                >
                    Build forms that move work
                    <span class="relative inline-block text-primary">
                        forward.
                        <span class="hero-underline absolute -bottom-1 left-0 h-2 w-full rounded-full bg-primary/15"></span>
                    </span>
                </h1>

                <p class="mt-6 max-w-2xl text-lg leading-relaxed text-muted-foreground">
                    Create registration flows, manage attendees, and read live activity from one calm dashboard built
                    for organizers who move fast.
                </p>

                <div class="mt-9 flex flex-wrap items-center gap-3">
                    <a
                        href="/auth"
                        class="inline-flex items-center gap-2 rounded-xl border border-primary/15 bg-primary px-6 py-3 text-sm font-semibold text-primary-foreground shadow-sm transition-[background-color,transform,box-shadow] duration-200 ease-[cubic-bezier(0.22,1,0.36,1)] hover:-translate-y-px hover:bg-primary/92 active:scale-[0.98]"
                    >
                        Get Started Free
                    </a>
                    <a
                        href="/features"
                        class="inline-flex items-center gap-2 rounded-xl border border-border bg-card px-6 py-3 text-sm font-semibold text-foreground shadow-xs transition-[border-color,background-color,transform] duration-200 ease-[cubic-bezier(0.22,1,0.36,1)] hover:-translate-y-px hover:border-primary/30 hover:bg-accent active:scale-[0.98]"
                    >
                        See How It Works
                    </a>
                </div>

                <div class="mt-8 flex flex-wrap gap-2 text-xs font-medium text-muted-foreground">
                    <span class="rounded-full border border-border bg-card px-3 py-1.5">No credit card</span>
                    <span class="rounded-full border border-primary/20 bg-primary/8 px-3 py-1.5 text-primary">Live analytics</span>
                    <span class="rounded-full border border-border bg-card px-3 py-1.5">DOSCOM made</span>
                </div>
            </div>

            <div
                :class="[
                    'relative transition-all delay-200 duration-700 ease-[cubic-bezier(0.22,1,0.36,1)]',
                    visible ? 'translate-y-0 opacity-100' : 'translate-y-12 opacity-0',
                ]"
            >
                <div class="absolute -bottom-4 left-6 z-10 inline-flex items-center gap-2 rounded-2xl border border-border bg-card/95 px-4 py-2.5 text-sm font-semibold shadow-sm backdrop-blur">
                    <span class="size-2 rounded-full bg-success animate-pulse"></span>
                    <span><span class="text-primary">342</span> people joined today</span>
                </div>
                <div class="app-surface relative overflow-hidden p-6 sm:p-8">
                    <div class="mb-5 flex items-center justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-[10px] font-semibold uppercase tracking-[0.18em] text-muted-foreground">Workspace</p>
                            <p class="font-display mt-1 text-2xl font-bold tracking-[-0.02em]">Registration Flow</p>
                        </div>
                        <span class="inline-flex items-center gap-1.5 rounded-full border border-primary/20 bg-primary/10 px-2.5 py-1 text-[11px] font-semibold text-primary">
                            <span class="size-1.5 rounded-full bg-primary"></span>
                            Live
                        </span>
                    </div>
                    <LocalLottie name="heroProduct" :height="280" width="100%" :lazy="false" />
                    <div class="mt-6 grid gap-3 sm:grid-cols-3">
                        <div class="rounded-xl border border-border bg-card p-4 shadow-xs">
                            <p class="font-display text-2xl font-bold tracking-[-0.02em]">86%</p>
                            <p class="mt-1 text-xs text-muted-foreground">Completion</p>
                        </div>
                        <div class="rounded-xl border border-border bg-card p-4 shadow-xs">
                            <p class="font-display text-2xl font-bold tracking-[-0.02em]">1.2k</p>
                            <p class="mt-1 text-xs text-muted-foreground">Responses</p>
                        </div>
                        <div class="rounded-xl border border-border bg-card p-4 shadow-xs">
                            <p class="font-display text-2xl font-bold tracking-[-0.02em]">14m</p>
                            <p class="mt-1 text-xs text-muted-foreground">Setup time</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>

<style scoped>
.hero-underline {
    z-index: -1;
    animation: underlineGrow 0.75s cubic-bezier(0.22, 1, 0.36, 1) 0.45s both;
    opacity: 0.28;
}
@keyframes underlineGrow {
    from {
        transform: scaleX(0);
        transform-origin: left;
    }
    to {
        transform: scaleX(1);
        transform-origin: left;
    }
}
</style>
