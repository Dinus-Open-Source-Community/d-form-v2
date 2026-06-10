<script setup lang="ts">
import { Separator } from '@/components/ui/separator';
import { routes } from '@/lib/routes';
import { MapPin, Mail, Globe } from 'lucide-vue-next';

const linkGroups = [
    {
        title: 'Platform',
        items: [
            { label: 'Fitur', href: routes.landing.features },
            { label: 'Acara', href: routes.landing.events.index },
            { label: 'Dokumentasi', href: routes.landing.docs },
        ],
    },
    {
        title: 'Akses',
        items: [
            { label: 'Masuk', href: routes.auth.login },
            { label: 'Buat akun', href: routes.auth.register },
            { label: 'Dasbor', href: routes.dashboard.index },
        ],
    },
    {
        title: 'Organisasi',
        items: [
            { label: 'Doscom Udinus', href: 'https://doscom.org', external: true },
            { label: 'Universitas Dian Nuswantoro', href: 'https://dinus.ac.id', external: true },
        ],
    },
];

const mapEmbedUrl =
    'https://maps.google.com/maps?q=Gedung+D+Universitas+Dian+Nuswantoro,+Jl.+Nakula+I+No.5-11,+Semarang&t=&z=17&ie=UTF8&iwloc=B&output=embed';
</script>

<template>
    <footer class="border-border/20 bg-muted/20 border-t">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-10">
            <div class="grid gap-8 py-10 sm:gap-10 sm:py-14 lg:grid-cols-[1fr_1.1fr] lg:gap-16 lg:py-20">
                <div class="grid gap-8 md:grid-cols-[minmax(0,1fr)_minmax(18rem,0.9fr)] lg:grid-cols-1 lg:gap-8">
                    <div class="min-w-0">
                        <a :href="routes.home" class="inline-flex items-center gap-2.5">
                            <img src="/public/DForm%201.png" alt="DOSCOM" class="h-8 w-auto sm:h-9" />
                        </a>
                        <p class="text-muted-foreground mt-4 max-w-xl text-sm leading-relaxed sm:max-w-xs sm:text-[0.9375rem]">
                            Platform sederhana untuk mengelola pendaftaran acara dari satu tempat. Dibangun oleh Doscom,
                            Universitas Dian Nuswantoro.
                        </p>

                        <div class="mt-6 flex flex-col gap-3.5">
                            <div class="text-muted-foreground flex items-start gap-2.5 text-sm leading-relaxed">
                                <MapPin class="text-primary/70 mt-0.5 size-4 shrink-0" />
                                <span>Gedung D, Universitas Dian Nuswantoro, Jl. Nakula I No.5-11, Semarang</span>
                            </div>
                            <div class="text-muted-foreground flex min-w-0 items-center gap-2.5 text-sm">
                                <Mail class="text-primary/70 size-4 shrink-0" />
                                <a href="mailto:doscom.go@gmail.com" class="hover:text-primary break-all transition-colors"
                                    >doscom.go@gmail.com</a
                                >
                            </div>
                            <div class="text-muted-foreground flex items-center gap-2.5 text-sm">
                                <Globe class="text-primary/70 size-4 shrink-0" />
                                <a
                                    href="https://doscom.org"
                                    target="_blank"
                                    rel="noopener"
                                    class="hover:text-primary transition-colors"
                                    >doscom.org</a
                                >
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-6 min-[420px]:grid-cols-2 sm:grid-cols-3 md:gap-8 lg:grid-cols-3">
                        <div v-for="group in linkGroups" :key="group.title">
                            <p class="text-foreground/50 mb-3 text-[0.75rem] font-semibold tracking-[0.14em] uppercase sm:mb-4">
                                {{ group.title }}
                            </p>
                            <ul class="flex flex-col gap-2">
                                <li v-for="item in group.items" :key="item.label">
                                    <a
                                        :href="item.href"
                                        :target="'external' in item ? '_blank' : undefined"
                                        :rel="'external' in item ? 'noopener' : undefined"
                                        class="text-muted-foreground hover:text-primary text-sm leading-relaxed transition-colors duration-150"
                                    >
                                        {{ item.label }}
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="border-border/30 overflow-hidden rounded-[1.5rem] border shadow-sm sm:rounded-2xl">
                    <iframe
                        :src="mapEmbedUrl"
                        width="100%"
                        height="100%"
                        class="min-h-[220px] sm:min-h-[280px] lg:min-h-full"
                        style="border: 0"
                        allowfullscreen
                        loading="lazy"
                        referrerpolicy="no-referrer-when-downgrade"
                        title="Lokasi Gedung D Universitas Dian Nuswantoro Semarang"
                    />
                </div>
            </div>

            <Separator class="opacity-30" />

            <div class="flex flex-col items-start justify-between gap-2 py-5 sm:flex-row sm:items-center sm:gap-3 sm:py-7">
                <p class="text-muted-foreground text-[0.75rem] leading-relaxed">
                    &copy; {{ new Date().getFullYear() }} DForm — Doscom, Universitas Dian Nuswantoro
                </p>
                <p class="text-muted-foreground/60 text-[0.75rem] leading-relaxed">Semarang, Indonesia</p>
            </div>
        </div>
    </footer>
</template>
