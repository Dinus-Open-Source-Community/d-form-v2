<script setup lang="ts">
import { ref, onMounted } from 'vue';

const isVisible = ref(false);

onMounted(() => {
    const observer = new IntersectionObserver(
        ([entry]) => {
            if (entry.isIntersecting) isVisible.value = true;
        },
        { threshold: 0.2 }
    );
    const el = document.getElementById('cta-section');
    if (el) observer.observe(el);
});
</script>

<template>
    <section id="cta-section" class="relative overflow-hidden bg-[#0A84DC] py-24 lg:py-32">
        <!-- Background decoration -->
        <div class="pointer-events-none absolute inset-0 opacity-10">
            <svg width="100%" height="100%">
                <defs>
                    <pattern id="cta-grid" width="32" height="32" patternUnits="userSpaceOnUse">
                        <circle cx="16" cy="16" r="1" fill="white" />
                    </pattern>
                </defs>
                <rect width="100%" height="100%" fill="url(#cta-grid)" />
            </svg>
        </div>

        <!-- Gradient orbs -->
        <div class="pointer-events-none absolute -top-32 -left-32 h-[400px] w-[400px] rounded-full bg-white/5 blur-3xl" />
        <div class="pointer-events-none absolute -right-32 -bottom-32 h-[400px] w-[400px] rounded-full bg-black/10 blur-3xl" />

        <div class="relative mx-auto max-w-4xl px-6 text-center lg:px-8">
            <div
                :class="[
                    'transition-all duration-700',
                    isVisible ? 'translate-y-0 opacity-100' : 'translate-y-8 opacity-0',
                ]"
            >
                <h2 class="text-3xl font-extrabold tracking-tight text-white sm:text-4xl lg:text-5xl">
                    Ready to create your next event?
                </h2>
                <p class="mx-auto mt-6 max-w-2xl text-lg leading-relaxed text-white/70">
                    Join thousands of event organizers who trust DForm to handle
                    registrations, forms, and attendee management.
                </p>
                <div class="mt-10 flex flex-wrap items-center justify-center gap-4">
                    <a
                        href="/auth"
                        class="group inline-flex items-center gap-2 rounded-xl bg-white px-8 py-4 text-sm font-bold text-[#0A84DC] shadow-lg transition-all hover:shadow-xl active:scale-[0.97]"
                    >
                        Get Started Free
                        <svg class="h-4 w-4 transition-transform group-hover:translate-x-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M5 12h14" /><path d="m12 5 7 7-7 7" />
                        </svg>
                    </a>
                    <a
                        href="#features"
                        class="inline-flex items-center gap-2 rounded-xl border border-white/25 px-8 py-4 text-sm font-semibold text-white transition-all hover:border-white/40 hover:bg-white/10"
                    >
                        Learn More
                    </a>
                </div>

                <p class="mt-8 text-sm text-white/40">
                    No credit card required · Free plan available · Set up in minutes
                </p>
            </div>
        </div>
    </section>
</template>
