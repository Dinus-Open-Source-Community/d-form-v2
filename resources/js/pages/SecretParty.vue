<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref } from 'vue'
import { Head } from '@inertiajs/vue3'

const RICKROLL_EMBED_SRC =
    'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ?autoplay=1&mute=0&loop=1&playlist=dQw4w9WgXcQ&controls=1&playsinline=1&rel=0&modestbranding=1'

type Phase = 'intro' | 'rickroll'

const phase = ref<Phase>('intro')
let autoTimer: ReturnType<typeof setTimeout> | null = null
const prefersReducedMotion =
    typeof window !== 'undefined' &&
    window.matchMedia?.('(prefers-reduced-motion: reduce)')?.matches

function goRickroll(): void {
    phase.value = 'rickroll'
    if (autoTimer != null) {
        clearTimeout(autoTimer)
        autoTimer = null
    }
}

onMounted(() => {
    if (prefersReducedMotion) {
        return
    }
    autoTimer = setTimeout(goRickroll, 8500)
})

onBeforeUnmount(() => {
    if (autoTimer != null) {
        clearTimeout(autoTimer)
    }
})
</script>

<template>
    <Head title="Surprise">
        <meta head-key="secret-robots" name="robots" content="noindex, nofollow" />
    </Head>

    <!-- Intro: celebratory SVG + confetti (no external assets) -->
    <div
        v-show="phase === 'intro'"
        class="secret-intro text-foreground fixed inset-0 z-[2147483646] flex flex-col items-center justify-center overflow-hidden bg-gradient-to-b from-violet-200 via-fuchsia-100 to-amber-100 dark:from-violet-950 dark:via-fuchsia-950 dark:to-slate-950"
        role="presentation"
    >
        <p class="sr-only">Animasi pembuka pesta rahasia.</p>

        <!-- Confetti layer -->
        <div class="pointer-events-none absolute inset-0 overflow-hidden" aria-hidden="true">
            <span
                v-for="n in 36"
                :key="n"
                class="confetti"
                :class="`c-${n % 7}`"
                :style="{ left: `${(n * 37) % 100}%`, animationDelay: `${(n % 11) * 0.12}s` }"
            />
        </div>

        <div class="relative z-10 flex max-w-lg flex-col items-center px-6 text-center">
            <svg
                class="celebrate-svg max-h-[min(52vh,420px)] w-full max-w-[min(90vw,380px)] drop-shadow-lg"
                viewBox="0 0 320 360"
                xmlns="http://www.w3.org/2000/svg"
                aria-hidden="true"
            >
                <defs>
                    <linearGradient id="b1" x1="0%" y1="0%" x2="100%" y2="100%">
                        <stop offset="0%" stop-color="#fb7185" />
                        <stop offset="100%" stop-color="#f59e0b" />
                    </linearGradient>
                    <linearGradient id="b2" x1="0%" y1="0%" x2="0%" y2="100%">
                        <stop offset="0%" stop-color="#38bdf8" />
                        <stop offset="100%" stop-color="#7c3aed" />
                    </linearGradient>
                    <linearGradient id="b3" x1="0%" y1="100%" x2="100%" y2="0%">
                        <stop offset="0%" stop-color="#34d399" />
                        <stop offset="100%" stop-color="#14b8a6" />
                    </linearGradient>
                </defs>

                <!-- strings -->
                <path
                    d="M78 118 Q 100 200 115 310"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="1.2"
                    class="text-foreground/25"
                />
                <path
                    d="M160 98 Q 165 190 168 315"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="1.2"
                    class="text-foreground/25"
                />
                <path
                    d="M242 112 Q 220 205 200 308"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="1.2"
                    class="text-foreground/25"
                />

                <!-- balloons -->
                <g class="balloon b-left">
                    <ellipse cx="78" cy="88" rx="38" ry="48" fill="url(#b1)" />
                    <polygon points="78,130 74,140 82,140" fill="#b45309" opacity="0.35" />
                </g>
                <g class="balloon b-mid">
                    <ellipse cx="160" cy="72" rx="44" ry="56" fill="url(#b2)" />
                    <ellipse cx="148" cy="62" rx="10" ry="6" fill="white" opacity="0.35" />
                    <polygon points="160,122 154,134 166,134" fill="#1e3a5f" opacity="0.35" />
                </g>
                <g class="balloon b-right">
                    <ellipse cx="242" cy="84" rx="36" ry="46" fill="url(#b3)" />
                    <polygon points="242,124 238,136 246,136" fill="#047857" opacity="0.35" />
                </g>

                <!-- mini cake -->
                <g transform="translate(118 250)">
                    <g class="cake-bob">
                        <rect x="0" y="40" width="84" height="28" rx="4" fill="currentColor" class="text-amber-800/80" />
                        <rect x="8" y="24" width="68" height="20" rx="3" fill="currentColor" class="text-amber-100 dark:text-amber-200/90" />
                        <rect x="36" y="12" width="12" height="16" rx="2" fill="currentColor" class="text-rose-400" />
                        <ellipse cx="42" cy="10" rx="4" ry="6" fill="#fbbf24" class="flame" />
                    </g>
                </g>

                <!-- sparkles -->
                <g class="sparkles" fill="#fcd34d" opacity="0.95">
                    <path d="M48 40l2 6 6 2-6 2-2 6-2-6-6-2 6-2z" />
                    <path d="M278 48l1.5 5 5 1.5-5 1.5-1.5 5-1.5-5-5-1.5 5-1.5z" />
                    <path d="M292 168l1 4 4 1-4 1-1 4-1-4-4-1 4-1z" />
                </g>
            </svg>

            <h1
                class="font-display mt-2 text-balance text-2xl font-bold tracking-tight text-violet-950 dark:text-violet-100 sm:text-3xl"
            >
                Pesta rahasia 🎈
            </h1>
            <p class="text-muted-foreground mt-2 max-w-sm text-pretty text-sm sm:text-base">
                Balon sudah naik, konfeti sudah jatuh… ada kejutan spesial kalau kamu siap.
            </p>

            <button
                type="button"
                class="bg-background/90 text-foreground ring-border/60 hover:bg-background mt-8 rounded-full px-7 py-3 text-sm font-semibold shadow-lg ring-1 transition-[transform,box-shadow] hover:scale-[1.02] active:scale-[0.99] dark:ring-white/10"
                @click="goRickroll"
            >
                Buka kejutan 🎁
            </button>
            <p v-if="!prefersReducedMotion" class="text-muted-foreground mt-3 text-xs">
                Atau tunggu sebentar — kejutan akan terbuka sendiri.
            </p>
            <p v-else class="text-muted-foreground mt-3 text-xs">Tekan tombol di atas untuk melanjutkan.</p>
        </div>
    </div>

    <!-- Rickroll (same as classic easter egg) -->
    <div
        v-show="phase === 'rickroll'"
        class="fixed inset-0 z-[2147483647] m-0 h-[100dvh] w-full bg-black p-0"
    >
        <iframe
            class="absolute inset-0 h-full w-full border-0"
            :src="RICKROLL_EMBED_SRC"
            title="Never Gonna Give You Up"
            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
            allowfullscreen
            referrerpolicy="strict-origin-when-cross-origin"
        />
        <div
            class="pointer-events-none absolute inset-x-0 bottom-0 z-10 flex justify-end px-4 pb-[max(1rem,env(safe-area-inset-bottom))] pt-16 sm:px-5"
        >
            <p
                class="pointer-events-auto max-w-[18rem] text-pretty rounded-2xl border border-white/15 bg-black/70 px-4 py-3 text-right text-xs text-white/90 shadow-2xl ring-1 ring-white/5 backdrop-blur-md"
                role="status"
            >
                Kamu menemukan rute rahasia. Semoga harimu tetap produktif.
            </p>
        </div>
    </div>
</template>

<style scoped>
.balloon {
    transform-origin: center bottom;
    animation: sway 2.8s ease-in-out infinite;
}
.balloon.b-left {
    animation-delay: 0s;
}
.balloon.b-mid {
    animation-delay: 0.4s;
}
.balloon.b-right {
    animation-delay: 0.8s;
}

.cake-bob {
    transform-origin: 42px 52px;
    animation: bob 3s ease-in-out infinite;
}

.flame {
    animation: flicker 0.6s ease-in-out infinite alternate;
}

.sparkles path {
    animation: twinkle 1.4s ease-in-out infinite;
}
.sparkles path:nth-child(2) {
    animation-delay: 0.35s;
}
.sparkles path:nth-child(3) {
    animation-delay: 0.7s;
}

@media (prefers-reduced-motion: reduce) {
    .balloon,
    .cake-bob,
    .sparkles path,
    .confetti,
    .celebrate-svg {
        animation: none !important;
    }
}

@keyframes sway {
    0%,
    100% {
        transform: translateY(0) rotate(-2deg);
    }
    50% {
        transform: translateY(-10px) rotate(3deg);
    }
}

@keyframes bob {
    0%,
    100% {
        transform: translateY(0);
    }
    50% {
        transform: translateY(-4px);
    }
}

@keyframes flicker {
    from {
        opacity: 0.85;
        transform: scale(1);
    }
    to {
        opacity: 1;
        transform: scale(1.08);
    }
}

@keyframes twinkle {
    0%,
    100% {
        opacity: 0.35;
        transform: scale(0.9);
    }
    50% {
        opacity: 1;
        transform: scale(1.1);
    }
}

.confetti {
    position: absolute;
    top: -12px;
    width: 8px;
    height: 12px;
    border-radius: 2px;
    opacity: 0.9;
    animation: fall 6.5s linear infinite;
}

.c-0 {
    background: #f472b6;
}
.c-1 {
    background: #a78bfa;
}
.c-2 {
    background: #38bdf8;
}
.c-3 {
    background: #fbbf24;
}
.c-4 {
    background: #34d399;
}
.c-5 {
    background: #fb7185;
}
.c-6 {
    background: #818cf8;
}

@keyframes fall {
    0% {
        transform: translateY(-10%) rotate(0deg);
    }
    100% {
        transform: translateY(110vh) rotate(720deg);
    }
}
</style>
