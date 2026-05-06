<script setup lang="ts">
import { onMounted, ref } from 'vue'
import LocalLottie from '@/components/core/LocalLottie.vue'

const visible = ref(false)

onMounted(() => {
    setTimeout(() => (visible.value = true), 100)
})
</script>

<template>
    <section class="relative overflow-hidden bg-background pt-32 pb-24 lg:pt-40 lg:pb-28">
        <div class="app-grid pointer-events-none absolute inset-0 opacity-40"></div>
        <div class="pointer-events-none absolute left-1/2 top-20 h-80 w-80 -translate-x-1/2 rounded-full bg-primary/10 blur-3xl"></div>

        <div class="relative mx-auto grid max-w-7xl items-center gap-12 px-6 lg:grid-cols-[0.95fr_1.05fr] lg:px-8">
            <div
                :class="[
                    'transition-all duration-700 ease-[cubic-bezier(0.22,1,0.36,1)]',
                    visible ? 'translate-y-0 opacity-100' : 'translate-y-8 opacity-0',
                ]"
            >
                <div class="app-kicker mb-6">Community events</div>
                <h1 class="font-display text-balance text-[2.6rem] font-bold leading-[1] tracking-[-0.04em] text-foreground sm:text-5xl lg:text-6xl">
                    Discover what's
                    <span class="text-primary">happening next.</span>
                </h1>
                <p class="mt-6 max-w-2xl text-base leading-relaxed text-muted-foreground lg:text-lg">
                    Browse curated events from the community, register instantly, and never miss a chance to learn,
                    build, or connect.
                </p>
            </div>

            <div
                :class="[
                    'transition-all delay-200 duration-700 ease-[cubic-bezier(0.22,1,0.36,1)]',
                    visible ? 'translate-y-0 opacity-100' : 'translate-y-12 opacity-0',
                ]"
            >
                <div class="app-surface overflow-hidden p-6">
                    <div class="mb-5 flex items-center justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-[10px] font-semibold uppercase tracking-[0.18em] text-muted-foreground">Event discovery</p>
                            <p class="font-display mt-1 text-2xl font-bold tracking-[-0.02em]">Open registrations</p>
                        </div>
                        <span class="inline-flex items-center gap-1.5 rounded-full border border-primary/20 bg-primary/10 px-2.5 py-1 text-[11px] font-semibold text-primary">
                            <span class="size-1.5 rounded-full bg-primary"></span>
                            Updated live
                        </span>
                    </div>
                    <LocalLottie name="eventsLive" :height="280" width="100%" :lazy="false" />
                </div>
            </div>
        </div>
    </section>
</template>
