<script setup lang="ts">
import { Separator } from '@/components/ui/separator'

const columns = [
    {
        title: 'Platform',
        items: [
            { label: 'Fitur', href: '/features' },
            { label: 'Acara', href: '/events' },
            { label: 'Dokumentasi', href: '/docs' },
        ],
    },
    {
        title: 'Akses',
        items: [
            { label: 'Masuk', href: '/auth/login' },
            { label: 'Buat akun', href: '/auth/register' },
            { label: 'Dasbor', href: '/user/dashboard' },
        ],
    },
]
</script>

<template>
    <footer class="border-t border-border/40 bg-muted/15">
        <div class="mx-auto max-w-5xl px-5 lg:px-8">
            <div class="grid gap-8 py-12 sm:grid-cols-3">
                <div>
                    <a href="/" class="inline-flex items-center gap-2">
                        <span class="flex size-7 items-center justify-center rounded-md bg-primary text-[10px] font-bold text-primary-foreground">D</span>
                        <span class="text-[15px] font-semibold tracking-tight text-foreground">DForm</span>
                    </a>
                    <p class="mt-3 max-w-[220px] text-[12px] leading-relaxed text-muted-foreground">
                        Platform sederhana untuk mengelola pendaftaran acara dari satu tempat.
                    </p>
                </div>

                <div v-for="col in columns" :key="col.title">
                    <p class="mb-3 text-[11px] font-semibold uppercase tracking-widest text-muted-foreground/70">
                        {{ col.title }}
                    </p>
                    <ul class="flex flex-col gap-2">
                        <li v-for="item in col.items" :key="item.label">
                            <a
                                :href="item.href"
                                class="text-[13px] text-foreground/75 transition-colors duration-150 hover:text-primary"
                            >
                                {{ item.label }}
                            </a>
                        </li>
                    </ul>
                </div>
            </div>

            <Separator class="opacity-50" />

            <div class="flex flex-col items-start justify-between gap-2 py-5 sm:flex-row sm:items-center">
                <p class="text-[11px] text-muted-foreground">&copy; {{ new Date().getFullYear() }} DForm</p>
                <p class="text-[11px] text-muted-foreground">Doscom — Universitas Dian Nuswantoro</p>
            </div>
        </div>
    </footer>
</template>
