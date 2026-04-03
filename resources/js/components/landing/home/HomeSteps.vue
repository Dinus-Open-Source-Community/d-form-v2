<script setup lang="ts">
import { ref, onMounted } from 'vue';

const visible = ref(false);
onMounted(() => {
    const observer = new IntersectionObserver(
        ([entry]) => { if (entry.isIntersecting) visible.value = true; },
        { threshold: 0.1 }
    );
    const el = document.getElementById('steps-section');
    if (el) observer.observe(el);
});
</script>

<template>
    <section id="steps-section" class="bg-[#F9FAFB] py-24 lg:py-32">
        <div class="mx-auto max-w-7xl px-6 lg:px-8">
            <div :class="['mx-auto max-w-2xl text-center transition-all duration-700', visible ? 'translate-y-0 opacity-100' : 'translate-y-8 opacity-0']">
                <h2 class="text-3xl font-extrabold tracking-tight text-[#111827] sm:text-4xl">
                    Three steps to <span class="relative inline-block">
                        <span class="text-[#0A84DC]">launch</span>
                        <span class="absolute -bottom-0.5 left-0 h-[2px] w-full rounded-full bg-[#0A84DC]/30"></span>
                    </span>
                </h2>
                <p class="mt-3 text-sm text-[#6B7280]">From idea to live event in minutes.</p>
            </div>

            <!-- Steps with visual SVGs -->
            <div :class="['mt-16 transition-all duration-700', visible ? 'translate-y-0 opacity-100' : 'translate-y-8 opacity-0']" style="transition-delay: 200ms;">
                <div class="grid gap-8 lg:grid-cols-3">
                    <!-- Step 1: Create Event -->
                    <div class="group relative overflow-hidden rounded-2xl border border-[#E5E7EB] bg-white p-6 shadow-sm transition-all duration-300 hover:-translate-y-1 hover:shadow-md">
                        <div class="mb-5 flex items-center gap-3">
                            <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-[#0A84DC]/[0.06] text-sm font-extrabold text-[#0A84DC]">01</span>
                            <h3 class="text-base font-bold text-[#111827]">Create Event</h3>
                        </div>
                        <p class="mb-6 text-xs leading-relaxed text-[#6B7280]">Set up your event with name, date, and capacity in under 2 minutes.</p>

                        <!-- SVG: Event creation form -->
                        <svg viewBox="0 0 280 180" fill="none" class="w-full rounded-xl bg-[#F9FAFB] border border-[#F3F4F6]">
                            <defs><filter id="s1s" x="-10%" y="-10%" width="120%" height="130%"><feDropShadow dx="0" dy="2" stdDeviation="4" flood-color="#111827" flood-opacity="0.04" /></filter></defs>
                            <!-- Card -->
                            <g filter="url(#s1s)" class="s1-card">
                                <rect x="30" y="18" width="220" height="144" rx="12" fill="white" stroke="#E5E7EB" stroke-width="0.8" />
                                <rect x="46" y="34" width="72" height="7" rx="3.5" fill="#0A84DC" opacity="0.5" />
                                <!-- Fields -->
                                <rect x="46" y="52" width="188" height="16" rx="5" fill="#F9FAFB" stroke="#E5E7EB" stroke-width="0.4" />
                                <rect x="54" y="57" width="60" height="6" rx="3" fill="#111827" opacity="0.15" />
                                <rect x="46" y="76" width="90" height="16" rx="5" fill="#F9FAFB" stroke="#E5E7EB" stroke-width="0.4" />
                                <rect x="54" y="81" width="40" height="6" rx="3" fill="#111827" opacity="0.15" />
                                <rect x="144" y="76" width="90" height="16" rx="5" fill="#F9FAFB" stroke="#E5E7EB" stroke-width="0.4" />
                                <rect x="152" y="81" width="36" height="6" rx="3" fill="#111827" opacity="0.15" />
                                <rect x="46" y="100" width="188" height="16" rx="5" fill="#F9FAFB" stroke="#E5E7EB" stroke-width="0.4" />
                                <rect x="54" y="105" width="80" height="6" rx="3" fill="#111827" opacity="0.15" />
                                <!-- Button -->
                                <rect x="46" y="128" width="80" height="24" rx="8" fill="#0A84DC" />
                                <text x="62" y="144" font-size="8.5" fill="white" font-weight="600" font-family="system-ui, sans-serif">Create Event</text>
                                <!-- Cursor -->
                                <rect x="118" y="57" width="2" height="7" fill="#0A84DC" opacity="0.6" class="s1-cursor" />
                            </g>
                        </svg>

                        <!-- Arrow to next (desktop) -->
                        <div class="pointer-events-none absolute -right-4 top-1/2 z-10 hidden -translate-y-1/2 lg:block">
                            <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><path d="M6 4l4 4-4 4" stroke="#0A84DC" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" opacity="0.3"/></svg>
                        </div>
                    </div>

                    <!-- Step 2: Build Form -->
                    <div class="group relative overflow-hidden rounded-2xl border border-[#E5E7EB] bg-white p-6 shadow-sm transition-all duration-300 hover:-translate-y-1 hover:shadow-md" style="transition-delay: 150ms;">
                        <div class="mb-5 flex items-center gap-3">
                            <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-[#0A84DC]/[0.06] text-sm font-extrabold text-[#0A84DC]">02</span>
                            <h3 class="text-base font-bold text-[#111827]">Build Form</h3>
                        </div>
                        <p class="mb-6 text-xs leading-relaxed text-[#6B7280]">Design registration forms with our drag-and-drop builder.</p>

                        <!-- SVG: Drag and drop builder -->
                        <svg viewBox="0 0 280 180" fill="none" class="w-full rounded-xl bg-[#F9FAFB] border border-[#F3F4F6]">
                            <defs><filter id="s2s" x="-10%" y="-10%" width="120%" height="130%"><feDropShadow dx="0" dy="2" stdDeviation="4" flood-color="#111827" flood-opacity="0.04" /></filter></defs>
                            <!-- Left: field palette -->
                            <rect x="20" y="18" width="80" height="144" rx="10" fill="white" stroke="#E5E7EB" stroke-width="0.6" filter="url(#s2s)" />
                            <rect x="30" y="30" width="60" height="5" rx="2.5" fill="#9CA3AF" opacity="0.3" />
                            <rect x="30" y="44" width="60" height="18" rx="5" fill="#F9FAFB" stroke="#E5E7EB" stroke-width="0.4" />
                            <rect x="38" y="50" width="24" height="5" rx="2.5" fill="#E5E7EB" />
                            <rect x="30" y="68" width="60" height="18" rx="5" fill="#F9FAFB" stroke="#E5E7EB" stroke-width="0.4" />
                            <rect x="38" y="74" width="28" height="5" rx="2.5" fill="#E5E7EB" />
                            <rect x="30" y="92" width="60" height="18" rx="5" fill="#F9FAFB" stroke="#E5E7EB" stroke-width="0.4" />
                            <rect x="38" y="98" width="32" height="5" rx="2.5" fill="#E5E7EB" />
                            <rect x="30" y="116" width="60" height="18" rx="5" fill="#F9FAFB" stroke="#E5E7EB" stroke-width="0.4" />
                            <rect x="38" y="122" width="20" height="5" rx="2.5" fill="#E5E7EB" />

                            <!-- Right: form canvas -->
                            <rect x="116" y="18" width="144" height="144" rx="10" fill="white" stroke="#E5E7EB" stroke-width="0.6" filter="url(#s2s)" />
                            <rect x="128" y="30" width="80" height="6" rx="3" fill="#0A84DC" opacity="0.4" />
                            <rect x="128" y="46" width="120" height="16" rx="5" fill="#F9FAFB" stroke="#E5E7EB" stroke-width="0.4" />
                            <rect x="136" y="51" width="40" height="6" rx="3" fill="#111827" opacity="0.12" />
                            <rect x="128" y="70" width="120" height="16" rx="5" fill="#F9FAFB" stroke="#E5E7EB" stroke-width="0.4" />
                            <rect x="136" y="75" width="56" height="6" rx="3" fill="#111827" opacity="0.12" />
                            <!-- Dragging field -->
                            <g class="s2-drag">
                                <rect x="128" y="96" width="120" height="18" rx="5" fill="white" stroke="#0A84DC" stroke-width="1" stroke-dasharray="3 2" opacity="0.5" />
                            </g>
                            <!-- Dropped fields -->
                            <rect x="128" y="120" width="120" height="16" rx="5" fill="#F9FAFB" stroke="#E5E7EB" stroke-width="0.4" />
                            <rect x="136" y="125" width="48" height="6" rx="3" fill="#111827" opacity="0.12" />
                            <!-- Drag arrow -->
                            <path d="M90 56 L120 56" stroke="#0A84DC" stroke-width="1" stroke-dasharray="3 3" opacity="0.2" class="s2-arrow" />
                            <circle cx="120" cy="56" r="2" fill="#0A84DC" opacity="0.3" class="s2-dot" />
                        </svg>

                        <div class="pointer-events-none absolute -right-4 top-1/2 z-10 hidden -translate-y-1/2 lg:block">
                            <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><path d="M6 4l4 4-4 4" stroke="#0A84DC" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" opacity="0.3"/></svg>
                        </div>
                    </div>

                    <!-- Step 3: Go Live -->
                    <div class="group overflow-hidden rounded-2xl border border-[#E5E7EB] bg-white p-6 shadow-sm transition-all duration-300 hover:-translate-y-1 hover:shadow-md" style="transition-delay: 300ms;">
                        <div class="mb-5 flex items-center gap-3">
                            <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-[#0A84DC]/[0.06] text-sm font-extrabold text-[#0A84DC]">03</span>
                            <h3 class="text-base font-bold text-[#111827]">Go Live</h3>
                        </div>
                        <p class="mb-6 text-xs leading-relaxed text-[#6B7280]">Publish and start collecting responses instantly.</p>

                        <!-- SVG: Live dashboard with chart -->
                        <svg viewBox="0 0 280 180" fill="none" class="w-full rounded-xl bg-[#F9FAFB] border border-[#F3F4F6]">
                            <defs><filter id="s3s" x="-10%" y="-10%" width="120%" height="130%"><feDropShadow dx="0" dy="2" stdDeviation="4" flood-color="#111827" flood-opacity="0.04" /></filter></defs>
                            <g filter="url(#s3s)">
                                <rect x="20" y="14" width="240" height="148" rx="12" fill="white" stroke="#E5E7EB" stroke-width="0.8" />
                                <!-- Status -->
                                <circle cx="44" cy="34" r="5" fill="#059669" opacity="0.12" />
                                <circle cx="44" cy="34" r="2.5" fill="#059669" class="s3-live" />
                                <rect x="56" y="30" width="28" height="6" rx="3" fill="#059669" opacity="0.4" />
                                <!-- Stats row -->
                                <text x="36" y="68" font-size="18" fill="#0A84DC" font-weight="800" font-family="system-ui, sans-serif">1,247</text>
                                <text x="112" y="62" font-size="7" fill="#9CA3AF" font-family="system-ui, sans-serif">total</text>
                                <text x="112" y="72" font-size="7" fill="#9CA3AF" font-family="system-ui, sans-serif">responses</text>
                                <!-- Up arrow -->
                                <rect x="170" y="52" width="70" height="22" rx="8" fill="#059669" opacity="0.06" />
                                <path d="M184 66l4-6 4 6" stroke="#059669" stroke-width="1.2" stroke-linecap="round" fill="none" />
                                <text x="196" y="67" font-size="8" fill="#059669" font-weight="700" font-family="system-ui, sans-serif">+24%</text>
                                <!-- Chart area -->
                                <polyline points="36,120 60,115 84,122 108,106 132,110 156,96 180,102 204,88 228,94 244,82"
                                    stroke="#0A84DC" stroke-width="1.8" fill="none" opacity="0.4" stroke-linecap="round" stroke-linejoin="round" class="s3-chart" />
                                <polygon points="36,120 60,115 84,122 108,106 132,110 156,96 180,102 204,88 228,94 244,82 244,148 36,148"
                                    fill="#0A84DC" opacity="0.03" />
                                <!-- Dots -->
                                <circle cx="156" cy="96" r="3" fill="white" stroke="#0A84DC" stroke-width="1.5" class="s3-dot1" />
                                <circle cx="244" cy="82" r="3" fill="white" stroke="#0A84DC" stroke-width="1.5" class="s3-dot2" />
                            </g>
                            <!-- Notification pop -->
                            <g filter="url(#s3s)" class="s3-notif">
                                <rect x="168" y="2" width="100" height="34" rx="10" fill="white" stroke="#E5E7EB" stroke-width="0.6" />
                                <circle cx="186" cy="19" r="5" fill="#059669" opacity="0.1" />
                                <path d="M183 19l2 2 5-5" stroke="#059669" stroke-width="1.2" stroke-linecap="round" />
                                <rect x="196" y="14" width="56" height="4" rx="2" fill="#F1F5F9" />
                                <rect x="196" y="22" width="36" height="3.5" rx="1.75" fill="#F1F5F9" />
                            </g>
                        </svg>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>

<style scoped>
/* Cursor blink */
.s1-cursor { animation: curBlink 1s step-end infinite; }
@keyframes curBlink { 0%,100% { opacity: 0.6; } 50% { opacity: 0; } }

/* Card float */
.s1-card { animation: s1Float 6s ease-in-out infinite; }
@keyframes s1Float { 0%,100% { transform: translateY(0); } 50% { transform: translateY(-3px); } }

/* Drag animation */
.s2-drag { animation: s2DragAnim 4s ease-in-out infinite; }
@keyframes s2DragAnim { 0%,100% { transform: translateY(0); opacity: 0.5; } 50% { transform: translateY(-6px); opacity: 0.8; } }

.s2-arrow { animation: s2DashAnim 1.5s linear infinite; }
@keyframes s2DashAnim { to { stroke-dashoffset: -12; } }

.s2-dot { animation: s2DotPulse 3s ease-in-out infinite; }
@keyframes s2DotPulse { 0%,100% { r: 2; opacity: 0.3; } 50% { r: 3; opacity: 0.6; } }

/* Live dot */
.s3-live { animation: s3LivePulse 2s ease-in-out infinite; }
@keyframes s3LivePulse { 0%,100% { r: 2.5; opacity: 1; } 50% { r: 3.5; opacity: 0.5; } }

/* Chart draw */
.s3-chart { stroke-dasharray: 300; stroke-dashoffset: 300; animation: s3ChartDraw 2.5s ease-out 0.5s forwards; }
@keyframes s3ChartDraw { to { stroke-dashoffset: 0; } }

.s3-dot1 { animation: s3DotPulse 4s ease-in-out infinite; }
.s3-dot2 { animation: s3DotPulse 4s ease-in-out infinite; animation-delay: -2s; }
@keyframes s3DotPulse { 0%,100% { r: 3; } 50% { r: 4; } }

/* Notification float */
.s3-notif { animation: s3NotifFloat 5s ease-in-out infinite; }
@keyframes s3NotifFloat { 0%,100% { transform: translateY(0); } 50% { transform: translateY(-4px); } }
</style>
