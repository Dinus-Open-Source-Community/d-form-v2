<script setup lang="ts">
import { onMounted, ref } from 'vue'

const visible = ref(false)

onMounted(() => {
    const observer = new IntersectionObserver(
        ([entry]) => {
            if (entry.isIntersecting) visible.value = true
        },
        { threshold: 0.1 },
    )
    const el = document.getElementById('stats-bar')
    if (el) observer.observe(el)
})

const stats = [
    { value: '10K+', label: 'Events created' },
    { value: '50K+', label: 'Forms built' },
    { value: '2M+', label: 'Responses' },
    { value: '99.9%', label: 'Uptime' },
]
</script>

<template>
    <section id="stats-bar" class="relative border-y border-border bg-primary py-14 lg:py-18">
        <div class="app-grid pointer-events-none absolute inset-0 opacity-20"></div>
        <div class="relative mx-auto max-w-7xl px-6 lg:px-8">
            <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
                <div
                    v-for="(stat, i) in stats"
                    :key="stat.label"
                    :class="[
                        'rounded-2xl border border-white/15 bg-white/10 px-5 py-5 text-white backdrop-blur transition-[opacity,transform] duration-700 ease-[cubic-bezier(0.22,1,0.36,1)] lg:border-white/10 lg:bg-transparent',
                        visible ? 'translate-y-0 opacity-100' : 'translate-y-4 opacity-0',
                    ]"
                    :style="{ transitionDelay: `${i * 100}ms` }"
                >
                    <span class="font-display text-3xl font-bold tracking-[-0.025em]">{{ stat.value }}</span>
                    <span class="mt-1 block text-[10px] font-semibold uppercase tracking-[0.18em] text-white/65">
                        {{ stat.label }}
                    </span>
                </div>
            </div>
        </div>
    </section>
</template>
