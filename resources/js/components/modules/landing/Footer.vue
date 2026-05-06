<script setup lang="ts">
const productLinks = [
    { label: 'Features', href: '/features' },
    { label: 'Events', href: '/events' },
    { label: 'Documentation', href: '/docs' },
]

const resourceLinks = [
    { label: 'API Reference', href: '/docs' },
    { label: 'Guides', href: '/docs' },
    { label: 'Community', href: '#' },
]
</script>

<template>
    <footer class="border-t border-slate-800 bg-slate-950 pt-14 pb-8">
        <div class="mx-auto max-w-7xl px-6 lg:px-8">
            <div class="grid gap-10 lg:grid-cols-[1.3fr_0.7fr_0.7fr_1.1fr]">
                <div>
                    <a href="/" class="inline-flex items-center gap-2.5">
                        <div class="flex size-8 items-center justify-center rounded-lg border border-white/15 bg-primary text-xs font-semibold text-white shadow-sm">
                            DF
                        </div>
                        <span class="font-display text-xl font-semibold text-white">
                            D<span class="text-primary">Form</span>
                        </span>
                    </a>
                    <p class="mt-4 max-w-xs text-sm leading-relaxed text-white/55">
                        A focused platform for event organizers to create forms, manage events, and collect responses.
                    </p>
                    <div class="mt-5 flex gap-2.5">
                        <a href="#" class="rounded-full border border-white/10 px-3 py-1.5 text-xs font-medium text-white/60 transition-colors hover:border-white/25 hover:text-white">Twitter</a>
                        <a href="#" class="rounded-full border border-white/10 px-3 py-1.5 text-xs font-medium text-white/60 transition-colors hover:border-white/25 hover:text-white">GitHub</a>
                    </div>
                </div>

                <div>
                    <h4 class="mb-3.5 text-[10px] font-semibold uppercase tracking-[0.14em] text-white/35">Product</h4>
                    <ul class="flex flex-col gap-2.5">
                        <li v-for="link in productLinks" :key="link.label">
                            <a :href="link.href" class="text-sm text-white/60 transition-colors hover:text-white">{{ link.label }}</a>
                        </li>
                    </ul>
                </div>

                <div>
                    <h4 class="mb-3.5 text-[10px] font-semibold uppercase tracking-[0.14em] text-white/35">Resources</h4>
                    <ul class="flex flex-col gap-2.5">
                        <li v-for="link in resourceLinks" :key="link.label">
                            <a :href="link.href" class="text-sm text-white/60 transition-colors hover:text-white">{{ link.label }}</a>
                        </li>
                    </ul>
                </div>

                <div>
                    <h4 class="mb-3.5 text-[10px] font-semibold uppercase tracking-[0.14em] text-white/35">Location</h4>
                    <p class="text-sm leading-relaxed text-white/55">
                        Gedung D, Universitas Dian Nuswantoro,<br />
                        Jl. Imam Bonjol No.207, Semarang,<br />
                        Jawa Tengah 50131
                    </p>
                </div>
            </div>

            <div class="mt-12 border-t border-white/10 pt-6">
                <div class="flex flex-col items-center justify-between gap-3 sm:flex-row">
                    <p class="text-xs text-white/40">
                        &copy; {{ new Date().getFullYear() }} DForm. All rights reserved.
                    </p>
                    <p class="text-xs text-white/40">
                        Built by Doscom
                    </p>
                </div>
            </div>
        </div>
    </footer>
</template>
