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
    <section id="cta-home" class="relative overflow-hidden border-y-[1.5px] border-[var(--brutal-ink)] bg-[var(--brutal-blue)] py-20 lg:py-24">
        <div class="brutal-grid pointer-events-none absolute inset-0 opacity-[0.04]"></div>
        <!-- Subtle floating accents (reduced from full brutal) -->
        <div class="absolute -top-6 left-10 h-20 w-20 rotate-12 rounded-2xl bg-[var(--brutal-yellow)]/15 blur-sm"></div>
        <div class="absolute right-8 -bottom-8 h-24 w-24 rounded-full bg-[var(--brutal-pink)]/12 blur-sm"></div>

        <div
            :class="[
                'relative mx-auto max-w-4xl px-6 text-center transition-all duration-700',
                visible ? 'translate-y-0 opacity-100' : 'translate-y-8 opacity-0',
            ]"
        >
            <div class="mx-auto mb-5 w-fit rounded-full border-[1.5px] border-[var(--brutal-ink)] bg-white px-4 py-1.5 text-xs font-bold uppercase tracking-[0.14em] shadow-[var(--brutal-shadow-sm)]">
                Make the next registration memorable
            </div>
            <h2 class="font-display text-balance text-3xl font-bold tracking-[-0.035em] text-white sm:text-4xl lg:text-5xl">
                Ready to create your<br />next event?
            </h2>
            <p class="mx-auto mt-4 max-w-xl text-base leading-relaxed font-medium text-white/70">
                Join organizers who want forms that feel clear, lively, and built for real humans.
            </p>
            <div class="mt-9 flex flex-wrap items-center justify-center gap-3.5">
                <a
                    href="/auth"
                    class="group inline-flex items-center gap-2 rounded-xl border-[1.5px] border-[var(--brutal-ink)] bg-white px-7 py-3.5 text-sm font-bold text-[var(--brutal-blue)] shadow-[var(--brutal-shadow)] transition-all hover:-translate-y-0.5 hover:bg-[var(--brutal-yellow)] hover:text-[var(--brutal-ink)] hover:shadow-[4px_4px_0_var(--brutal-ink)] active:translate-y-0 active:shadow-[1px_1px_0_var(--brutal-ink)]"
                >
                    Get Started Free
                    <svg class="h-4 w-4 transition-transform group-hover:translate-x-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
                </a>
            </div>
            <p class="mt-5 text-[10px] font-bold uppercase tracking-[0.14em] text-white/50">No credit card required — setup in minutes</p>
        </div>
    </section>
</template>
