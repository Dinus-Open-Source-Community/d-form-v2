<script setup lang="ts">
import LocalLottie from '@/components/core/LocalLottie.vue';
import type { TLottieName } from '@/lib/lotties';
import { CalendarPlus, PenTool, Rocket } from 'lucide-vue-next';
import type { Component } from 'vue';
import { useViewportReveal } from '@/hooks/useViewportReveal';

interface Step {
    num: string;
    title: string;
    desc: string;
    detail: string;
    lottie: TLottieName;
    icon: Component;
}

const steps: Step[] = [
    {
        num: '01',
        title: 'Buat acara baru',
        desc: 'Tentukan informasi dasar acara Anda.',
        detail: 'Isi nama acara, tanggal pelaksanaan, kuota peserta, dan deskripsi singkat. Anda juga bisa mengunggah banner dan mengatur kapan pendaftaran dibuka dan ditutup.',
        lottie: 'landingStepCreate',
        icon: CalendarPlus,
    },
    {
        num: '02',
        title: 'Rancang formulir',
        desc: 'Desain form sesuai kebutuhan acara.',
        detail: 'Buka Form Builder, tambahkan field dengan drag & drop — mulai dari teks, dropdown, radio, checkbox, hingga file upload. Atur validasi, urutan, dan pratinjau sebelum dipublikasikan.',
        lottie: 'landingStepBuild',
        icon: PenTool,
    },
    {
        num: '03',
        title: 'Publikasi & kelola',
        desc: 'Bagikan dan pantau secara real-time.',
        detail: 'Setelah formulir terbit, bagikan link ke calon peserta. Pantau respons masuk dari dasbor, lihat statistik pendaftaran, dan ekspor data kapan pun dibutuhkan.',
        lottie: 'landingStepMonitor',
        icon: Rocket,
    },
];

const { visible } = useViewportReveal({ targetId: 'features-how', threshold: 0.12 });
</script>

<template>
    <section id="features-how" class="bg-muted/30 py-24 md:py-32">
        <div class="mx-auto max-w-7xl px-6 lg:px-10">
            <div
                :class="[
                    'mx-auto mb-16 max-w-2xl text-center transition-all duration-500',
                    visible ? 'translate-y-0 opacity-100' : 'translate-y-4 opacity-0',
                ]"
            >
                <p class="text-primary text-xs font-semibold tracking-[0.2em] uppercase">Cara Kerja</p>
                <h2 class="font-display text-foreground mt-3 text-2xl font-bold tracking-tight sm:text-3xl">
                    Dari nol sampai siap terima pendaftar
                </h2>
                <p class="text-muted-foreground mx-auto mt-3 max-w-lg text-base leading-relaxed">
                    Ikuti tiga langkah sederhana ini dan acara Anda langsung bisa menerima pendaftaran.
                </p>
            </div>

            <div class="flex flex-col gap-12 lg:gap-16">
                <div
                    v-for="(step, i) in steps"
                    :key="step.num"
                    :class="[
                        'grid items-center gap-10 transition-all duration-600 lg:grid-cols-2 lg:gap-16',
                        visible ? 'translate-y-0 opacity-100' : 'translate-y-6 opacity-0',
                    ]"
                    :style="{ transitionDelay: `${100 + i * 120}ms` }"
                >
                    <!-- Lottie card — alternates position -->
                    <div :class="['order-2', i % 2 === 0 ? 'lg:order-2' : 'lg:order-1']">
                        <div
                            class="border-border/30 bg-background/80 overflow-hidden rounded-2xl border p-6 shadow-sm sm:p-8"
                        >
                            <LocalLottie :name="step.lottie" :height="200" width="100%" />
                        </div>
                    </div>

                    <!-- Text content -->
                    <div :class="['order-1', i % 2 === 0 ? 'lg:order-1' : 'lg:order-2']">
                        <div class="mb-4 flex items-center gap-3">
                            <div
                                class="bg-primary text-primary-foreground flex size-10 items-center justify-center rounded-full"
                            >
                                <component :is="step.icon" class="size-5" />
                            </div>
                            <span class="text-primary/40 text-sm font-bold tracking-wider">LANGKAH {{ step.num }}</span>
                        </div>

                        <h3 class="text-foreground text-xl font-bold tracking-tight sm:text-2xl">{{ step.title }}</h3>
                        <p class="text-muted-foreground mt-2 text-sm font-medium">{{ step.desc }}</p>
                        <p class="text-muted-foreground mt-4 text-sm leading-relaxed">{{ step.detail }}</p>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>
