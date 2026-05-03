<script setup lang="ts">
import { ref, onMounted } from 'vue';

const visible = ref(false);
onMounted(() => {
    const observer = new IntersectionObserver(
        ([entry]) => { if (entry.isIntersecting) visible.value = true; },
        { threshold: 0.1 }
    );
    const el = document.getElementById('feat-how');
    if (el) observer.observe(el);
});

const steps = [
    { num: '01', title: 'Design Your Form', desc: 'Use the drag-and-drop builder to add fields, set validations, and customize the look and feel.' },
    { num: '02', title: 'Publish & Share', desc: 'Generate a shareable link or embed the form on your website. Go live in seconds.' },
    { num: '03', title: 'Analyze Results', desc: 'View real-time responses, auto-generated charts, and export data at any time.' },
];
</script>

<template>
    <section id="feat-how" class="relative overflow-hidden bg-background py-20 lg:py-28">
        <div class="absolute left-8 top-12 h-20 w-20 -rotate-12 rounded-2xl bg-[var(--brutal-lilac)]/8 blur-md"></div>
        <div class="mx-auto max-w-7xl px-6 lg:px-8">
            <div :class="['mx-auto max-w-2xl text-center transition-all duration-700', visible ? 'translate-y-0 opacity-100' : 'translate-y-8 opacity-0']">
                <h2 class="font-display text-3xl font-bold tracking-[-0.035em] text-[var(--brutal-ink)] sm:text-4xl lg:text-5xl">
                    How it <span class="text-[var(--brutal-blue)]">works</span>
                </h2>
                <p class="mt-3 text-base font-medium text-[var(--brutal-ink)]/60">From design to insights in three punchy steps.</p>
            </div>
            <div class="mt-14 grid gap-6 lg:grid-cols-3">
                <div
                    v-for="(s, i) in steps" :key="s.num"
                    :class="['brutal-card relative p-7 transition-all duration-700', visible ? 'translate-y-0 opacity-100' : 'translate-y-8 opacity-0']"
                    :style="{ transitionDelay: `${200 + i * 150}ms` }"
                >
                    <span class="mb-3.5 inline-flex h-9 w-9 items-center justify-center rounded-lg border-[1.5px] border-[var(--brutal-ink)] bg-[var(--brutal-yellow)]/15 text-xs font-bold text-[var(--brutal-ink)] shadow-[var(--brutal-shadow-sm)]">{{ s.num }}</span>
                    <h3 class="font-display mb-1.5 text-lg font-bold text-[var(--brutal-ink)]">{{ s.title }}</h3>
                    <p class="text-sm font-medium leading-relaxed text-[var(--brutal-ink)]/60">{{ s.desc }}</p>
                    <div v-if="i < 2" class="pointer-events-none absolute -right-4 top-1/2 z-10 hidden -translate-y-1/2 lg:block">
                        <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><path d="M6 4l4 4-4 4" stroke="var(--brutal-blue)" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" opacity="0.25"/></svg>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>
