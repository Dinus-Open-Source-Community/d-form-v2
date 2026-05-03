<script setup lang="ts">
import { ref, onMounted } from 'vue';

const visible = ref(false);
onMounted(() => setTimeout(() => (visible.value = true), 100));
</script>

<template>
    <section class="relative overflow-hidden pt-32 pb-20 lg:pt-40 lg:pb-28">
        <!-- Subtle background elements (reduced from full brutal) -->
        <div class="brutal-grid pointer-events-none absolute inset-0 opacity-[0.02]"></div>
        <div class="absolute -top-24 right-8 h-40 w-40 rotate-12 rounded-[2rem] bg-[var(--brutal-yellow)]/15 blur-sm"></div>
        <div class="absolute bottom-16 left-6 hidden h-24 w-24 -rotate-12 rounded-full bg-[var(--brutal-pink)]/10 blur-sm lg:block"></div>

        <div class="relative mx-auto grid max-w-7xl items-center gap-14 px-6 lg:grid-cols-[1.04fr_0.96fr] lg:px-8">
            <div
                :class="[
                    'transition-all duration-700 ease-out',
                    visible ? 'translate-y-0 opacity-100' : 'translate-y-8 opacity-0',
                ]"
            >
                <div
                    class="mb-6 inline-flex items-center gap-2 rounded-full border-[1.5px] border-[var(--brutal-ink)] bg-[var(--brutal-mint)]/15 px-4 py-2 text-xs font-bold uppercase tracking-[0.14em] shadow-[var(--brutal-shadow-sm)]"
                >
                    <span class="h-2 w-2 animate-pulse rounded-full bg-[var(--brutal-blue)]"></span>
                    Event ops, but louder
                </div>

                <h1
                    class="font-display text-balance max-w-4xl text-[2.8rem] leading-[0.96] font-bold tracking-[-0.045em] text-[var(--brutal-ink)] sm:text-6xl lg:text-7xl"
                >
                    Build forms that feel
                    <span class="relative inline-block text-[var(--brutal-blue)]">
                        alive.
                        <span class="hero-underline absolute -bottom-1.5 left-0 h-2.5 w-full rounded-full bg-[var(--brutal-yellow)]"></span>
                    </span>
                </h1>

                <p class="mt-6 max-w-2xl text-lg leading-relaxed font-medium text-[var(--brutal-ink)]/65">
                    Create registration flows, manage attendees, and read the room from one colorful dashboard that is
                    clear, human, and impossible to miss.
                </p>

                <div class="mt-9 flex flex-wrap items-center gap-3.5">
                    <a
                        href="/auth"
                        class="group inline-flex items-center gap-2.5 rounded-xl border-[1.5px] border-[var(--brutal-ink)] bg-[var(--brutal-blue)] px-7 py-3.5 text-sm font-bold text-white shadow-[var(--brutal-shadow)] transition-all duration-150 hover:-translate-y-0.5 hover:shadow-[4px_4px_0_var(--brutal-ink)] active:translate-y-0 active:shadow-[1px_1px_0_var(--brutal-ink)]"
                    >
                        Get Started Free
                        <svg
                            class="h-4 w-4 transition-transform duration-200 group-hover:translate-x-0.5"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2.5"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                        >
                            <path d="M5 12h14" />
                            <path d="m12 5 7 7-7 7" />
                        </svg>
                    </a>
                    <a
                        href="/features"
                        class="inline-flex items-center gap-2 rounded-xl border-[1.5px] border-[var(--brutal-ink)] bg-white px-7 py-3.5 text-sm font-bold text-[var(--brutal-ink)] shadow-[var(--brutal-shadow)] transition-all duration-150 hover:-translate-y-0.5 hover:bg-[var(--brutal-pink)]/8 hover:shadow-[4px_4px_0_var(--brutal-ink)] active:translate-y-0 active:shadow-[1px_1px_0_var(--brutal-ink)]"
                    >
                        See How It Works
                    </a>
                </div>

                <div class="mt-8 flex flex-wrap gap-2.5 text-sm font-bold">
                    <span class="rounded-full border-[1.5px] border-[var(--brutal-ink)]/20 bg-white px-3.5 py-1.5 shadow-[var(--shadow-xs)]">No credit card</span>
                    <span class="rounded-full border-[1.5px] border-[var(--brutal-ink)]/20 bg-[var(--brutal-lilac)]/10 px-3.5 py-1.5 shadow-[var(--shadow-xs)]">Live analytics</span>
                    <span class="rounded-full border-[1.5px] border-[var(--brutal-ink)]/20 bg-[var(--brutal-yellow)]/15 px-3.5 py-1.5 shadow-[var(--shadow-xs)]">DOSCOM made</span>
                </div>
            </div>

            <div
                :class="[
                    'relative transition-all delay-200 duration-700 ease-out',
                    visible ? 'translate-y-0 opacity-100' : 'translate-y-12 opacity-0',
                ]"
            >
                <!-- Floating accent (subtle) -->
                <div class="absolute -right-4 top-6 h-16 w-16 rounded-full bg-[var(--brutal-mint)]/15 blur-sm"></div>
                <div class="absolute -bottom-4 left-6 z-10 rounded-xl border-[1.5px] border-[var(--brutal-ink)] bg-white px-4 py-2.5 text-sm font-bold shadow-[var(--brutal-shadow)]">
                    <span class="text-[var(--brutal-blue)]">342</span> people joined today
                </div>

                <!-- SVG Dashboard illustration — kept but refined -->
                <svg viewBox="0 0 720 520" fill="none" xmlns="http://www.w3.org/2000/svg" class="home-svg w-full">
                    <rect x="64" y="48" width="560" height="392" rx="24" fill="white" stroke="var(--brutal-ink)" stroke-width="3" opacity="0.4" />
                    <rect x="86" y="72" width="560" height="392" rx="24" fill="var(--brutal-blue)" stroke="var(--brutal-ink)" stroke-width="3" />
                    <rect x="112" y="98" width="430" height="54" rx="14" fill="var(--brutal-yellow)" stroke="var(--brutal-ink)" stroke-width="2" opacity="0.9" />
                    <circle cx="136" cy="125" r="7" fill="var(--brutal-pink)" stroke="var(--brutal-ink)" stroke-width="2" />
                    <circle cx="160" cy="125" r="7" fill="var(--brutal-mint)" stroke="var(--brutal-ink)" stroke-width="2" />
                    <circle cx="184" cy="125" r="7" fill="white" stroke="var(--brutal-ink)" stroke-width="2" />

                    <g class="card-1">
                        <rect x="112" y="186" width="190" height="150" rx="16" fill="var(--brutal-pink)" stroke="var(--brutal-ink)" stroke-width="2.5" opacity="0.9" />
                        <rect x="132" y="210" width="92" height="12" rx="6" fill="var(--brutal-ink)" />
                        <rect x="132" y="238" width="130" height="10" rx="5" fill="white" stroke="var(--brutal-ink)" stroke-width="1.5" />
                        <rect x="132" y="264" width="96" height="10" rx="5" fill="white" stroke="var(--brutal-ink)" stroke-width="1.5" />
                        <rect x="132" y="300" width="118" height="20" rx="8" fill="var(--brutal-yellow)" stroke="var(--brutal-ink)" stroke-width="1.5" />
                    </g>

                    <g class="card-2">
                        <rect x="330" y="186" width="190" height="150" rx="16" fill="var(--brutal-mint)" stroke="var(--brutal-ink)" stroke-width="2.5" opacity="0.85" />
                        <path d="M360 300 L392 258 L424 282 L466 224" stroke="var(--brutal-ink)" stroke-width="4" stroke-linecap="round" stroke-linejoin="round" />
                        <circle cx="466" cy="224" r="10" fill="var(--brutal-yellow)" stroke="var(--brutal-ink)" stroke-width="2.5" />
                        <rect x="360" y="210" width="82" height="12" rx="6" fill="var(--brutal-ink)" />
                    </g>

                    <g class="notif-float">
                        <rect x="430" y="350" width="170" height="68" rx="16" fill="white" stroke="var(--brutal-ink)" stroke-width="2.5" />
                        <circle cx="464" cy="384" r="14" fill="var(--brutal-blue)" stroke="var(--brutal-ink)" stroke-width="2" />
                        <path d="M458 384l5 5 10-12" stroke="white" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" />
                        <rect x="494" y="374" width="70" height="9" rx="4.5" fill="var(--brutal-ink)" />
                        <rect x="494" y="392" width="48" height="7" rx="3.5" fill="var(--brutal-ink)" opacity="0.3" />
                    </g>
                </svg>
            </div>
        </div>
    </section>
</template>

<style scoped>
.hero-underline {
    z-index: -1;
    animation: underlineGrow 0.75s cubic-bezier(0.22, 1, 0.36, 1) 0.45s both;
}
@keyframes underlineGrow {
    from {
        transform: scaleX(0);
        transform-origin: left;
    }
    to {
        transform: scaleX(1);
        transform-origin: left;
    }
}

.card-1 {
    animation: cFloat 7s ease-in-out infinite;
}
.card-2 {
    animation: cFloat 8s ease-in-out infinite;
    animation-delay: -2s;
}
@keyframes cFloat {
    0%,
    100% {
        transform: translateY(0);
    }
    50% {
        transform: translateY(-3px);
    }
}

.notif-float {
    animation: nFloat 6s ease-in-out infinite;
}
@keyframes nFloat {
    0%,
    100% {
        transform: translateY(0);
    }
    50% {
        transform: translateY(-4px);
    }
}
</style>
