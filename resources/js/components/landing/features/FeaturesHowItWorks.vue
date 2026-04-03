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
    <section id="feat-how" class="bg-white py-24 lg:py-32">
        <div class="mx-auto max-w-7xl px-6 lg:px-8">
            <div :class="['mx-auto max-w-2xl text-center transition-all duration-700', visible ? 'translate-y-0 opacity-100' : 'translate-y-8 opacity-0']">
                <h2 class="text-2xl font-extrabold tracking-tight text-[#111827] sm:text-3xl">
                    How it <span class="text-[#0A84DC]">works</span>
                </h2>
                <p class="mt-3 text-sm text-[#6B7280]">From design to insights in three simple steps.</p>
            </div>

            <div class="mt-16 grid gap-8 lg:grid-cols-3">
                <div
                    v-for="(s, i) in steps" :key="s.num"
                    :class="['relative rounded-xl border border-[#E5E7EB] bg-[#F9FAFB] p-8 transition-all duration-700', visible ? 'translate-y-0 opacity-100' : 'translate-y-8 opacity-0']"
                    :style="{ transitionDelay: `${200 + i * 150}ms` }"
                >
                    <span class="mb-4 inline-flex h-10 w-10 items-center justify-center rounded-lg bg-[#0A84DC]/[0.06] text-sm font-extrabold text-[#0A84DC]">{{ s.num }}</span>
                    <h3 class="mb-2 text-base font-bold text-[#111827]">{{ s.title }}</h3>
                    <p class="text-sm leading-relaxed text-[#6B7280]">{{ s.desc }}</p>
                    <!-- Connector arrow between cards (desktop) -->
                    <div v-if="i < 2" class="pointer-events-none absolute -right-4 top-1/2 z-10 hidden -translate-y-1/2 lg:block">
                        <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><path d="M6 4l4 4-4 4" stroke="#0A84DC" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" opacity="0.3"/></svg>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>
