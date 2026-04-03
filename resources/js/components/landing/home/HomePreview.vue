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
    <section id="preview-section" class="bg-white py-24 lg:py-32">
        <div class="mx-auto max-w-7xl px-6 lg:px-8">
            <div :class="['mx-auto max-w-2xl text-center transition-all duration-700', visible ? 'translate-y-0 opacity-100' : 'translate-y-8 opacity-0']">
                <h2 class="text-3xl font-extrabold tracking-tight text-[#111827] sm:text-4xl">
                    Everything you need,<br/><span class="text-[#0A84DC]">nothing you don't.</span>
                </h2>
                <p class="mt-4 text-base leading-relaxed text-[#6B7280]">Powerful yet simple tools to run your events end-to-end.</p>
            </div>

            <div class="mt-16 grid gap-8 sm:grid-cols-2 lg:grid-cols-3">
                <div
                    v-for="(p, i) in previews" :key="p.title"
                    :class="['group rounded-xl border border-[#E5E7EB] bg-white p-8 transition-all duration-500 hover:-translate-y-1 hover:border-[#0A84DC]/15 hover:shadow-md', visible ? 'translate-y-0 opacity-100' : 'translate-y-8 opacity-0']"
                    :style="{ transitionDelay: `${200 + i * 120}ms` }"
                >
                    <div class="mb-5 flex h-11 w-11 items-center justify-center rounded-xl bg-[#0A84DC]/[0.06] transition-colors group-hover:bg-[#0A84DC]/[0.12]">
                        <svg v-if="p.icon === 'form'" class="h-5 w-5 text-[#0A84DC]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"/><path d="M14 2v6h6"/><path d="M16 13H8"/><path d="M16 17H8"/><path d="M10 9H8"/></svg>
                        <svg v-if="p.icon === 'event'" class="h-5 w-5 text-[#0A84DC]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="18" x="3" y="4" rx="2"/><path d="M16 2v4"/><path d="M8 2v4"/><path d="M3 10h18"/></svg>
                        <svg v-if="p.icon === 'chart'" class="h-5 w-5 text-[#0A84DC]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v18h18"/><path d="M18 17V9"/><path d="M13 17V5"/><path d="M8 17v-3"/></svg>
                    </div>
                    <h3 class="mb-2 text-base font-bold text-[#111827]">{{ p.title }}</h3>
                    <p class="text-sm leading-relaxed text-[#6B7280]">{{ p.desc }}</p>
                </div>
            </div>
        </div>
    </section>
</template>
