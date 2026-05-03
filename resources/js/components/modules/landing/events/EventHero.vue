<script setup lang="ts">
import { ref, onMounted } from 'vue';

const visible = ref(false);
onMounted(() => setTimeout(() => (visible.value = true), 100));
</script>

<template>
    <section class="bg-background relative overflow-hidden pt-32 pb-24 lg:pt-40 lg:pb-28">
        <div class="brutal-grid pointer-events-none absolute inset-0 opacity-[0.02]"></div>
        <div class="pointer-events-none absolute inset-0 opacity-[0.025]">
            <svg width="100%" height="100%">
                <defs>
                    <pattern id="evt-dots" width="20" height="20" patternUnits="userSpaceOnUse">
                        <circle cx="2" cy="2" r="0.6" fill="#111827" />
                    </pattern>
                </defs>
                <rect width="100%" height="100%" fill="url(#evt-dots)" />
            </svg>
        </div>

        <div class="max-w-8xl relative mx-auto flex w-full items-center justify-between px-6 lg:px-8">
            <!-- Centered hero text -->
            <div
                :class="[
                    'mx-auto w-full max-w-3xl text-center transition-all duration-700',
                    visible ? 'translate-y-0 opacity-100' : 'translate-y-8 opacity-0',
                ]"
            >
                <h1
                    class="font-display text-[2.8rem] leading-[0.96] font-bold tracking-[-0.045em] text-[var(--brutal-ink)] sm:text-6xl lg:text-7xl"
                >
                    Discover what's<br />
                    <span class="relative inline-block">
                        <span class="text-[var(--brutal-blue)]">happening next</span>
                        <span
                            class="evt-underline absolute -bottom-1 left-0 h-[3px] w-full rounded-full bg-[var(--brutal-blue)]/20"
                        ></span>
                    </span>
                </h1>

                <p class="mx-auto mt-6 max-w-2xl text-base leading-relaxed font-medium text-[var(--brutal-ink)]/60 lg:text-lg">
                    Browse curated events from the community, register instantly, and never miss an opportunity to learn
                    and connect.
                </p>
            </div>

            <!-- Full-width event discovery SVG -->
            <div
                :class="[
                    'mx-auto mt-16 w-full max-w-2xl transition-all delay-300 duration-700',
                    visible ? 'translate-y-0 opacity-100' : 'translate-y-12 opacity-0',
                ]"
            >
                <svg viewBox="0 0 800 320" fill="none" xmlns="http://www.w3.org/2000/svg" class="evt-svg w-full">
                    <defs>
                        <filter id="es" x="-8%" y="-8%" width="116%" height="124%">
                            <feDropShadow dx="0" dy="4" stdDeviation="12" flood-color="#111827" flood-opacity="0.06" />
                        </filter>
                    </defs>

                    <!-- Main container -->
                    <g filter="url(#es)">
                        <rect
                            x="40"
                            y="10"
                            width="720"
                            height="300"
                            rx="18"
                            fill="white"
                            stroke="#E5E7EB"
                            stroke-width="1"
                        />
                        <!-- Top bar -->
                        <rect x="40" y="10" width="720" height="42" rx="18" fill="#F9FAFB" />
                        <rect x="40" y="44" width="720" height="8" fill="#F9FAFB" />
                        <circle cx="66" cy="32" r="4" fill="#E5E7EB" />
                        <circle cx="80" cy="32" r="4" fill="#E5E7EB" />
                        <circle cx="94" cy="32" r="4" fill="#E5E7EB" />
                        <!-- Tab navigation -->
                        <rect x="300" y="22" width="56" height="20" rx="6" fill="#0A84DC" opacity="0.08" />
                        <rect x="310" y="29" width="36" height="6" rx="3" fill="#0A84DC" opacity="0.5" />
                        <rect x="368" y="29" width="28" height="6" rx="3" fill="#E5E7EB" />
                        <rect x="408" y="29" width="36" height="6" rx="3" fill="#E5E7EB" />
                        <!-- Search -->
                        <rect
                            x="580"
                            y="22"
                            width="160"
                            height="20"
                            rx="7"
                            fill="white"
                            stroke="#E5E7EB"
                            stroke-width="0.6"
                        />
                        <circle cx="596" cy="32" r="4" fill="none" stroke="#9CA3AF" stroke-width="0.8" />
                        <line x1="599" y1="35" x2="602" y2="38" stroke="#9CA3AF" stroke-width="0.8" />
                    </g>

                    <!-- Event cards row -->
                    <g class="evc-1">
                        <rect
                            x="66"
                            y="68"
                            width="220"
                            height="110"
                            rx="12"
                            fill="white"
                            stroke="#E5E7EB"
                            stroke-width="0.8"
                        />
                        <rect x="66" y="68" width="220" height="44" rx="12" fill="#0A84DC" opacity="0.03" />
                        <rect x="66" y="104" width="220" height="8" fill="#0A84DC" opacity="0.03" />
                        <rect x="80" y="126" width="100" height="7" rx="3.5" fill="#111827" opacity="0.5" />
                        <rect x="80" y="140" width="160" height="5" rx="2.5" fill="#F1F5F9" />
                        <rect x="80" y="152" width="48" height="14" rx="7" fill="#059669" opacity="0.08" />
                        <text
                            x="88"
                            y="163"
                            font-size="6.5"
                            fill="#059669"
                            font-weight="600"
                            font-family="system-ui, sans-serif"
                        >
                            Open
                        </text>
                        <text x="210" y="163" font-size="7" fill="#9CA3AF" font-family="system-ui, sans-serif">
                            342
                        </text>
                    </g>

                    <g class="evc-2">
                        <rect
                            x="298"
                            y="68"
                            width="220"
                            height="110"
                            rx="12"
                            fill="white"
                            stroke="#E5E7EB"
                            stroke-width="0.8"
                        />
                        <rect x="298" y="68" width="220" height="44" rx="12" fill="#7C3AED" opacity="0.03" />
                        <rect x="298" y="104" width="220" height="8" fill="#7C3AED" opacity="0.03" />
                        <rect x="312" y="126" width="120" height="7" rx="3.5" fill="#111827" opacity="0.5" />
                        <rect x="312" y="140" width="140" height="5" rx="2.5" fill="#F1F5F9" />
                        <rect x="312" y="152" width="48" height="14" rx="7" fill="#7C3AED" opacity="0.08" />
                        <text
                            x="320"
                            y="163"
                            font-size="6.5"
                            fill="#7C3AED"
                            font-weight="600"
                            font-family="system-ui, sans-serif"
                        >
                            Soon
                        </text>
                        <text x="442" y="163" font-size="7" fill="#9CA3AF" font-family="system-ui, sans-serif">
                            189
                        </text>
                    </g>

                    <g class="evc-3">
                        <rect
                            x="530"
                            y="68"
                            width="220"
                            height="110"
                            rx="12"
                            fill="white"
                            stroke="#E5E7EB"
                            stroke-width="0.8"
                        />
                        <rect x="530" y="68" width="220" height="44" rx="12" fill="#059669" opacity="0.03" />
                        <rect x="530" y="104" width="220" height="8" fill="#059669" opacity="0.03" />
                        <rect x="544" y="126" width="110" height="7" rx="3.5" fill="#111827" opacity="0.5" />
                        <rect x="544" y="140" width="150" height="5" rx="2.5" fill="#F1F5F9" />
                        <rect x="544" y="152" width="48" height="14" rx="7" fill="#059669" opacity="0.08" />
                        <text
                            x="552"
                            y="163"
                            font-size="6.5"
                            fill="#059669"
                            font-weight="600"
                            font-family="system-ui, sans-serif"
                        >
                            Open
                        </text>
                        <text x="674" y="163" font-size="7" fill="#9CA3AF" font-family="system-ui, sans-serif">
                            1.2K
                        </text>
                    </g>

                    <!-- Bottom stats bar -->
                    <line x1="66" y1="200" x2="734" y2="200" stroke="#F3F4F6" stroke-width="1" />

                    <text
                        x="80"
                        y="228"
                        font-size="18"
                        fill="#0A84DC"
                        font-weight="800"
                        font-family="system-ui, sans-serif"
                    >
                        8
                    </text>
                    <text x="100" y="224" font-size="7" fill="#9CA3AF" font-family="system-ui, sans-serif">total</text>
                    <text x="100" y="234" font-size="7" fill="#9CA3AF" font-family="system-ui, sans-serif">events</text>

                    <line x1="160" y1="210" x2="160" y2="240" stroke="#F3F4F6" stroke-width="1" />

                    <text
                        x="180"
                        y="228"
                        font-size="18"
                        fill="#059669"
                        font-weight="800"
                        font-family="system-ui, sans-serif"
                    >
                        5
                    </text>
                    <text x="200" y="224" font-size="7" fill="#9CA3AF" font-family="system-ui, sans-serif">
                        open for
                    </text>
                    <text x="200" y="234" font-size="7" fill="#9CA3AF" font-family="system-ui, sans-serif">
                        registration
                    </text>

                    <line x1="280" y1="210" x2="280" y2="240" stroke="#F3F4F6" stroke-width="1" />

                    <text
                        x="300"
                        y="228"
                        font-size="18"
                        fill="#111827"
                        font-weight="800"
                        font-family="system-ui, sans-serif"
                    >
                        3.2K
                    </text>
                    <text x="360" y="224" font-size="7" fill="#9CA3AF" font-family="system-ui, sans-serif">total</text>
                    <text x="360" y="234" font-size="7" fill="#9CA3AF" font-family="system-ui, sans-serif">
                        attendees
                    </text>

                    <!-- Mini sparkline -->
                    <polyline
                        points="520,238 540,228 560,234 580,222 600,226 620,216 640,220 660,212 680,218 700,206 720,212"
                        stroke="#0A84DC"
                        stroke-width="1.5"
                        fill="none"
                        opacity="0.25"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        class="evt-spark"
                    />
                    <polygon
                        points="520,238 540,228 560,234 580,222 600,226 620,216 640,220 660,212 680,218 700,206 720,212 720,244 520,244"
                        fill="#0A84DC"
                        opacity="0.02"
                    />

                    <!-- Bottom pagination -->
                    <g>
                        <rect x="342" y="266" width="28" height="24" rx="6" fill="#0A84DC" />
                        <text
                            x="350"
                            y="282"
                            font-size="8"
                            fill="white"
                            font-weight="700"
                            font-family="system-ui, sans-serif"
                        >
                            1
                        </text>
                        <rect
                            x="378"
                            y="266"
                            width="28"
                            height="24"
                            rx="6"
                            fill="#F9FAFB"
                            stroke="#E5E7EB"
                            stroke-width="0.5"
                        />
                        <text
                            x="386"
                            y="282"
                            font-size="8"
                            fill="#9CA3AF"
                            font-weight="600"
                            font-family="system-ui, sans-serif"
                        >
                            2
                        </text>
                        <rect
                            x="414"
                            y="266"
                            width="28"
                            height="24"
                            rx="6"
                            fill="#F9FAFB"
                            stroke="#E5E7EB"
                            stroke-width="0.5"
                        />
                        <text
                            x="422"
                            y="282"
                            font-size="8"
                            fill="#9CA3AF"
                            font-weight="600"
                            font-family="system-ui, sans-serif"
                        >
                            3
                        </text>
                    </g>
                </svg>
            </div>
        </div>
    </section>
</template>

<style scoped>
.evt-underline {
    animation: eUG 1s ease-out 0.8s both;
}
@keyframes eUG {
    from {
        transform: scaleX(0);
        transform-origin: left;
    }
    to {
        transform: scaleX(1);
        transform-origin: left;
    }
}

.evc-1 {
    animation: evcF 6s ease-in-out infinite;
}
.evc-2 {
    animation: evcF 7s ease-in-out infinite;
    animation-delay: -2s;
}
.evc-3 {
    animation: evcF 5.5s ease-in-out infinite;
    animation-delay: -3.5s;
}
@keyframes evcF {
    0%,
    100% {
        transform: translateY(0);
    }
    50% {
        transform: translateY(-3px);
    }
}

.evt-spark {
    stroke-dasharray: 200;
    stroke-dashoffset: 200;
    animation: evtSpk 2s ease-out 1s forwards;
}
@keyframes evtSpk {
    to {
        stroke-dashoffset: 0;
    }
}
</style>
