<script setup lang="ts">
import { ref, onMounted } from 'vue';

const visible = ref(false);
onMounted(() => setTimeout(() => (visible.value = true), 100));
</script>

<template>
    <section class="relative overflow-hidden bg-white pt-32 pb-24 lg:pt-40 lg:pb-28">
        <div class="pointer-events-none absolute inset-0 opacity-[0.025]">
            <svg width="100%" height="100%">
                <defs>
                    <pattern id="feat-dots" width="20" height="20" patternUnits="userSpaceOnUse">
                        <circle cx="2" cy="2" r="0.6" fill="#111827" />
                    </pattern>
                </defs>
                <rect width="100%" height="100%" fill="url(#feat-dots)" />
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
                    class="text-[2.75rem] leading-[1.1] font-semibold tracking-tight text-[#111827] sm:text-5xl lg:text-[3.75rem]"
                >
                    Every tool you need,<br />
                    <span class="relative inline-block">
                        <span class="text-[#0A84DC]">one platform</span>
                        <span
                            class="feat-underline absolute -bottom-1 left-0 h-[3px] w-full rounded-full bg-[#0A84DC]/30"
                        ></span>
                    </span>
                </h1>

                <p class="mx-auto mt-6 max-w-lg text-base leading-relaxed text-[#6B7280] lg:text-lg">
                    From form building to real-time analytics, DForm gives event organizers everything they need to
                    deliver seamless experiences.
                </p>
            </div>

            <!-- Full-width feature module SVG -->
            <div
                :class="[
                    'mx-auto mt-16 w-full max-w-2xl transition-all delay-300 duration-700',
                    visible ? 'translate-y-0 opacity-100' : 'translate-y-12 opacity-0',
                ]"
            >
                <svg viewBox="0 0 800 360" fill="none" xmlns="http://www.w3.org/2000/svg" class="feat-svg w-full">
                    <defs>
                        <filter id="fs" x="-8%" y="-8%" width="116%" height="124%">
                            <feDropShadow dx="0" dy="4" stdDeviation="12" flood-color="#111827" flood-opacity="0.06" />
                        </filter>
                    </defs>

                    <!-- Blueprint bg -->
                    <rect
                        x="20"
                        y="10"
                        width="760"
                        height="340"
                        rx="20"
                        fill="#F9FAFB"
                        stroke="#E5E7EB"
                        stroke-width="0.5"
                        stroke-dasharray="4 3"
                        opacity="0.7"
                    />
                    <!-- Grid lines -->
                    <line
                        x1="280"
                        y1="10"
                        x2="280"
                        y2="350"
                        stroke="#E5E7EB"
                        stroke-width="0.3"
                        opacity="0.4"
                        stroke-dasharray="2 4"
                    />
                    <line
                        x1="540"
                        y1="10"
                        x2="540"
                        y2="350"
                        stroke="#E5E7EB"
                        stroke-width="0.3"
                        opacity="0.4"
                        stroke-dasharray="2 4"
                    />
                    <line
                        x1="20"
                        y1="185"
                        x2="780"
                        y2="185"
                        stroke="#E5E7EB"
                        stroke-width="0.3"
                        opacity="0.4"
                        stroke-dasharray="2 4"
                    />

                    <!-- Module 1: Form Builder (top-left) -->
                    <g class="fm-1" filter="url(#fs)">
                        <rect
                            x="50"
                            y="30"
                            width="210"
                            height="140"
                            rx="14"
                            fill="white"
                            stroke="#E5E7EB"
                            stroke-width="0.8"
                        />
                        <rect x="66" y="46" width="52" height="7" rx="3.5" fill="#0A84DC" opacity="0.5" />
                        <rect
                            x="66"
                            y="62"
                            width="178"
                            height="14"
                            rx="5"
                            fill="#F9FAFB"
                            stroke="#E5E7EB"
                            stroke-width="0.4"
                        />
                        <rect x="74" y="66" width="44" height="6" rx="3" fill="#E5E7EB" />
                        <rect
                            x="66"
                            y="84"
                            width="178"
                            height="14"
                            rx="5"
                            fill="#F9FAFB"
                            stroke="#E5E7EB"
                            stroke-width="0.4"
                        />
                        <rect x="74" y="88" width="56" height="6" rx="3" fill="#E5E7EB" />
                        <rect
                            x="66"
                            y="106"
                            width="86"
                            height="12"
                            rx="4"
                            fill="#F9FAFB"
                            stroke="#E5E7EB"
                            stroke-width="0.4"
                        />
                        <rect
                            x="158"
                            y="106"
                            width="86"
                            height="12"
                            rx="4"
                            fill="#F9FAFB"
                            stroke="#E5E7EB"
                            stroke-width="0.4"
                        />
                        <rect x="66" y="130" width="60" height="22" rx="7" fill="#0A84DC" />
                        <text
                            x="78"
                            y="145"
                            font-size="8"
                            fill="white"
                            font-weight="600"
                            font-family="system-ui, sans-serif"
                        >
                            Create
                        </text>
                        <rect x="150" y="66" width="2" height="7" fill="#0A84DC" opacity="0.6" class="fm-cursor" />
                    </g>

                    <!-- Module 3: Analytics (top-right) -->
                    <g class="fm-3" filter="url(#fs)">
                        <rect
                            x="540"
                            y="30"
                            width="210"
                            height="140"
                            rx="14"
                            fill="white"
                            stroke="#E5E7EB"
                            stroke-width="0.8"
                        />
                        <rect x="556" y="46" width="52" height="7" rx="3.5" fill="#0A84DC" opacity="0.5" />
                        <text
                            x="556"
                            y="78"
                            font-size="20"
                            fill="#0A84DC"
                            font-weight="800"
                            font-family="system-ui, sans-serif"
                        >
                            89%
                        </text>
                        <text x="616" y="72" font-size="7" fill="#9CA3AF" font-family="system-ui, sans-serif">
                            completion
                        </text>
                        <text x="616" y="82" font-size="7" fill="#9CA3AF" font-family="system-ui, sans-serif">
                            rate
                        </text>
                        <polyline
                            points="558,120 582,114 606,122 630,106 654,110 678,96 702,102 726,88"
                            stroke="#0A84DC"
                            stroke-width="1.5"
                            fill="none"
                            opacity="0.3"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            class="fm-chart"
                        />
                        <polygon
                            points="558,120 582,114 606,122 630,106 654,110 678,96 702,102 726,88 726,150 558,150"
                            fill="#0A84DC"
                            opacity="0.03"
                        />
                        <circle
                            cx="678"
                            cy="96"
                            r="3"
                            fill="white"
                            stroke="#0A84DC"
                            stroke-width="1.5"
                            class="fm-cdot"
                        />
                    </g>

                    <!-- Module 4: Team (bottom-left) -->
                    <g class="fm-4" filter="url(#fs)">
                        <rect
                            x="50"
                            y="200"
                            width="210"
                            height="130"
                            rx="14"
                            fill="white"
                            stroke="#E5E7EB"
                            stroke-width="0.8"
                        />
                        <rect x="66" y="216" width="56" height="7" rx="3.5" fill="#0A84DC" opacity="0.5" />
                        <circle cx="100" cy="260" r="14" fill="#0A84DC" opacity="0.04" />
                        <circle cx="92" cy="256" r="8" fill="white" stroke="#E5E7EB" stroke-width="0.8" />
                        <circle cx="104" cy="262" r="8" fill="white" stroke="#E5E7EB" stroke-width="0.8" />
                        <circle cx="116" cy="256" r="8" fill="white" stroke="#E5E7EB" stroke-width="0.8" />
                        <circle
                            cx="128"
                            cy="262"
                            r="8"
                            fill="white"
                            stroke="#0A84DC"
                            stroke-width="0.8"
                            stroke-dasharray="2 2"
                            opacity="0.4"
                            class="fm-invite"
                        />
                        <text x="125" y="266" font-size="10" fill="#0A84DC" font-weight="500" opacity="0.5">+</text>
                        <circle cx="96" cy="250" r="2" fill="#059669" class="fm-online" />
                        <rect x="66" y="290" width="178" height="8" rx="4" fill="#F9FAFB" />
                        <rect x="66" y="304" width="130" height="8" rx="4" fill="#F9FAFB" />
                    </g>

                    <!-- Module 5: Integrations (bottom-center) -->
                    <g class="fm-5" filter="url(#fs)">
                        <rect
                            x="295"
                            y="200"
                            width="210"
                            height="130"
                            rx="14"
                            fill="white"
                            stroke="#E5E7EB"
                            stroke-width="0.8"
                        />
                        <rect x="311" y="216" width="64" height="7" rx="3.5" fill="#0A84DC" opacity="0.5" />
                        <g>
                            <rect x="311" y="236" width="48" height="36" rx="8" fill="#059669" opacity="0.04" />
                            <text
                                x="324"
                                y="260"
                                font-size="10"
                                fill="#059669"
                                font-weight="800"
                                font-family="system-ui, sans-serif"
                            >
                                G
                            </text>
                            <rect x="367" y="236" width="48" height="36" rx="8" fill="#7C3AED" opacity="0.04" />
                            <text
                                x="383"
                                y="260"
                                font-size="10"
                                fill="#7C3AED"
                                font-weight="800"
                                font-family="system-ui, sans-serif"
                            >
                                S
                            </text>
                            <rect x="423" y="236" width="48" height="36" rx="8" fill="#5B65EA" opacity="0.04" />
                            <text
                                x="439"
                                y="260"
                                font-size="10"
                                fill="#5B65EA"
                                font-weight="800"
                                font-family="system-ui, sans-serif"
                            >
                                D
                            </text>
                        </g>
                        <rect x="311" y="286" width="178" height="8" rx="4" fill="#F9FAFB" />
                        <rect x="311" y="302" width="120" height="8" rx="4" fill="#F9FAFB" />
                    </g>

                    <!-- Module 6: Security (bottom-right) -->
                    <g class="fm-6" filter="url(#fs)">
                        <rect
                            x="540"
                            y="200"
                            width="210"
                            height="130"
                            rx="14"
                            fill="white"
                            stroke="#E5E7EB"
                            stroke-width="0.8"
                        />
                        <rect x="556" y="216" width="52" height="7" rx="3.5" fill="#0A84DC" opacity="0.5" />
                        <circle cx="650" cy="270" r="26" fill="#059669" opacity="0.04" class="fm-shield-ring" />
                        <svg
                            x="636"
                            y="256"
                            width="28"
                            height="28"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="#059669"
                            stroke-width="1.5"
                            stroke-linecap="round"
                        >
                            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10" />
                            <path d="m9 12 2 2 4-4" />
                        </svg>
                        <rect x="556" y="290" width="178" height="8" rx="4" fill="#F9FAFB" />
                        <rect x="556" y="304" width="140" height="8" rx="4" fill="#F9FAFB" />
                    </g>

                    <!-- Connection particles -->
                    <circle r="2" fill="#0A84DC" opacity="0.4" class="fmp-1" />
                    <circle r="2" fill="#0A84DC" opacity="0.4" class="fmp-2" />
                    <circle r="2" fill="#0A84DC" opacity="0.4" class="fmp-3" />
                </svg>
            </div>
        </div>
    </section>
</template>

<style scoped>
.feat-underline {
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

.fm-1 {
    animation: fmFloat 6s ease-in-out infinite;
}
.fm-2 {
    animation: fmFloat 7s ease-in-out infinite;
    animation-delay: -1.5s;
}
.fm-3 {
    animation: fmFloat 5.5s ease-in-out infinite;
    animation-delay: -3s;
}
.fm-4 {
    animation: fmFloat 6.5s ease-in-out infinite;
    animation-delay: -2s;
}
.fm-5 {
    animation: fmFloat 5s ease-in-out infinite;
    animation-delay: -1s;
}
.fm-6 {
    animation: fmFloat 7s ease-in-out infinite;
    animation-delay: -3.5s;
}
@keyframes fmFloat {
    0%,
    100% {
        transform: translateY(0);
    }
    50% {
        transform: translateY(-4px);
    }
}

.fm-cursor {
    animation: fmCur 1s step-end infinite;
}
@keyframes fmCur {
    0%,
    100% {
        opacity: 0.6;
    }
    50% {
        opacity: 0;
    }
}

.fm-date-hl {
    animation: fmDH 4s ease-in-out infinite;
}
@keyframes fmDH {
    0%,
    100% {
        opacity: 1;
    }
    50% {
        opacity: 0.7;
    }
}

.fm-evdot {
    animation: fmED 3s ease-in-out infinite;
}
.fm-evdot-2 {
    animation: fmED 3s ease-in-out infinite;
    animation-delay: -1.5s;
}
@keyframes fmED {
    0%,
    100% {
        r: 1.5;
    }
    50% {
        r: 2.5;
    }
}

.fm-chart {
    stroke-dasharray: 200;
    stroke-dashoffset: 200;
    animation: fmCD 2s ease-out 1s forwards;
}
@keyframes fmCD {
    to {
        stroke-dashoffset: 0;
    }
}

.fm-cdot {
    animation: fmCDP 4s ease-in-out infinite;
}
@keyframes fmCDP {
    0%,
    100% {
        r: 3;
    }
    50% {
        r: 4;
    }
}

.fm-invite {
    animation: fmInv 3s ease-in-out infinite;
}
@keyframes fmInv {
    0%,
    100% {
        opacity: 0.4;
    }
    50% {
        opacity: 0.7;
    }
}

.fm-online {
    animation: fmOn 2s ease-in-out infinite;
}
@keyframes fmOn {
    0%,
    100% {
        r: 2;
        opacity: 1;
    }
    50% {
        r: 3;
        opacity: 0.5;
    }
}

.fm-shield-ring {
    animation: fmSR 4s ease-in-out infinite;
}
@keyframes fmSR {
    0%,
    100% {
        r: 26;
        opacity: 0.04;
    }
    50% {
        r: 30;
        opacity: 0.08;
    }
}

.fmp-1 {
    offset-path: path('M260 115 L295 115');
    animation: fmpM 2s ease-in-out infinite;
}
.fmp-2 {
    offset-path: path('M505 115 L540 115');
    animation: fmpM 2.2s ease-in-out infinite;
    animation-delay: -0.5s;
}
.fmp-3 {
    offset-path: path('M260 265 L295 265');
    animation: fmpM 1.8s ease-in-out infinite;
    animation-delay: -1s;
}
@keyframes fmpM {
    0% {
        offset-distance: 0%;
        opacity: 0;
    }
    30% {
        opacity: 0.5;
    }
    70% {
        opacity: 0.5;
    }
    100% {
        offset-distance: 100%;
        opacity: 0;
    }
}
</style>
