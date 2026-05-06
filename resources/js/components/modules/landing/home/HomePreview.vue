<script setup lang="ts">
import { onMounted, ref } from 'vue'

const visible = ref(false)

onMounted(() => {
    const observer = new IntersectionObserver(
        ([entry]) => {
            if (entry.isIntersecting) visible.value = true
        },
        { threshold: 0.15 },
    )
    const el = document.getElementById('preview-section')
    if (el) observer.observe(el)
})

const previews = [
    { title: 'Form Builder', desc: 'Drag-and-drop interface with field rules, media choices, and live preview.' },
    { title: 'Event Manager', desc: 'Create, schedule, and manage events with attendee tracking built in.' },
    { title: 'Live Analytics', desc: 'Read responses instantly with charts, tables, and export-ready data.' },
]
</script>

<template>
    <section id="preview-section" class="relative overflow-hidden bg-background py-20 lg:py-28">
        <div class="pointer-events-none absolute right-8 top-12 h-64 w-64 rounded-full bg-primary/8 blur-3xl"></div>
        <div class="mx-auto max-w-7xl px-6 lg:px-8">
            <div :class="['mx-auto max-w-2xl text-center transition-all duration-700', visible ? 'translate-y-0 opacity-100' : 'translate-y-8 opacity-0']">
                <h2 class="font-display text-3xl font-semibold tracking-[-0.04em] text-foreground sm:text-4xl lg:text-5xl">
                    Everything you need,<br/><span class="text-primary">nothing you don't.</span>
                </h2>
                <p class="mt-4 text-base leading-relaxed text-muted-foreground">Powerful, focused tools to run your events end-to-end.</p>
            </div>

            <div class="mt-14 grid gap-6 lg:grid-cols-[1.1fr_0.9fr_1fr]">
                <div
                    v-for="(p, i) in previews"
                    :key="p.title"
                    :class="['app-surface group p-7 transition-all duration-500', i === 1 ? 'lg:mt-10' : '', visible ? 'translate-y-0 opacity-100' : 'translate-y-8 opacity-0']"
                    :style="{ transitionDelay: `${200 + i * 120}ms` }"
                >
                    <div class="mb-4 flex items-center gap-3">
                        <span class="size-2 rounded-full bg-primary"></span>
                        <span class="text-xs font-semibold uppercase tracking-[0.16em] text-muted-foreground">0{{ i + 1 }}</span>
                    </div>
                    <h3 class="font-display mb-2 text-xl font-semibold text-foreground">{{ p.title }}</h3>
                    <p class="text-sm leading-relaxed text-muted-foreground">{{ p.desc }}</p>
                </div>
            </div>
        </div>
    </section>
</template>
