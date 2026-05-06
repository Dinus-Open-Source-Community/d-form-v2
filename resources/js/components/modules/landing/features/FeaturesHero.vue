<script setup lang="ts">
import { ref, onMounted } from 'vue'
import LocalLottie from '@/components/core/LocalLottie.vue'

const visible = ref(false)
onMounted(() => setTimeout(() => (visible.value = true), 100))
</script>

<template>
    <section class="relative overflow-hidden bg-background pt-32 pb-20 lg:pt-40 lg:pb-24">
        <div class="app-grid pointer-events-none absolute inset-0 opacity-40"></div>
        <div class="pointer-events-none absolute left-1/2 top-28 h-80 w-80 -translate-x-1/2 rounded-full bg-primary/10 blur-3xl"></div>

        <div class="relative mx-auto grid max-w-7xl items-center gap-12 px-6 lg:grid-cols-[0.95fr_1.05fr] lg:px-8">
            <div
                :class="[
                    'w-full max-w-3xl transition-[opacity,transform] duration-700 ease-[cubic-bezier(0.22,1,0.36,1)]',
                    visible ? 'translate-y-0 opacity-100' : 'translate-y-8 opacity-0',
                ]"
            >
                <div class="app-kicker mb-6">Feature suite</div>
                <h1 class="font-display text-balance text-[2.6rem] font-bold leading-[1] tracking-[-0.04em] text-foreground sm:text-5xl lg:text-6xl">
                    Every tool you need,
                    <span class="relative inline-block text-primary">
                        one platform.
                        <span class="feat-underline absolute -bottom-1 left-0 h-2 w-full rounded-full bg-primary/15"></span>
                    </span>
                </h1>

                <p class="mt-6 max-w-2xl text-base leading-relaxed text-muted-foreground lg:text-lg">
                    From form building to real-time analytics, DForm gives event organizers everything they need to
                    deliver clear workflows and better registration experiences.
                </p>
            </div>

            <div
                :class="[
                    'w-full transition-[opacity,transform] delay-300 duration-700 ease-[cubic-bezier(0.22,1,0.36,1)]',
                    visible ? 'translate-y-0 opacity-100' : 'translate-y-12 opacity-0',
                ]"
            >
                <div class="app-surface overflow-hidden p-6">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="rounded-2xl border border-border bg-card p-5 shadow-xs">
                            <p class="text-[10px] font-semibold uppercase tracking-[0.18em] text-muted-foreground">Builder</p>
                            <p class="font-display mt-2 text-xl font-bold tracking-[-0.02em]">Drag. Preview. Publish.</p>
                        </div>
                        <div class="rounded-2xl border border-primary/20 bg-primary/8 p-5 shadow-xs">
                            <p class="text-[10px] font-semibold uppercase tracking-[0.18em] text-primary">Analytics</p>
                            <p class="font-display mt-2 text-xl font-bold tracking-[-0.02em]">89% completion</p>
                        </div>
                    </div>
                    <LocalLottie name="featureModules" :height="240" width="100%" :lazy="false" />
                </div>
            </div>
        </div>
    </section>
</template>

<style scoped>
.feat-underline {
    animation: underlineGrow 0.9s cubic-bezier(0.22, 1, 0.36, 1) 0.7s both;
    transform-origin: left;
}
@keyframes underlineGrow {
    from { transform: scaleX(0); }
    to { transform: scaleX(1); }
}
</style>
