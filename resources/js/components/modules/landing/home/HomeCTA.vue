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
    const el = document.getElementById('cta-home')
    if (el) observer.observe(el)
})
</script>

<template>
    <section id="cta-home" class="relative overflow-hidden border-y border-primary/20 bg-primary py-20 lg:py-24">
        <div class="app-grid pointer-events-none absolute inset-0 opacity-20"></div>
        <div class="pointer-events-none absolute -top-24 left-10 h-72 w-72 rounded-full bg-white/20 blur-3xl"></div>

        <div
            :class="[
                'relative mx-auto max-w-4xl px-6 text-center transition-all duration-700',
                visible ? 'translate-y-0 opacity-100' : 'translate-y-8 opacity-0',
            ]"
        >
            <div class="mx-auto mb-5 inline-flex w-fit items-center gap-1.5 rounded-full border border-white/20 bg-white/10 px-3.5 py-1.5 text-[11px] font-semibold uppercase tracking-[0.16em] text-white backdrop-blur">
                <span class="size-1.5 rounded-full bg-white"></span>
                Make the next registration memorable
            </div>
            <h2 class="font-display text-balance text-3xl font-bold tracking-[-0.035em] text-white sm:text-4xl lg:text-5xl">
                Ready to create your<br />next event?
            </h2>
            <p class="mx-auto mt-4 max-w-xl text-base leading-relaxed text-white/75">
                Join organizers who want forms that feel clear, lively, and built for real humans.
            </p>
            <div class="mt-9 flex flex-wrap items-center justify-center gap-3">
                <a
                    href="/auth"
                    class="inline-flex items-center gap-2 rounded-xl border border-white/15 bg-white px-6 py-3 text-sm font-semibold text-primary shadow-sm transition-[transform,background-color] duration-200 ease-[cubic-bezier(0.22,1,0.36,1)] hover:-translate-y-px hover:bg-white/95 active:scale-[0.98]"
                >
                    Get Started Free
                </a>
            </div>
            <p class="mt-5 text-[10px] font-medium uppercase tracking-[0.16em] text-white/55">No credit card required. Setup in minutes.</p>
        </div>
    </section>
</template>
