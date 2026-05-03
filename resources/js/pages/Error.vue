<script setup lang="ts">
import { computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import LocalLottie from '@/components/core/LocalLottie.vue';

const props = defineProps<{
    status: number;
}>();

const titles: Record<number, string> = {
    503: '503: Lagi beres-beres',
    500: '500: Server lagi mumet',
    404: '404: Halamannya kabur',
    403: '403: Area khusus',
};

const descriptions: Record<number, string> = {
    503: 'DForm sedang maintenance sebentar. Balik lagi nanti ya.',
    500: 'Ada yang tidak beres di sisi server. Tim kami perlu cek sebentar.',
    404: 'Halaman yang kamu cari tidak ditemukan, tapi kamu masih bisa balik ke rumah.',
    403: 'Kamu belum punya akses untuk membuka halaman ini.',
};

const title = computed(() => titles[props.status] ?? `${props.status}: Ada yang aneh`);
const description = computed(() => descriptions[props.status] ?? 'Terjadi masalah yang tidak terduga.');
</script>

<template>
    <Head :title="title" />

    <main class="relative grid min-h-dvh place-items-center overflow-hidden bg-background px-6 py-16">
        <div class="brutal-grid pointer-events-none absolute inset-0 opacity-[0.02]"></div>

        <section class="brutal-card relative z-10 grid max-w-4xl items-center gap-8 bg-white p-7 md:grid-cols-[0.9fr_1.1fr] md:p-9">
            <div class="rounded-2xl border-[1.5px] border-[var(--brutal-ink)] bg-[var(--brutal-mint)]/10 p-4 shadow-[var(--brutal-shadow-sm)]">
                <LocalLottie name="errorState" :height="240" :width="240" class="mx-auto" />
            </div>

            <div>
                <p class="mb-3.5 w-fit rounded-full border-[1.5px] border-[var(--brutal-ink)] bg-[var(--brutal-yellow)]/20 px-3.5 py-1.5 text-xs font-bold uppercase tracking-[0.14em] shadow-[var(--brutal-shadow-sm)]">
                    Something happened
                </p>
                <h1 class="font-display text-balance text-4xl font-bold leading-none tracking-[-0.035em] text-[var(--brutal-ink)] md:text-5xl">
                    {{ title }}
                </h1>
                <p class="mt-4 max-w-xl text-base font-medium leading-relaxed text-[var(--brutal-ink)]/60">
                    {{ description }}
                </p>
                <div class="mt-7 flex flex-wrap gap-2.5">
                    <a
                        href="/"
                        class="rounded-xl border-[1.5px] border-[var(--brutal-ink)] bg-[var(--brutal-blue)] px-5 py-2.5 text-sm font-bold text-white shadow-[var(--brutal-shadow)] transition-all hover:-translate-y-0.5 hover:shadow-[4px_4px_0_var(--brutal-ink)]"
                    >
                        Back to Home
                    </a>
                    <a
                        href="/events"
                        class="rounded-xl border-[1.5px] border-[var(--brutal-ink)] bg-white px-5 py-2.5 text-sm font-bold text-[var(--brutal-ink)] shadow-[var(--brutal-shadow)] transition-all hover:-translate-y-0.5 hover:bg-[var(--brutal-pink)]/8 hover:shadow-[4px_4px_0_var(--brutal-ink)]"
                    >
                        Browse Events
                    </a>
                </div>
            </div>
        </section>
    </main>
</template>
