<script setup lang="ts">
import { ref, onMounted } from 'vue';

const visible = ref(false);
onMounted(() => {
    const observer = new IntersectionObserver(
        ([entry]) => {
            if (entry.isIntersecting) visible.value = true;
        },
        { threshold: 0.1 }
    );
    const el = document.getElementById('stats-bar');
    if (el) observer.observe(el);
});

const stats = [
    { value: '10K+', label: 'Events Created', icon: 'calendar' },
    { value: '50K+', label: 'Forms Built', icon: 'form' },
    { value: '2M+', label: 'Responses', icon: 'chart' },
    { value: '99.9%', label: 'Uptime', icon: 'shield' },
];
</script>

<template>
    <section id="stats-bar" class="relative border-y-[1.5px] border-[var(--brutal-ink)] bg-[var(--brutal-blue)] py-14 lg:py-18">
        <div class="brutal-grid pointer-events-none absolute inset-0 opacity-[0.04]"></div>

        <div class="relative mx-auto max-w-7xl px-6 lg:px-8">
            <div class="grid grid-cols-2 gap-5 lg:grid-cols-4 lg:gap-0">
                <div
                    v-for="(stat, i) in stats"
                    :key="stat.label"
                    :class="[
                        'relative flex flex-col items-center rounded-xl border-[1.5px] border-[var(--brutal-ink)] bg-white px-4 py-5 text-[var(--brutal-ink)] shadow-[var(--brutal-shadow-sm)] transition-all duration-600 lg:rounded-none lg:border-0 lg:bg-transparent lg:text-white lg:shadow-none',
                        visible ? 'translate-y-0 opacity-100' : 'translate-y-4 opacity-0',
                        i < 3 ? 'lg:border-r-[1.5px] lg:border-white/20' : '',
                    ]"
                    :style="{ transitionDelay: `${i * 100}ms` }"
                >
                    <div class="mb-3 flex h-10 w-10 items-center justify-center rounded-lg border-[1.5px] border-[var(--brutal-ink)] bg-[var(--brutal-yellow)]/20 text-[var(--brutal-ink)] shadow-[var(--brutal-shadow-sm)] lg:border-white/30 lg:bg-white/15 lg:text-white lg:shadow-none">
                        <svg
                            v-if="stat.icon === 'calendar'"
                            class="h-4.5 w-4.5"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.8"
                            stroke-linecap="round"
                        >
                            <rect width="18" height="18" x="3" y="4" rx="2" />
                            <path d="M16 2v4" />
                            <path d="M8 2v4" />
                            <path d="M3 10h18" />
                        </svg>
                        <svg
                            v-if="stat.icon === 'form'"
                            class="h-4.5 w-4.5"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.8"
                            stroke-linecap="round"
                        >
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z" />
                            <path d="M14 2v6h6" />
                            <path d="M16 13H8" />
                            <path d="M16 17H8" />
                        </svg>
                        <svg
                            v-if="stat.icon === 'chart'"
                            class="h-4.5 w-4.5"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.8"
                            stroke-linecap="round"
                        >
                            <path d="M3 3v18h18" />
                            <path d="M18 17V9" />
                            <path d="M13 17V5" />
                            <path d="M8 17v-3" />
                        </svg>
                        <svg
                            v-if="stat.icon === 'shield'"
                            class="h-4.5 w-4.5"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.8"
                            stroke-linecap="round"
                        >
                            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10" />
                            <path d="m9 12 2 2 4-4" />
                        </svg>
                    </div>
                    <span class="font-display text-3xl font-bold tracking-tight">{{ stat.value }}</span>
                    <span class="mt-1 text-[10px] font-bold tracking-wider uppercase opacity-70">{{
                        stat.label
                    }}</span>
                </div>
            </div>
        </div>
    </section>
</template>
