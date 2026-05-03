<script setup lang="ts">
import { ref, onMounted } from 'vue';

const visible = ref(false);
onMounted(() => {
    const observer = new IntersectionObserver(
        ([entry]) => { if (entry.isIntersecting) visible.value = true; },
        { threshold: 0.15 }
    );
    const el = document.getElementById('preview-section');
    if (el) observer.observe(el);
});

const previews = [
    { icon: 'form', title: 'Form Builder', desc: 'Drag-and-drop interface with 20+ field types and conditional logic.' },
    { icon: 'event', title: 'Event Manager', desc: 'Create, schedule, and manage events with real-time attendee tracking.' },
    { icon: 'chart', title: 'Live Analytics', desc: 'Visualize responses instantly with auto-generated charts and exports.' },
];
</script>

<template>
    <section id="preview-section" class="relative overflow-hidden bg-background py-20 lg:py-28">
        <div class="absolute right-8 top-12 h-20 w-20 rounded-full bg-[var(--brutal-pink)]/8 blur-md"></div>
        <div class="mx-auto max-w-7xl px-6 lg:px-8">
            <div :class="['mx-auto max-w-2xl text-center transition-all duration-700', visible ? 'translate-y-0 opacity-100' : 'translate-y-8 opacity-0']">
                <h2 class="font-display text-3xl font-bold tracking-[-0.035em] text-[var(--brutal-ink)] sm:text-4xl lg:text-5xl">
                    Everything you need,<br/><span class="text-[var(--brutal-blue)]">nothing you don't.</span>
                </h2>
                <p class="mt-4 text-base font-medium leading-relaxed text-[var(--brutal-ink)]/60">Powerful, expressive tools to run your events end-to-end.</p>
            </div>

            <div class="mt-14 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                <div
                    v-for="(p, i) in previews" :key="p.title"
                    :class="['brutal-card group p-7 transition-all duration-500', visible ? 'translate-y-0 opacity-100' : 'translate-y-8 opacity-0']"
                    :style="{ transitionDelay: `${200 + i * 120}ms` }"
                >
                    <div class="mb-4 flex h-10 w-10 items-center justify-center rounded-lg border-[1.5px] border-[var(--brutal-ink)] bg-[var(--brutal-yellow)]/15 text-[var(--brutal-ink)] shadow-[var(--brutal-shadow-sm)] transition-colors group-hover:bg-[var(--brutal-mint)]/15">
                        <svg v-if="p.icon === 'form'" class="h-4.5 w-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"/><path d="M14 2v6h6"/><path d="M16 13H8"/><path d="M16 17H8"/><path d="M10 9H8"/></svg>
                        <svg v-if="p.icon === 'event'" class="h-4.5 w-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="18" x="3" y="4" rx="2"/><path d="M16 2v4"/><path d="M8 2v4"/><path d="M3 10h18"/></svg>
                        <svg v-if="p.icon === 'chart'" class="h-4.5 w-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v18h18"/><path d="M18 17V9"/><path d="M13 17V5"/><path d="M8 17v-3"/></svg>
                    </div>
                    <h3 class="font-display mb-2 text-lg font-bold text-[var(--brutal-ink)]">{{ p.title }}</h3>
                    <p class="text-sm font-medium leading-relaxed text-[var(--brutal-ink)]/60">{{ p.desc }}</p>
                </div>
            </div>
        </div>
    </section>
</template>
