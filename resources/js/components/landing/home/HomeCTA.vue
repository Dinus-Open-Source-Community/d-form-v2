<script setup lang="ts">
import { ref, onMounted } from 'vue';

const visible = ref(false);
onMounted(() => {
    const observer = new IntersectionObserver(
        ([entry]) => { if (entry.isIntersecting) visible.value = true; },
        { threshold: 0.15 }
    );
    const el = document.getElementById('cta-home');
    if (el) observer.observe(el);
});
</script>

<template>
    <section id="cta-home" class="relative overflow-hidden bg-[#0A84DC] py-24 lg:py-28">
        <div class="pointer-events-none absolute inset-0 opacity-[0.06]">
            <svg width="100%" height="100%">
                <defs>
                    <pattern id="cta-dots" width="28" height="28" patternUnits="userSpaceOnUse">
                        <circle cx="14" cy="14" r="1" fill="white" />
                    </pattern>
                </defs>
                <rect width="100%" height="100%" fill="url(#cta-dots)" />
            </svg>
        </div>

        <div :class="['relative mx-auto max-w-3xl px-6 text-center transition-all duration-700', visible ? 'translate-y-0 opacity-100' : 'translate-y-8 opacity-0']">
            <h2 class="text-3xl font-extrabold tracking-tight text-white sm:text-4xl lg:text-5xl">
                Ready to create your<br/>next event?
            </h2>
            <p class="mx-auto mt-5 max-w-xl text-base leading-relaxed text-white/60">
                Join thousands of organizers who trust DForm.
            </p>
            <div class="mt-10 flex flex-wrap items-center justify-center gap-4">
                <a href="/auth" class="group inline-flex items-center gap-2 rounded-xl bg-white px-8 py-4 text-sm font-bold text-[#0A84DC] shadow-sm transition-all hover:shadow-md active:scale-[0.97]">
                    Get Started Free
                    <svg class="h-4 w-4 transition-transform group-hover:translate-x-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
                </a>
            </div>
            <p class="mt-6 text-xs text-white/30">No credit card required · Setup in minutes</p>
        </div>
    </section>
</template>
