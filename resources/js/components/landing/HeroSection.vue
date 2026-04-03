<script setup lang="ts">
import { onMounted, ref } from 'vue';

const isVisible = ref(false);
onMounted(() => {
    setTimeout(() => (isVisible.value = true), 100);
});
</script>

<template>
    <section class="relative overflow-hidden bg-white pt-28 pb-20 lg:pt-36 lg:pb-28">
        <!-- Subtle grid background -->
        <div class="pointer-events-none absolute inset-0 opacity-[0.03]">
            <svg width="100%" height="100%">
                <defs>
                    <pattern id="grid" width="40" height="40" patternUnits="userSpaceOnUse">
                        <path d="M 40 0 L 0 0 0 40" fill="none" stroke="#000" stroke-width="1" />
                    </pattern>
                </defs>
                <rect width="100%" height="100%" fill="url(#grid)" />
            </svg>
        </div>

        <!-- Gradient orb -->
        <div class="pointer-events-none absolute -top-40 -right-40 h-[600px] w-[600px] rounded-full bg-[#0A84DC]/5 blur-3xl" />
        <div class="pointer-events-none absolute -bottom-20 -left-40 h-[400px] w-[400px] rounded-full bg-[#0A84DC]/3 blur-3xl" />

        <div class="relative mx-auto max-w-7xl px-6 lg:px-8">
            <div class="grid items-center gap-16 lg:grid-cols-2 lg:gap-20">
                <!-- Left: Copy -->
                <div
                    :class="[
                        'transition-all duration-700 ease-out',
                        isVisible ? 'translate-y-0 opacity-100' : 'translate-y-8 opacity-0',
                    ]"
                >
                    <div class="mb-6 inline-flex items-center gap-2 rounded-full border border-[#0A84DC]/20 bg-[#0A84DC]/5 px-4 py-1.5">
                        <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-[#0A84DC]" />
                        <span class="text-xs font-semibold tracking-wide text-[#0A84DC] uppercase">Event Platform</span>
                    </div>

                    <h1 class="text-4xl leading-[1.1] font-extrabold tracking-tight text-gray-900 sm:text-5xl lg:text-6xl">
                        Create events
                        <span class="relative">
                            <span class="relative z-10 text-[#0A84DC]">effortlessly</span>
                            <svg class="absolute -bottom-1.5 left-0 z-0 w-full" height="10" viewBox="0 0 200 10" preserveAspectRatio="none">
                                <path d="M0 8 Q50 0 100 6 Q150 12 200 4" stroke="#0A84DC" stroke-width="3" fill="none" opacity="0.2" stroke-linecap="round">
                                    <animate attributeName="d" dur="3s" repeatCount="indefinite"
                                        values="M0 8 Q50 0 100 6 Q150 12 200 4;M0 5 Q50 10 100 3 Q150 8 200 6;M0 8 Q50 0 100 6 Q150 12 200 4" />
                                </path>
                            </svg>
                        </span>
                        <br />with powerful forms.
                    </h1>

                    <p class="mt-6 max-w-lg text-lg leading-relaxed text-gray-500">
                        Build registration forms, manage attendees, and collect responses —
                        all in one modern platform designed for event organizers.
                    </p>

                    <div class="mt-10 flex flex-wrap items-center gap-4">
                        <a
                            href="/auth"
                            class="group inline-flex items-center gap-2 rounded-xl bg-[#0A84DC] px-7 py-3.5 text-sm font-semibold text-white shadow-lg shadow-[#0A84DC]/25 transition-all hover:bg-[#0972c0] hover:shadow-xl hover:shadow-[#0A84DC]/30 active:scale-[0.97]"
                        >
                            Start Building
                            <svg class="h-4 w-4 transition-transform group-hover:translate-x-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M5 12h14" /><path d="m12 5 7 7-7 7" />
                            </svg>
                        </a>
                        <a
                            href="#how-it-works"
                            class="inline-flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-7 py-3.5 text-sm font-semibold text-gray-700 transition-all hover:border-gray-300 hover:bg-gray-50 active:scale-[0.97]"
                        >
                            See How It Works
                        </a>
                    </div>

                    <!-- Trust indicators -->
                    <div class="mt-12 flex items-center gap-6">
                        <div class="flex -space-x-2">
                            <div v-for="i in 4" :key="i" class="flex h-8 w-8 items-center justify-center rounded-full border-2 border-white bg-gradient-to-br from-gray-200 to-gray-300 text-[10px] font-bold text-gray-600">
                                {{ ['AR', 'BK', 'CL', 'DM'][i-1] }}
                            </div>
                        </div>
                        <div>
                            <div class="flex items-center gap-1">
                                <svg v-for="i in 5" :key="i" class="h-3.5 w-3.5 text-amber-400" viewBox="0 0 20 20" fill="currentColor">
                                    <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                                </svg>
                            </div>
                            <p class="mt-0.5 text-xs text-gray-400">Trusted by <span class="font-semibold text-gray-600">2,000+</span> organizers</p>
                        </div>
                    </div>
                </div>

                <!-- Right: SVG Hero Illustration -->
                <div
                    :class="[
                        'relative transition-all delay-200 duration-700 ease-out',
                        isVisible ? 'translate-y-0 opacity-100' : 'translate-y-12 opacity-0',
                    ]"
                >
                    <div class="relative mx-auto w-full max-w-lg lg:max-w-none">
                        <svg viewBox="0 0 560 480" fill="none" xmlns="http://www.w3.org/2000/svg" class="w-full drop-shadow-sm">
                            <!-- Background card -->
                            <rect x="60" y="40" width="440" height="400" rx="24" fill="#F8FAFC" stroke="#E2E8F0" stroke-width="1"/>

                            <!-- Animated flowing paths -->
                            <path d="M120 200 C180 200, 200 120, 280 120 C360 120, 380 200, 440 200" stroke="#0A84DC" stroke-width="2" fill="none" opacity="0.15" stroke-linecap="round">
                                <animate attributeName="stroke-dashoffset" from="600" to="0" dur="3s" repeatCount="indefinite" />
                                <animate attributeName="stroke-dasharray" values="0 600;300 300;600 0" dur="3s" repeatCount="indefinite" />
                            </path>
                            <path d="M120 280 C180 280, 220 340, 280 340 C340 340, 360 280, 440 280" stroke="#0A84DC" stroke-width="2" fill="none" opacity="0.15" stroke-linecap="round">
                                <animate attributeName="stroke-dashoffset" from="600" to="0" dur="4s" repeatCount="indefinite" />
                                <animate attributeName="stroke-dasharray" values="0 600;300 300;600 0" dur="4s" repeatCount="indefinite" />
                            </path>
                            <path d="M280 80 C280 140, 200 180, 200 240 C200 300, 280 340, 280 400" stroke="#0A84DC" stroke-width="1.5" fill="none" opacity="0.1" stroke-dasharray="6 4">
                                <animate attributeName="stroke-dashoffset" from="40" to="0" dur="2s" repeatCount="indefinite" />
                            </path>

                            <!-- Node 1: Event Card -->
                            <g>
                                <rect x="90" y="100" width="160" height="100" rx="16" fill="white" stroke="#E2E8F0" stroke-width="1" filter="url(#shadow1)" />
                                <rect x="108" y="118" width="56" height="8" rx="4" fill="#0A84DC" opacity="0.8"/>
                                <rect x="108" y="134" width="124" height="6" rx="3" fill="#E2E8F0"/>
                                <rect x="108" y="148" width="96" height="6" rx="3" fill="#E2E8F0"/>
                                <rect x="108" y="168" width="60" height="20" rx="6" fill="#0A84DC" opacity="0.1"/>
                                <text x="120" y="182" font-size="9" fill="#0A84DC" font-weight="600" font-family="Plus Jakarta Sans, sans-serif">Event</text>
                                <circle cx="230" cy="118" r="14" fill="#0A84DC" opacity="0.08"/>
                                <path d="M224 118h12M230 112v12" stroke="#0A84DC" stroke-width="1.5" stroke-linecap="round"/>
                                <!-- Pulse dot -->
                                <circle cx="250" cy="150" r="4" fill="#0A84DC">
                                    <animate attributeName="r" values="3;6;3" dur="2s" repeatCount="indefinite" />
                                    <animate attributeName="opacity" values="0.8;0.2;0.8" dur="2s" repeatCount="indefinite" />
                                </circle>
                            </g>

                            <!-- Connecting line 1->2 -->
                            <path d="M250 150 C290 150, 290 190, 310 190" stroke="#0A84DC" stroke-width="2" fill="none" stroke-dasharray="4 4" opacity="0.3">
                                <animate attributeName="stroke-dashoffset" from="16" to="0" dur="1s" repeatCount="indefinite" />
                            </path>

                            <!-- Node 2: Form Builder Card -->
                            <g>
                                <rect x="310" y="150" width="160" height="130" rx="16" fill="white" stroke="#E2E8F0" stroke-width="1" filter="url(#shadow1)">
                                    <animate attributeName="y" values="150;146;150" dur="4s" repeatCount="indefinite" />
                                </rect>
                                <rect x="328" y="168" width="70" height="8" rx="4" fill="#0A84DC" opacity="0.8">
                                    <animate attributeName="y" values="168;164;168" dur="4s" repeatCount="indefinite" />
                                </rect>
                                <!-- Form fields -->
                                <rect x="328" y="188" width="124" height="18" rx="6" fill="#F1F5F9" stroke="#E2E8F0" stroke-width="0.5">
                                    <animate attributeName="y" values="188;184;188" dur="4s" repeatCount="indefinite" />
                                </rect>
                                <rect x="328" y="214" width="124" height="18" rx="6" fill="#F1F5F9" stroke="#E2E8F0" stroke-width="0.5">
                                    <animate attributeName="y" values="214;210;214" dur="4s" repeatCount="indefinite" />
                                </rect>
                                <rect x="328" y="240" width="80" height="22" rx="8" fill="#0A84DC">
                                    <animate attributeName="y" values="240;236;240" dur="4s" repeatCount="indefinite" />
                                </rect>
                                <text x="348" y="255" font-size="9" fill="white" font-weight="600" font-family="Plus Jakarta Sans, sans-serif">
                                    Submit
                                    <animate attributeName="y" values="255;251;255" dur="4s" repeatCount="indefinite" />
                                </text>
                                <!-- Pulse dot -->
                                <circle cx="310" cy="240" r="4" fill="#0A84DC">
                                    <animate attributeName="r" values="3;6;3" dur="2.5s" repeatCount="indefinite" />
                                    <animate attributeName="opacity" values="0.8;0.2;0.8" dur="2.5s" repeatCount="indefinite" />
                                </circle>
                            </g>

                            <!-- Connecting line 2->3 -->
                            <path d="M310 240 C270 240, 270 310, 230 320" stroke="#0A84DC" stroke-width="2" fill="none" stroke-dasharray="4 4" opacity="0.3">
                                <animate attributeName="stroke-dashoffset" from="16" to="0" dur="1s" repeatCount="indefinite" />
                            </path>

                            <!-- Node 3: Analytics Card -->
                            <g>
                                <rect x="90" y="290" width="180" height="110" rx="16" fill="white" stroke="#E2E8F0" stroke-width="1" filter="url(#shadow1)" />
                                <rect x="108" y="308" width="64" height="8" rx="4" fill="#0A84DC" opacity="0.8"/>
                                <!-- Mini bar chart -->
                                <rect x="112" y="362" width="16" height="24" rx="3" fill="#0A84DC" opacity="0.15">
                                    <animate attributeName="height" values="24;32;24" dur="3s" repeatCount="indefinite" />
                                    <animate attributeName="y" values="362;354;362" dur="3s" repeatCount="indefinite" />
                                </rect>
                                <rect x="136" y="350" width="16" height="36" rx="3" fill="#0A84DC" opacity="0.3">
                                    <animate attributeName="height" values="36;28;36" dur="3s" repeatCount="indefinite" />
                                    <animate attributeName="y" values="350;358;350" dur="3s" repeatCount="indefinite" />
                                </rect>
                                <rect x="160" y="340" width="16" height="46" rx="3" fill="#0A84DC" opacity="0.5">
                                    <animate attributeName="height" values="46;52;46" dur="3s" repeatCount="indefinite" />
                                    <animate attributeName="y" values="340;334;340" dur="3s" repeatCount="indefinite" />
                                </rect>
                                <rect x="184" y="330" width="16" height="56" rx="3" fill="#0A84DC" opacity="0.7">
                                    <animate attributeName="height" values="56;44;56" dur="3s" repeatCount="indefinite" />
                                    <animate attributeName="y" values="330;342;330" dur="3s" repeatCount="indefinite" />
                                </rect>
                                <rect x="208" y="326" width="16" height="60" rx="3" fill="#0A84DC">
                                    <animate attributeName="height" values="60;66;60" dur="3s" repeatCount="indefinite" />
                                    <animate attributeName="y" values="326;320;326" dur="3s" repeatCount="indefinite" />
                                </rect>
                                <!-- Count -->
                                <text x="232" y="316" font-size="20" fill="#0A84DC" font-weight="800" font-family="Plus Jakarta Sans, sans-serif">247</text>
                                <text x="234" y="330" font-size="8" fill="#94A3B8" font-family="Plus Jakarta Sans, sans-serif">responses</text>
                            </g>

                            <!-- Floating badge -->
                            <g>
                                <rect x="380" y="60" width="100" height="36" rx="18" fill="white" stroke="#E2E8F0" stroke-width="1" filter="url(#shadow1)">
                                    <animate attributeName="y" values="60;54;60" dur="5s" repeatCount="indefinite" />
                                </rect>
                                <circle cx="400" cy="78" r="8" fill="#10B981" opacity="0.15">
                                    <animate attributeName="cy" values="78;72;78" dur="5s" repeatCount="indefinite" />
                                </circle>
                                <circle cx="400" cy="78" r="3" fill="#10B981">
                                    <animate attributeName="cy" values="78;72;78" dur="5s" repeatCount="indefinite" />
                                </circle>
                                <text x="416" y="82" font-size="9" fill="#374151" font-weight="600" font-family="Plus Jakarta Sans, sans-serif">
                                    Live
                                    <animate attributeName="y" values="82;76;82" dur="5s" repeatCount="indefinite" />
                                </text>
                            </g>

                            <!-- Floating notification -->
                            <g>
                                <rect x="60" y="240" width="36" height="36" rx="12" fill="white" stroke="#E2E8F0" stroke-width="1" filter="url(#shadow1)">
                                    <animate attributeName="y" values="240;234;240" dur="6s" repeatCount="indefinite" />
                                </rect>
                                <path d="M72 252 L78 258 L84 246" stroke="#0A84DC" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" fill="none">
                                    <animate attributeName="d" values="M72 252 L78 258 L84 246;M72 246 L78 252 L84 240;M72 252 L78 258 L84 246" dur="6s" repeatCount="indefinite" />
                                </path>
                            </g>

                            <!-- Dot grid decoration -->
                            <g opacity="0.15">
                                <circle v-for="row in 4" v-bind:key="'dot-row-'+row" :cx="0" :cy="0">
                                    <template v-for="col in 4" :key="'dot-'+row+'-'+col">
                                    </template>
                                </circle>
                            </g>
                            <g opacity="0.08">
                                <circle cx="420" cy="340" r="1.5" fill="#0A84DC" />
                                <circle cx="434" cy="340" r="1.5" fill="#0A84DC" />
                                <circle cx="448" cy="340" r="1.5" fill="#0A84DC" />
                                <circle cx="462" cy="340" r="1.5" fill="#0A84DC" />
                                <circle cx="420" cy="354" r="1.5" fill="#0A84DC" />
                                <circle cx="434" cy="354" r="1.5" fill="#0A84DC" />
                                <circle cx="448" cy="354" r="1.5" fill="#0A84DC" />
                                <circle cx="462" cy="354" r="1.5" fill="#0A84DC" />
                                <circle cx="420" cy="368" r="1.5" fill="#0A84DC" />
                                <circle cx="434" cy="368" r="1.5" fill="#0A84DC" />
                                <circle cx="448" cy="368" r="1.5" fill="#0A84DC" />
                                <circle cx="462" cy="368" r="1.5" fill="#0A84DC" />
                                <circle cx="420" cy="382" r="1.5" fill="#0A84DC" />
                                <circle cx="434" cy="382" r="1.5" fill="#0A84DC" />
                                <circle cx="448" cy="382" r="1.5" fill="#0A84DC" />
                                <circle cx="462" cy="382" r="1.5" fill="#0A84DC" />
                            </g>

                            <!-- Shadow filter -->
                            <defs>
                                <filter id="shadow1" x="-4%" y="-4%" width="108%" height="116%">
                                    <feDropShadow dx="0" dy="4" stdDeviation="8" flood-color="#000" flood-opacity="0.06" />
                                </filter>
                            </defs>
                        </svg>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>
