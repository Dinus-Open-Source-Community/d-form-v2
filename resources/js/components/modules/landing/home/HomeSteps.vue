<script setup lang="ts">
import { ref, onMounted } from 'vue'
import LocalLottie from '@/components/core/LocalLottie.vue'
import type { LottieName } from '@/lib/lotties'

interface HomeStep {
    readonly title: string
    readonly body: string
    readonly lottie: LottieName
}

const steps: readonly HomeStep[] = [
    { title: 'Create Event', body: 'Set up your event with name, date, and capacity in under 2 minutes.', lottie: 'stepsFlow' },
    { title: 'Build Form', body: 'Design registration forms with our drag-and-drop builder.', lottie: 'featureModules' },
    { title: 'Go Live', body: 'Publish and start collecting responses instantly.', lottie: 'heroProduct' },
]

const visible = ref(false)
onMounted(() => {
    const observer = new IntersectionObserver(
        ([entry]) => {
            if (entry.isIntersecting) visible.value = true
        },
        { threshold: 0.1 },
    )
    const el = document.getElementById('steps-section')
    if (el) observer.observe(el)
})
</script>

<template>
    <section id="steps-section" class="relative overflow-hidden border-y border-border bg-card/60 py-20 lg:py-28">
        <div class="app-grid pointer-events-none absolute inset-0 opacity-40"></div>
        <div class="mx-auto max-w-7xl px-6 lg:px-8">
            <div :class="['mx-auto max-w-2xl text-center transition-all duration-700', visible ? 'translate-y-0 opacity-100' : 'translate-y-8 opacity-0']">
                <h2 class="font-display text-3xl font-semibold tracking-[-0.04em] text-foreground sm:text-4xl lg:text-5xl">
                    Three steps to <span class="relative inline-block">
                        <span class="text-primary">launch</span>
                        <span class="absolute -bottom-1 left-0 -z-10 h-2.5 w-full rounded-full bg-primary/12"></span>
                    </span>
                </h2>
                <p class="mt-3 text-base text-muted-foreground">From idea to live event in minutes.</p>
            </div>

            <div :class="['mt-14 transition-all duration-700', visible ? 'translate-y-0 opacity-100' : 'translate-y-8 opacity-0']" style="transition-delay: 200ms;">
                <div class="grid gap-6 lg:grid-cols-3">
                    <div
                        v-for="(step, idx) in steps"
                        :key="step.title"
                        class="app-surface group relative overflow-hidden p-6"
                        :style="{ transitionDelay: `${idx * 120}ms` }"
                    >
                        <div class="mb-4 flex items-center gap-3">
                            <span class="grid size-9 place-items-center rounded-lg border border-primary/20 bg-primary/10 text-xs font-semibold text-primary">
                                {{ String(idx + 1).padStart(2, '0') }}
                            </span>
                            <h3 class="font-display text-lg font-bold tracking-[-0.02em] text-foreground">{{ step.title }}</h3>
                        </div>
                        <p class="mb-5 text-sm leading-relaxed text-muted-foreground">{{ step.body }}</p>
                        <div class="rounded-2xl border border-border bg-muted/30 p-4">
                            <LocalLottie :name="step.lottie" :height="180" width="100%" />
                        </div>
                        <span
                            v-if="idx < steps.length - 1"
                            aria-hidden="true"
                            class="pointer-events-none absolute -right-3 top-1/2 hidden size-6 -translate-y-1/2 rounded-full border border-border bg-card lg:block"
                        ></span>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>

