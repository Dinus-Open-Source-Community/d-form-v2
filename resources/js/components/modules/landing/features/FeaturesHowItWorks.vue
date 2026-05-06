<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { ChevronRight } from 'lucide-vue-next'

interface HowStep {
    readonly num: string
    readonly title: string
    readonly description: string
}

const visible = ref(false)
onMounted(() => {
    const observer = new IntersectionObserver(
        ([entry]) => {
            if (entry.isIntersecting) visible.value = true
        },
        { threshold: 0.1 },
    )
    const el = document.getElementById('feat-how')
    if (el) observer.observe(el)
})

const steps: readonly HowStep[] = [
    { num: '01', title: 'Design Your Form', description: 'Use the drag-and-drop builder to add fields, set validations, and customize the look and feel.' },
    { num: '02', title: 'Publish & Share', description: 'Generate a shareable link or embed the form on your website. Go live in seconds.' },
    { num: '03', title: 'Analyze Results', description: 'View real-time responses, auto-generated charts, and export data whenever needed.' },
]
</script>

<template>
    <section id="feat-how" class="relative overflow-hidden bg-background py-20 lg:py-28">
        <div class="pointer-events-none absolute -left-16 top-12 h-72 w-72 rounded-full bg-primary/8 blur-3xl"></div>
        <div class="relative mx-auto max-w-7xl px-6 lg:px-8">
            <div :class="['mx-auto max-w-2xl text-center transition-[opacity,transform] duration-700 ease-[cubic-bezier(0.22,1,0.36,1)]', visible ? 'translate-y-0 opacity-100' : 'translate-y-8 opacity-0']">
                <h2 class="font-display text-3xl font-bold tracking-[-0.035em] text-foreground sm:text-4xl lg:text-5xl">
                    How it <span class="text-primary">works</span>
                </h2>
                <p class="mt-3 text-base leading-relaxed text-muted-foreground">From design to insights in three focused steps.</p>
            </div>
            <div class="mt-14 grid gap-6 lg:grid-cols-3">
                <div
                    v-for="(s, i) in steps"
                    :key="s.num"
                    :class="[
                        'app-surface relative p-7 transition-[opacity,transform] duration-700 ease-[cubic-bezier(0.22,1,0.36,1)]',
                        visible ? 'translate-y-0 opacity-100' : 'translate-y-8 opacity-0',
                    ]"
                    :style="{ transitionDelay: `${200 + i * 130}ms` }"
                >
                    <span class="mb-4 inline-flex size-9 items-center justify-center rounded-lg border border-primary/20 bg-primary/10 text-xs font-semibold text-primary">
                        {{ s.num }}
                    </span>
                    <h3 class="font-display mb-1.5 text-lg font-bold tracking-[-0.02em] text-foreground">{{ s.title }}</h3>
                    <p class="text-sm leading-relaxed text-muted-foreground">{{ s.description }}</p>
                    <ChevronRight
                        v-if="i < steps.length - 1"
                        class="pointer-events-none absolute -right-4 top-1/2 hidden size-4 -translate-y-1/2 text-primary/40 lg:block"
                        :stroke-width="2"
                    />
                </div>
            </div>
        </div>
    </section>
</template>
