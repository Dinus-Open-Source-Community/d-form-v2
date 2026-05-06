<script setup lang="ts">
import LandingLayout from '@/layouts/LandingLayout.vue';
import { ref, onMounted } from 'vue';
import LocalLottie from '@/components/core/LocalLottie.vue';

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
        <section class="relative overflow-hidden bg-background pt-28 pb-16 lg:pt-36 lg:pb-20">
            <div class="app-grid pointer-events-none absolute inset-0 opacity-40"></div>
            <div class="pointer-events-none absolute right-0 top-20 h-72 w-72 rounded-full bg-primary/10 blur-3xl"></div>

            <div class="relative mx-auto max-w-7xl px-6 lg:px-8">
                <div class="grid items-center gap-16 lg:grid-cols-2 lg:gap-20">
                    <div :class="['transition-all duration-700', heroVisible ? 'translate-y-0 opacity-100' : 'translate-y-8 opacity-0']">
                        <div class="app-kicker mb-6">Documentation</div>
                        <h1 class="font-display text-5xl leading-[0.98] font-semibold tracking-[-0.055em] text-foreground sm:text-6xl">
                            Learn, build,<br/><span class="text-primary">ship faster.</span>
                        </h1>
                        <p class="mt-6 max-w-md text-base leading-relaxed text-muted-foreground lg:text-lg">
                            Comprehensive guides, API references, and tutorials to help you get the most out of DForm.
                        </p>

                        <label class="mt-8 flex max-w-md items-center gap-2.5 rounded-2xl border border-border bg-card px-4 py-3 shadow-xs transition-[border-color,box-shadow] duration-200 ease-[cubic-bezier(0.22,1,0.36,1)] focus-within:border-primary focus-within:ring-3 focus-within:ring-primary/15">
                            <span class="size-1.5 rounded-full bg-primary"></span>
                            <input type="text" placeholder="Search documentation..." class="w-full bg-transparent text-sm text-foreground placeholder:text-muted-foreground outline-none" />
                        </label>
                    </div>

                    <div :class="['relative transition-all delay-200 duration-700', heroVisible ? 'translate-y-0 opacity-100' : 'translate-y-12 opacity-0']">
                        <div class="app-surface p-6">
                            <div class="mb-5 flex items-center justify-between">
                                <div>
                                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-muted-foreground">Docs center</p>
                                    <p class="font-display mt-1 text-2xl font-semibold">Guides and API notes</p>
                                </div>
                                <span class="rounded-full border border-success/15 bg-success/10 px-3 py-1 text-xs font-semibold text-success">200 OK</span>
                            </div>
                            <LocalLottie name="docsFlow" :height="300" width="100%" />
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Content -->
        <section id="docs-content" class="border-y border-border bg-card/60 py-20 lg:py-28">
            <div class="mx-auto max-w-7xl px-6 lg:px-8">
                <div class="mb-10">
                    <h2 class="font-display text-4xl font-semibold tracking-[-0.04em] text-foreground">Guides & Tutorials</h2>
                    <p class="mt-2 text-base text-muted-foreground">Everything you need to get started and go further.</p>
                </div>

                <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                    <a
                        v-for="(g, i) in guides" :key="g.title"
                        href="#"
                        :class="['app-surface group flex flex-col p-6 transition-all duration-500', contentVisible ? 'translate-y-0 opacity-100' : 'translate-y-6 opacity-0']"
                        :style="{ transitionDelay: `${i * 80}ms` }"
                    >
                        <div class="mb-3 flex items-center justify-between">
                            <span class="rounded-full border border-primary/15 bg-primary/10 px-2.5 py-1 text-[10px] font-semibold uppercase tracking-[0.14em] text-primary">{{ g.tag }}</span>
                        </div>
                        <h3 class="font-display mb-1 text-lg font-semibold text-foreground transition-colors group-hover:text-primary">{{ g.title }}</h3>
                        <p class="text-sm leading-relaxed text-muted-foreground">{{ g.desc }}</p>
                        <div class="mt-auto flex items-center gap-1 pt-4 text-[11px] font-semibold text-primary opacity-0 transition-opacity group-hover:opacity-100">
                            Read guide
                        </div>
                    </a>
                </div>

                <div class="mt-20">
                    <div class="mb-8 flex items-center gap-3">
                        <div class="size-2 rounded-full bg-primary"></div>
                        <h2 class="font-display text-3xl font-semibold tracking-[-0.04em] text-foreground">API Reference</h2>
                    </div>

                    <div class="app-surface overflow-hidden p-0">
                        <div class="border-b border-border bg-muted/40 px-6 py-3">
                            <span class="font-mono text-xs font-semibold text-muted-foreground">Base URL: https://api.dform.io/v1</span>
                        </div>
                        <div class="divide-y divide-border/60">
                            <div v-for="ep in apiEndpoints" :key="ep.path" class="flex items-center gap-4 px-6 py-3.5 transition-colors hover:bg-muted/30">
                                <span
                                    :class="[
                                        'w-14 rounded-md py-0.5 text-center font-mono text-[10px] font-semibold',
                                        ep.method === 'GET' ? 'bg-success/10 text-success' : 'bg-primary/10 text-primary',
                                    ]"
                                >{{ ep.method }}</span>
                                <code class="font-mono text-xs font-medium text-foreground">{{ ep.path }}</code>
                                <span class="ml-auto text-xs text-muted-foreground">{{ ep.desc }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </LandingLayout>
</template>
