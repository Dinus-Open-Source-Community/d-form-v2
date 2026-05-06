<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { Check, Minus } from 'lucide-vue-next'
import type { ComparisonRow } from '@/types/landing'

const visible = ref(false)
onMounted(() => {
    const observer = new IntersectionObserver(
        ([entry]) => {
            if (entry.isIntersecting) visible.value = true
        },
        { threshold: 0.1 },
    )
    const el = document.getElementById('feat-compare')
    if (el) observer.observe(el)
})

const rows: readonly ComparisonRow[] = [
    { feature: 'Unlimited Events', dform: true, competitor: false },
    { feature: 'Drag & Drop Form Builder', dform: true, competitor: true },
    { feature: 'Real-time Analytics', dform: true, competitor: false },
    { feature: 'Team Collaboration', dform: true, competitor: false },
    { feature: 'Webhooks & API', dform: true, competitor: true },
    { feature: 'Custom Branding', dform: true, competitor: false },
    { feature: 'GDPR Compliance', dform: true, competitor: true },
    { feature: 'Free Tier Available', dform: true, competitor: false },
]

function isYes(value: boolean | string): boolean {
    return value === true
}
</script>

<template>
    <section id="feat-compare" class="bg-background py-24 lg:py-32">
        <div class="mx-auto max-w-4xl px-6 lg:px-8">
            <div :class="['mx-auto max-w-2xl text-center transition-[opacity,transform] duration-700 ease-[cubic-bezier(0.22,1,0.36,1)]', visible ? 'translate-y-0 opacity-100' : 'translate-y-8 opacity-0']">
                <h2 class="font-display text-3xl font-bold tracking-[-0.035em] text-foreground sm:text-4xl lg:text-5xl">
                    Why choose <span class="text-primary">DForm?</span>
                </h2>
                <p class="mt-3 text-base leading-relaxed text-muted-foreground">See how we compare to other platforms.</p>
            </div>

            <div
                :class="[
                    'app-surface mt-12 overflow-hidden p-0 transition-[opacity,transform] duration-700 ease-[cubic-bezier(0.22,1,0.36,1)]',
                    visible ? 'translate-y-0 opacity-100' : 'translate-y-8 opacity-0',
                ]"
                style="transition-delay: 200ms"
            >
                <div class="grid grid-cols-3 border-b border-border bg-muted/40 px-6 py-3.5 text-[10px] font-semibold uppercase tracking-[0.16em] text-muted-foreground">
                    <span>Feature</span>
                    <span class="text-center text-primary">DForm</span>
                    <span class="text-center">Others</span>
                </div>
                <div class="divide-y divide-border">
                    <div
                        v-for="row in rows"
                        :key="row.feature"
                        class="grid grid-cols-3 items-center px-6 py-3.5 transition-colors hover:bg-primary/4"
                    >
                        <span class="text-sm font-medium text-foreground">{{ row.feature }}</span>
                        <span class="flex justify-center">
                            <Check v-if="isYes(row.dform)" class="size-4 text-success" :stroke-width="3" />
                            <Minus v-else class="size-4 text-muted-foreground/40" :stroke-width="2" />
                        </span>
                        <span class="flex justify-center">
                            <Check v-if="isYes(row.competitor)" class="size-4 text-success" :stroke-width="3" />
                            <Minus v-else class="size-4 text-muted-foreground/40" :stroke-width="2" />
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>
