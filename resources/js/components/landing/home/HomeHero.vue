<script setup lang="ts">
import { ref, onMounted } from 'vue';

const visible = ref(false);
onMounted(() => setTimeout(() => (visible.value = true), 100));
</script>

<template>
    <section class="relative overflow-hidden bg-white pt-32 pb-24 lg:pt-40 lg:pb-32">
        <!-- Subtle dot grid -->
        <div class="pointer-events-none absolute inset-0 opacity-[0.025]">
            <svg width="100%" height="100%">
                <defs>
                    <pattern id="home-dots" width="20" height="20" patternUnits="userSpaceOnUse">
                        <circle cx="2" cy="2" r="0.6" fill="#111827" />
                    </pattern>
                </defs>
                <rect width="100%" height="100%" fill="url(#home-dots)" />
            </svg>
        </div>

        <div class="max-w-8xl relative mx-auto flex justify-between px-6 lg:px-8">
            <!-- Centered hero text -->
            <div
                :class="[
                    'mx-auto w-full max-w-3xl text-center transition-all duration-700 ease-out',
                    visible ? 'translate-y-0 opacity-100' : 'translate-y-8 opacity-0',
                ]"
            >
                <h1
                    class="text-[2.75rem] leading-[1.1] font-semibold tracking-tight text-[#111827] sm:text-5xl lg:text-[3.75rem]"
                >
                    The smarter way to<br />
                    <span class="relative inline-block">
                        <span class="text-[#0A84DC]">organize events</span>
                        <span
                            class="hero-underline absolute -bottom-1 left-0 h-[3px] w-full rounded-full bg-[#0A84DC]/30"
                        ></span>
                    </span>
                </h1>

                <p class="mx-auto mt-6 max-w-lg text-base leading-relaxed text-[#6B7280] lg:text-lg">
                    Create custom registration forms, manage attendees in real-time, and turn responses into actionable
                    insights — all from one dashboard.
                </p>

                <div class="mt-10 flex flex-wrap items-center justify-center gap-4">
                    <a
                        href="/auth"
                        class="group inline-flex items-center gap-2.5 rounded-xl bg-[#0A84DC] px-8 py-3.5 text-sm font-semibold text-white shadow-sm transition-all duration-200 hover:bg-[#0972c0] hover:shadow-md active:scale-[0.97]"
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
                        class="inline-flex items-center gap-2 rounded-xl border border-[#E5E7EB] bg-white px-8 py-3.5 text-sm font-semibold text-[#111827] transition-all duration-200 hover:border-[#D1D5DB] hover:bg-[#F9FAFB] active:scale-[0.97]"
                    >
                        See How It Works
                    </a>
                </div>
            </div>

            <!-- Full-width SVG illustration below text -->
            <div
                :class="[
                    'mx-auto mt-16 w-full max-w-2xl transition-all delay-300 duration-700 ease-out',
                    visible ? 'translate-y-0 opacity-100' : 'translate-y-12 opacity-0',
                ]"
            >
                <svg viewBox="0 0 800 380" fill="none" xmlns="http://www.w3.org/2000/svg" class="home-svg w-full">
                    <defs>
                        <filter id="hs" x="-8%" y="-8%" width="116%" height="124%">
                            <feDropShadow dx="0" dy="4" stdDeviation="12" flood-color="#111827" flood-opacity="0.06" />
                        </filter>
                        <filter id="hg" x="-20%" y="-20%" width="140%" height="140%">
                            <feDropShadow dx="0" dy="0" stdDeviation="5" flood-color="#0A84DC" flood-opacity="0.1" />
                        </filter>
                    </defs>

                    <!-- Main dashboard container -->
                    <g filter="url(#hs)">
                        <rect
                            x="60"
                            y="20"
                            width="680"
                            height="340"
                            rx="20"
                            fill="white"
                            stroke="#E5E7EB"
                            stroke-width="1"
                        />
                        <!-- Top bar -->
                        <rect x="60" y="20" width="680" height="44" rx="20" fill="#F9FAFB" />
                        <rect x="60" y="56" width="680" height="8" fill="#F9FAFB" />
                        <!-- Window dots -->
                        <circle cx="86" cy="42" r="4" fill="#E5E7EB" />
                        <circle cx="100" cy="42" r="4" fill="#E5E7EB" />
                        <circle cx="114" cy="42" r="4" fill="#E5E7EB" />
                        <!-- Nav tabs -->
                        <rect x="260" y="34" width="48" height="16" rx="5" fill="#0A84DC" opacity="0.08" />
                        <rect x="268" y="39" width="32" height="6" rx="3" fill="#0A84DC" opacity="0.5" />
                        <rect x="320" y="39" width="32" height="6" rx="3" fill="#E5E7EB" />
                        <rect x="364" y="39" width="32" height="6" rx="3" fill="#E5E7EB" />
                        <!-- Search bar -->
                        <rect
                            x="560"
                            y="32"
                            width="160"
                            height="22"
                            rx="8"
                            fill="white"
                            stroke="#E5E7EB"
                            stroke-width="0.6"
                        />
                        <circle cx="576" cy="43" r="4" fill="none" stroke="#9CA3AF" stroke-width="0.8" />
                        <line
                            x1="579"
                            y1="46"
                            x2="582"
                            y2="49"
                            stroke="#9CA3AF"
                            stroke-width="0.8"
                            stroke-linecap="round"
                        />
                    </g>

                    <!-- Left sidebar -->
                    <rect x="62" y="66" width="160" height="292" fill="#FAFBFC" />
                    <line x1="222" y1="66" x2="222" y2="358" stroke="#F3F4F6" stroke-width="1" />
                    <!-- Sidebar items -->
                    <rect x="78" y="86" width="128" height="28" rx="8" fill="#0A84DC" opacity="0.06" />
                    <rect x="78" y="86" width="3" height="28" rx="1.5" fill="#0A84DC" />
                    <circle cx="96" cy="100" r="4" fill="#0A84DC" opacity="0.2" />
                    <rect x="108" y="96" width="56" height="6" rx="3" fill="#0A84DC" opacity="0.4" />
                    <circle cx="96" cy="130" r="4" fill="#E5E7EB" />
                    <rect x="108" y="126" width="48" height="6" rx="3" fill="#E5E7EB" />
                    <circle cx="96" cy="156" r="4" fill="#E5E7EB" />
                    <rect x="108" y="152" width="60" height="6" rx="3" fill="#E5E7EB" />
                    <circle cx="96" cy="182" r="4" fill="#E5E7EB" />
                    <rect x="108" y="178" width="40" height="6" rx="3" fill="#E5E7EB" />
                    <!-- Sidebar divider -->
                    <line x1="78" y1="206" x2="206" y2="206" stroke="#F3F4F6" stroke-width="1" />
                    <circle cx="96" cy="228" r="4" fill="#E5E7EB" />
                    <rect x="108" y="224" width="52" height="6" rx="3" fill="#E5E7EB" />

                    <!-- Main content area — Event list -->
                    <!-- Header -->
                    <rect x="244" y="82" width="80" height="8" rx="4" fill="#111827" opacity="0.7" />
                    <rect x="616" y="78" width="100" height="22" rx="7" fill="#0A84DC" />
                    <text
                        x="636"
                        y="93"
                        font-size="8.5"
                        fill="white"
                        font-weight="600"
                        font-family="system-ui, sans-serif"
                    >
                        + New Event
                    </text>

                    <!-- Event cards row -->
                    <g class="card-1">
                        <rect
                            x="244"
                            y="110"
                            width="230"
                            height="108"
                            rx="12"
                            fill="white"
                            stroke="#E5E7EB"
                            stroke-width="0.8"
                        />
                        <!-- Image placeholder -->
                        <rect x="244" y="110" width="230" height="50" rx="12" fill="#0A84DC" opacity="0.04" />
                        <rect x="244" y="152" width="230" height="8" fill="#0A84DC" opacity="0.04" />
                        <!-- Content -->
                        <rect x="258" y="170" width="100" height="7" rx="3.5" fill="#111827" opacity="0.6" />
                        <rect x="258" y="184" width="160" height="5" rx="2.5" fill="#F1F5F9" />
                        <rect x="258" y="196" width="50" height="14" rx="7" fill="#059669" opacity="0.08" />
                        <text
                            x="268"
                            y="207"
                            font-size="6.5"
                            fill="#059669"
                            font-weight="600"
                            font-family="system-ui, sans-serif"
                        >
                            Open
                        </text>
                        <text x="406" y="207" font-size="7" fill="#9CA3AF" font-family="system-ui, sans-serif">
                            342 attendees
                        </text>
                    </g>

                    <g class="card-2">
                        <rect
                            x="486"
                            y="110"
                            width="230"
                            height="108"
                            rx="12"
                            fill="white"
                            stroke="#E5E7EB"
                            stroke-width="0.8"
                        />
                        <rect x="486" y="110" width="230" height="50" rx="12" fill="#7C3AED" opacity="0.04" />
                        <rect x="486" y="152" width="230" height="8" fill="#7C3AED" opacity="0.04" />
                        <rect x="500" y="170" width="120" height="7" rx="3.5" fill="#111827" opacity="0.6" />
                        <rect x="500" y="184" width="140" height="5" rx="2.5" fill="#F1F5F9" />
                        <rect x="500" y="196" width="50" height="14" rx="7" fill="#7C3AED" opacity="0.08" />
                        <text
                            x="508"
                            y="207"
                            font-size="6.5"
                            fill="#7C3AED"
                            font-weight="600"
                            font-family="system-ui, sans-serif"
                        >
                            Soon
                        </text>
                        <text x="648" y="207" font-size="7" fill="#9CA3AF" font-family="system-ui, sans-serif">
                            189 attendees
                        </text>
                    </g>

                    <!-- Analytics area -->
                    <rect
                        x="244"
                        y="236"
                        width="472"
                        height="110"
                        rx="12"
                        fill="white"
                        stroke="#E5E7EB"
                        stroke-width="0.8"
                        class="analytics-card"
                    />
                    <rect x="262" y="252" width="52" height="7" rx="3.5" fill="#111827" opacity="0.5" />

                    <!-- Mini bar chart -->
                    <rect x="490" y="310" width="18" height="24" rx="3" fill="#0A84DC" opacity="0.12" class="bar-1" />
                    <rect x="516" y="298" width="18" height="36" rx="3" fill="#0A84DC" opacity="0.2" class="bar-2" />
                    <rect x="542" y="286" width="18" height="48" rx="3" fill="#0A84DC" opacity="0.3" class="bar-3" />
                    <rect x="568" y="278" width="18" height="56" rx="3" fill="#0A84DC" opacity="0.45" class="bar-4" />
                    <rect x="594" y="266" width="18" height="68" rx="3" fill="#0A84DC" opacity="0.6" class="bar-5" />
                    <rect x="620" y="258" width="18" height="76" rx="3" fill="#0A84DC" opacity="0.8" class="bar-6" />
                    <rect x="646" y="252" width="18" height="82" rx="3" fill="#0A84DC" class="bar-7" />
                    <!-- Chart line overlay -->
                    <polyline
                        points="499,306 525,294 551,282 577,274 603,262 629,254 655,248"
                        stroke="#0A84DC"
                        stroke-width="1.5"
                        fill="none"
                        opacity="0.3"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        class="trend-line"
                    />
                    <!-- Chart dots -->
                    <circle cx="551" cy="282" r="3" fill="white" stroke="#0A84DC" stroke-width="1.5" class="tdot-1" />
                    <circle cx="629" cy="254" r="3" fill="white" stroke="#0A84DC" stroke-width="1.5" class="tdot-2" />

                    <!-- Floating notification badge -->
                    <g class="notif-float" filter="url(#hs)">
                        <rect
                            x="670"
                            y="6"
                            width="120"
                            height="44"
                            rx="12"
                            fill="white"
                            stroke="#E5E7EB"
                            stroke-width="0.8"
                        />
                        <circle cx="690" cy="28" r="8" fill="#059669" opacity="0.1" />
                        <path
                            d="M686 28l3 3 6-6"
                            stroke="#059669"
                            stroke-width="1.5"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                        />
                        <rect x="706" y="22" width="60" height="5" rx="2.5" fill="#F1F5F9" />
                        <rect x="706" y="31" width="40" height="4" rx="2" fill="#F1F5F9" />
                    </g>

                    <!-- Cursor -->
                    <g class="cursor-move">
                        <circle cx="400" cy="160" r="6" fill="#0A84DC" opacity="0.15" />
                        <circle cx="400" cy="160" r="2.5" fill="#0A84DC" />
                    </g>
                </svg>
            </div>
        </div>
    </section>
</template>

<style scoped>
.hero-underline {
    animation: underlineGrow 1s ease-out 0.8s both;
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

/* Cards */
.card-1 {
    animation: cFloat 6s ease-in-out infinite;
}
.card-2 {
    animation: cFloat 7s ease-in-out infinite;
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

.analytics-card {
    animation: aPulse 6s ease-in-out infinite;
}
@keyframes aPulse {
    0%,
    100% {
        stroke: #e5e7eb;
    }
    50% {
        stroke: #0a84dc20;
    }
}

/* Bar chart animation */
.bar-1 {
    animation: bGrow 3s ease-in-out infinite;
}
.bar-2 {
    animation: bGrow 3s ease-in-out infinite;
    animation-delay: -0.3s;
}
.bar-3 {
    animation: bGrow 3s ease-in-out infinite;
    animation-delay: -0.6s;
}
.bar-4 {
    animation: bGrow 3s ease-in-out infinite;
    animation-delay: -0.9s;
}
.bar-5 {
    animation: bGrow 3s ease-in-out infinite;
    animation-delay: -1.2s;
}
.bar-6 {
    animation: bGrow 3s ease-in-out infinite;
    animation-delay: -1.5s;
}
.bar-7 {
    animation: bGrow 3s ease-in-out infinite;
    animation-delay: -1.8s;
}
@keyframes bGrow {
    0%,
    100% {
        opacity: var(--tw-bg-opacity, 0.8);
    }
    50% {
        opacity: calc(var(--tw-bg-opacity, 0.8) * 0.6);
    }
}

/* Trend line */
.trend-line {
    stroke-dasharray: 200;
    stroke-dashoffset: 200;
    animation: tLine 2s ease-out 1.2s forwards;
}
@keyframes tLine {
    to {
        stroke-dashoffset: 0;
    }
}

.tdot-1 {
    animation: tDot 4s ease-in-out infinite;
}
.tdot-2 {
    animation: tDot 4s ease-in-out infinite;
    animation-delay: -2s;
}
@keyframes tDot {
    0%,
    100% {
        r: 3;
    }
    50% {
        r: 4;
    }
}

/* Notification */
.notif-float {
    animation: nFloat 5s ease-in-out infinite;
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

/* Cursor */
.cursor-move {
    animation: cursorAnim 8s ease-in-out infinite;
}
@keyframes cursorAnim {
    0%,
    100% {
        transform: translate(0, 0);
    }
    25% {
        transform: translate(180px, 60px);
    }
    50% {
        transform: translate(240px, 100px);
    }
    75% {
        transform: translate(-60px, 40px);
    }
}
</style>
