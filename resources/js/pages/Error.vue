<script setup lang="ts">
import { computed } from 'vue'
import { Head } from '@inertiajs/vue3'
import LocalLottie from '@/components/core/LocalLottie.vue'

const props = defineProps<{
    status: number
}>()

const titles: Record<number, string> = {
    503: '503: Lagi beres-beres',
    500: '500: Server lagi mumet',
    404: '404: Halamannya kabur',
    403: '403: Area khusus',
}

const descriptions: Record<number, string> = {
    503: 'DForm sedang maintenance sebentar. Balik lagi nanti ya.',
    500: 'Ada yang tidak beres di sisi server. Tim kami perlu cek sebentar.',
    404: 'Halaman yang kamu cari tidak ditemukan, tapi kamu masih bisa balik ke rumah.',
    403: 'Kamu belum punya akses untuk membuka halaman ini.',
}

const title = computed<string>(() => titles[props.status] ?? `${props.status}: Ada yang aneh`)
const description = computed<string>(() => descriptions[props.status] ?? 'Terjadi masalah yang tidak terduga.')
</script>

<template>
    <Head :title="title" />

    <main class="relative grid min-h-dvh place-items-center overflow-hidden bg-background px-6 py-16">
        <div class="app-grid pointer-events-none absolute inset-0 opacity-30"></div>
        <div class="pointer-events-none absolute -top-20 left-1/2 h-96 w-96 -translate-x-1/2 rounded-full bg-primary/8 blur-3xl"></div>

        <section class="app-surface relative z-10 grid max-w-4xl items-center gap-8 p-7 md:grid-cols-[0.9fr_1.1fr] md:p-9">
            <div class="rounded-2xl border border-border bg-muted/30 p-4">
                <LocalLottie name="errorState" :height="240" :width="240" :lazy="false" class="mx-auto" />
            </div>

            <div>
                <p class="app-kicker mb-4">Something happened</p>
                <h1 class="font-display text-balance text-4xl font-bold leading-[1.05] tracking-[-0.035em] text-foreground md:text-5xl">
                    {{ title }}
                </h1>
                <p class="mt-4 max-w-xl text-base leading-relaxed text-muted-foreground">
                    {{ description }}
                </p>
                <div class="mt-7 flex flex-wrap gap-3">
                    <a
                        href="/"
                        class="inline-flex items-center gap-2 rounded-xl border border-primary/15 bg-primary px-5 py-2.5 text-sm font-semibold text-primary-foreground shadow-sm transition-[transform,background-color] duration-200 ease-[cubic-bezier(0.22,1,0.36,1)] hover:-translate-y-px hover:bg-primary/92 active:scale-[0.98]"
                    >
                        Back to Home
                    </a>
                    <a
                        href="/events"
                        class="inline-flex items-center gap-2 rounded-xl border border-border bg-card px-5 py-2.5 text-sm font-semibold text-foreground shadow-xs transition-[transform,border-color] duration-200 ease-[cubic-bezier(0.22,1,0.36,1)] hover:-translate-y-px hover:border-primary/30 hover:bg-accent active:scale-[0.98]"
                    >
                        Browse Events
                    </a>
                </div>
            </div>
        </section>
    </main>
</template>
