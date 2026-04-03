<script setup lang="ts">
import LandingLayout from '@/layouts/LandingLayout.vue';
import { ref, onMounted } from 'vue';

const heroVisible = ref(false);
const contentVisible = ref(false);

onMounted(() => {
    setTimeout(() => (heroVisible.value = true), 100);
    const observer = new IntersectionObserver(
        ([entry]) => { if (entry.isIntersecting) contentVisible.value = true; },
        { threshold: 0.1 }
    );
    const el = document.getElementById('docs-content');
    if (el) observer.observe(el);
});

const guides = [
    { icon: 'rocket', title: 'Quick Start', desc: 'Create your first event and form in under 5 minutes.', tag: 'Beginner' },
    { icon: 'form', title: 'Form Builder Guide', desc: 'Learn how to use field types, validations, and conditional logic.', tag: 'Guide' },
    { icon: 'webhook', title: 'API & Webhooks', desc: 'Integrate DForm with your stack using our REST API and webhooks.', tag: 'Developer' },
    { icon: 'team', title: 'Team Management', desc: 'Set up roles, permissions, and collaborative workflows.', tag: 'Admin' },
    { icon: 'chart', title: 'Analytics & Reports', desc: 'Understand response data with built-in charts and export tools.', tag: 'Guide' },
    { icon: 'shield', title: 'Security & Privacy', desc: 'Encryption, GDPR compliance, and data governance best practices.', tag: 'Security' },
];

const apiEndpoints = [
    { method: 'GET', path: '/api/events', desc: 'List all events' },
    { method: 'POST', path: '/api/events', desc: 'Create a new event' },
    { method: 'GET', path: '/api/events/:id/forms', desc: 'Get forms for an event' },
    { method: 'POST', path: '/api/forms/:id/responses', desc: 'Submit a form response' },
    { method: 'GET', path: '/api/analytics/:id', desc: 'Get response analytics' },
];
</script>

<template>
    <LandingLayout>
        <!-- Hero -->
        <section class="relative overflow-hidden bg-white pt-28 pb-16 lg:pt-36 lg:pb-20">
            <div class="pointer-events-none absolute inset-0 opacity-[0.02]">
                <svg width="100%" height="100%"><defs><pattern id="dg" width="48" height="48" patternUnits="userSpaceOnUse"><path d="M 48 0 L 0 0 0 48" fill="none" stroke="#000" stroke-width="0.5"/></pattern></defs><rect width="100%" height="100%" fill="url(#dg)"/></svg>
            </div>

            <div class="relative mx-auto max-w-7xl px-6 lg:px-8">
                <div class="grid items-center gap-16 lg:grid-cols-2 lg:gap-20">
                    <div :class="['transition-all duration-700', heroVisible ? 'translate-y-0 opacity-100' : 'translate-y-8 opacity-0']">
                        <div class="mb-6 inline-flex items-center gap-2 rounded-full border border-[#0A84DC]/15 bg-[#0A84DC]/[0.04] px-4 py-1.5">
                            <span class="text-xs font-semibold tracking-wide text-[#0A84DC]">Documentation</span>
                        </div>
                        <h1 class="text-4xl leading-[1.08] font-extrabold tracking-tight text-gray-900 sm:text-5xl">
                            Learn, build,<br/><span class="text-[#0A84DC]">ship faster.</span>
                        </h1>
                        <p class="mt-6 max-w-md text-base leading-relaxed text-gray-400 lg:text-lg">
                            Comprehensive guides, API references, and tutorials to help you get the most out of DForm.
                        </p>

                        <!-- Search bar -->
                        <div class="mt-8 flex max-w-md items-center gap-3 rounded-xl border border-gray-200 bg-white px-4 py-3 shadow-sm transition-all focus-within:border-[#0A84DC]/30 focus-within:shadow-md">
                            <svg class="h-4 w-4 text-gray-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
                            <input type="text" placeholder="Search documentation..." class="w-full bg-transparent text-sm text-gray-700 placeholder-gray-300 outline-none"/>
                        </div>
                    </div>

                    <!-- SVG: Document flow animation -->
                    <div :class="['relative transition-all delay-200 duration-700', heroVisible ? 'translate-y-0 opacity-100' : 'translate-y-12 opacity-0']">
                        <svg viewBox="0 0 480 380" fill="none" xmlns="http://www.w3.org/2000/svg" class="w-full">
                            <!-- Background code blocks -->
                            <rect x="60" y="30" width="360" height="320" rx="16" fill="#0F172A" opacity="0.03"/>

                            <!-- Document page 1 (back) -->
                            <rect x="160" y="60" width="200" height="260" rx="12" fill="white" stroke="#E5E7EB" stroke-width="0.8" filter="url(#ds)" opacity="0.6">
                                <animate attributeName="x" values="164;160;164" dur="7s" repeatCount="indefinite"/>
                            </rect>

                            <!-- Document page 2 (middle) -->
                            <rect x="140" y="50" width="200" height="260" rx="12" fill="white" stroke="#E5E7EB" stroke-width="0.8" filter="url(#ds)" opacity="0.8">
                                <animate attributeName="x" values="142;140;142" dur="6s" repeatCount="indefinite"/>
                            </rect>

                            <!-- Document page 3 (front) -->
                            <g>
                                <rect x="120" y="40" width="200" height="260" rx="12" fill="white" stroke="#E5E7EB" stroke-width="0.8" filter="url(#ds)">
                                    <animate attributeName="y" values="42;38;42" dur="5s" repeatCount="indefinite"/>
                                </rect>
                                <!-- Header bar -->
                                <rect x="120" y="40" width="200" height="36" rx="12" fill="#0A84DC" opacity="0.04">
                                    <animate attributeName="y" values="42;38;42" dur="5s" repeatCount="indefinite"/>
                                </rect>
                                <circle cx="140" cy="58" r="4" fill="#0A84DC" opacity="0.2"><animate attributeName="cy" values="60;56;60" dur="5s" repeatCount="indefinite"/></circle>
                                <rect x="152" y="54" width="40" height="5" rx="2.5" fill="#0A84DC" opacity="0.15"><animate attributeName="y" values="56;52;56" dur="5s" repeatCount="indefinite"/></rect>

                                <!-- Code lines -->
                                <rect x="136" y="90" width="80" height="5" rx="2.5" fill="#0A84DC" opacity="0.12"><animate attributeName="y" values="92;88;92" dur="5s" repeatCount="indefinite"/></rect>
                                <rect x="136" y="102" width="168" height="5" rx="2.5" fill="#E5E7EB"><animate attributeName="y" values="104;100;104" dur="5s" repeatCount="indefinite"/></rect>
                                <rect x="136" y="114" width="140" height="5" rx="2.5" fill="#E5E7EB"><animate attributeName="y" values="116;112;116" dur="5s" repeatCount="indefinite"/></rect>
                                <rect x="136" y="126" width="160" height="5" rx="2.5" fill="#E5E7EB"><animate attributeName="y" values="128;124;128" dur="5s" repeatCount="indefinite"/></rect>

                                <!-- Code block -->
                                <rect x="136" y="146" width="168" height="56" rx="6" fill="#0F172A" opacity="0.03"><animate attributeName="y" values="148;144;148" dur="5s" repeatCount="indefinite"/></rect>
                                <rect x="148" y="158" width="60" height="4" rx="2" fill="#0A84DC" opacity="0.3"><animate attributeName="y" values="160;156;160" dur="5s" repeatCount="indefinite"/></rect>
                                <rect x="148" y="168" width="120" height="4" rx="2" fill="#64748B" opacity="0.15"><animate attributeName="y" values="170;166;170" dur="5s" repeatCount="indefinite"/></rect>
                                <rect x="148" y="178" width="90" height="4" rx="2" fill="#64748B" opacity="0.15"><animate attributeName="y" values="180;176;180" dur="5s" repeatCount="indefinite"/></rect>
                                <rect x="148" y="188" width="40" height="4" rx="2" fill="#10B981" opacity="0.3"><animate attributeName="y" values="190;186;190" dur="5s" repeatCount="indefinite"/></rect>

                                <!-- More lines -->
                                <rect x="136" y="218" width="100" height="5" rx="2.5" fill="#E5E7EB"><animate attributeName="y" values="220;216;220" dur="5s" repeatCount="indefinite"/></rect>
                                <rect x="136" y="230" width="160" height="5" rx="2.5" fill="#E5E7EB"><animate attributeName="y" values="232;228;232" dur="5s" repeatCount="indefinite"/></rect>
                                <rect x="136" y="242" width="130" height="5" rx="2.5" fill="#E5E7EB"><animate attributeName="y" values="244;240;244" dur="5s" repeatCount="indefinite"/></rect>

                                <!-- Button -->
                                <rect x="136" y="264" width="70" height="22" rx="7" fill="#0A84DC" opacity="0.1"><animate attributeName="y" values="266;262;266" dur="5s" repeatCount="indefinite"/></rect>
                                <text x="148" y="279" font-size="8" fill="#0A84DC" font-weight="600" font-family="Plus Jakarta Sans, sans-serif">Try it<animate attributeName="y" values="281;277;281" dur="5s" repeatCount="indefinite"/></text>
                            </g>

                            <!-- Floating elements -->
                            <g>
                                <rect x="340" y="80" width="110" height="44" rx="10" fill="white" stroke="#E5E7EB" stroke-width="0.8" filter="url(#ds)">
                                    <animate attributeName="y" values="82;76;82" dur="4s" repeatCount="indefinite"/>
                                </rect>
                                <rect x="354" y="92" width="32" height="5" rx="2.5" fill="#10B981" opacity="0.5"><animate attributeName="y" values="94;88;94" dur="4s" repeatCount="indefinite"/></rect>
                                <rect x="354" y="102" width="80" height="4" rx="2" fill="#E5E7EB"><animate attributeName="y" values="104;98;104" dur="4s" repeatCount="indefinite"/></rect>
                                <text x="392" y="96" font-size="7" fill="#10B981" font-weight="600" font-family="Plus Jakarta Sans, sans-serif"><animate attributeName="y" values="98;92;98" dur="4s" repeatCount="indefinite"/>200 OK</text>
                            </g>

                            <g>
                                <rect x="340" y="260" width="110" height="70" rx="10" fill="white" stroke="#E5E7EB" stroke-width="0.8" filter="url(#ds)">
                                    <animate attributeName="y" values="262;258;262" dur="6s" repeatCount="indefinite"/>
                                </rect>
                                <rect x="354" y="276" width="44" height="5" rx="2.5" fill="#0A84DC" opacity="0.3"><animate attributeName="y" values="278;274;278" dur="6s" repeatCount="indefinite"/></rect>
                                <rect x="354" y="286" width="80" height="4" rx="2" fill="#E5E7EB"><animate attributeName="y" values="288;284;288" dur="6s" repeatCount="indefinite"/></rect>
                                <rect x="354" y="296" width="60" height="4" rx="2" fill="#E5E7EB"><animate attributeName="y" values="298;294;298" dur="6s" repeatCount="indefinite"/></rect>
                                <rect x="354" y="306" width="44" height="4" rx="2" fill="#E5E7EB"><animate attributeName="y" values="308;304;308" dur="6s" repeatCount="indefinite"/></rect>
                            </g>

                            <!-- Connector lines -->
                            <line x1="320" y1="180" x2="340" y2="105" stroke="#0A84DC" stroke-width="1" stroke-dasharray="3 3" opacity="0.15"><animate attributeName="stroke-dashoffset" from="12" to="0" dur="1s" repeatCount="indefinite"/></line>
                            <line x1="320" y1="220" x2="340" y2="290" stroke="#0A84DC" stroke-width="1" stroke-dasharray="3 3" opacity="0.15"><animate attributeName="stroke-dashoffset" from="12" to="0" dur="1s" repeatCount="indefinite"/></line>

                            <!-- Cursor blink -->
                            <rect x="270" y="188" width="2" height="10" fill="#0A84DC" opacity="0.5">
                                <animate attributeName="opacity" values="0.6;0;0.6" dur="1s" repeatCount="indefinite"/>
                                <animate attributeName="y" values="190;186;190" dur="5s" repeatCount="indefinite"/>
                            </rect>

                            <defs><filter id="ds" x="-6%" y="-6%" width="112%" height="120%"><feDropShadow dx="0" dy="4" stdDeviation="10" flood-color="#000" flood-opacity="0.04"/></filter></defs>
                        </svg>
                    </div>
                </div>
            </div>
        </section>

        <!-- Content -->
        <section id="docs-content" class="bg-[#FAFAFA] py-20 lg:py-28">
            <div class="mx-auto max-w-7xl px-6 lg:px-8">
                <!-- Guides -->
                <div class="mb-10">
                    <h2 class="text-2xl font-extrabold tracking-tight text-gray-900">Guides & Tutorials</h2>
                    <p class="mt-2 text-sm text-gray-400">Everything you need to get started and go further.</p>
                </div>

                <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                    <a
                        v-for="(g, i) in guides" :key="g.title"
                        href="#"
                        :class="['group flex flex-col rounded-2xl border border-gray-100 bg-white p-6 transition-all duration-500 hover:-translate-y-1 hover:border-[#0A84DC]/15 hover:shadow-xl hover:shadow-[#0A84DC]/[0.04]', contentVisible ? 'translate-y-0 opacity-100' : 'translate-y-6 opacity-0']"
                        :style="{ transitionDelay: `${i * 80}ms` }"
                    >
                        <div class="mb-3 flex items-center justify-between">
                            <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-[#0A84DC]/[0.06]">
                                <svg v-if="g.icon === 'rocket'" class="h-4 w-4 text-[#0A84DC]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4.5 16.5c-1.5 1.26-2 5-2 5s3.74-.5 5-2c.71-.84.7-2.13-.09-2.91a2.18 2.18 0 0 0-2.91-.09Z"/><path d="m12 15-3-3a22 22 0 0 1 2-3.95A12.88 12.88 0 0 1 22 2c0 2.72-.78 7.5-6 11a22.35 22.35 0 0 1-4 2Z"/><path d="M9 12H4s.55-3.03 2-4c1.62-1.08 5 0 5 0"/><path d="M12 15v5s3.03-.55 4-2c1.08-1.62 0-5 0-5"/></svg>
                                <svg v-if="g.icon === 'form'" class="h-4 w-4 text-[#0A84DC]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"/><path d="M14 2v6h6"/></svg>
                                <svg v-if="g.icon === 'webhook'" class="h-4 w-4 text-[#0A84DC]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/></svg>
                                <svg v-if="g.icon === 'team'" class="h-4 w-4 text-[#0A84DC]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
                                <svg v-if="g.icon === 'chart'" class="h-4 w-4 text-[#0A84DC]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v18h18"/><path d="M18 17V9"/><path d="M13 17V5"/></svg>
                                <svg v-if="g.icon === 'shield'" class="h-4 w-4 text-[#0A84DC]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10"/></svg>
                            </div>
                            <span class="rounded-md bg-gray-50 px-2 py-0.5 text-[10px] font-semibold text-gray-400">{{ g.tag }}</span>
                        </div>
                        <h3 class="mb-1 text-sm font-bold text-gray-900 transition-colors group-hover:text-[#0A84DC]">{{ g.title }}</h3>
                        <p class="text-xs leading-relaxed text-gray-400">{{ g.desc }}</p>
                        <div class="mt-auto flex items-center gap-1 pt-4 text-[11px] font-semibold text-[#0A84DC] opacity-0 transition-opacity group-hover:opacity-100">
                            Read guide
                            <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
                        </div>
                    </a>
                </div>

                <!-- API Reference Preview -->
                <div class="mt-20">
                    <div class="mb-8 flex items-center gap-3">
                        <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-[#0A84DC]/[0.06]">
                            <svg class="h-4 w-4 text-[#0A84DC]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/></svg>
                        </div>
                        <h2 class="text-xl font-bold tracking-tight text-gray-900">API Reference</h2>
                    </div>

                    <div class="overflow-hidden rounded-2xl border border-gray-100 bg-white">
                        <div class="border-b border-gray-50 bg-gray-50/50 px-6 py-3">
                            <span class="text-xs font-mono font-medium text-gray-400">Base URL: https://api.dform.io/v1</span>
                        </div>
                        <div class="divide-y divide-gray-50">
                            <div v-for="ep in apiEndpoints" :key="ep.path" class="flex items-center gap-4 px-6 py-3.5 transition-colors hover:bg-gray-50/50">
                                <span
                                    :class="[
                                        'w-14 rounded-md py-0.5 text-center font-mono text-[10px] font-bold',
                                        ep.method === 'GET' ? 'bg-emerald-50 text-emerald-600' : 'bg-[#0A84DC]/8 text-[#0A84DC]',
                                    ]"
                                >{{ ep.method }}</span>
                                <code class="text-xs font-mono font-medium text-gray-700">{{ ep.path }}</code>
                                <span class="ml-auto text-xs text-gray-400">{{ ep.desc }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </LandingLayout>
</template>
