<script setup lang="ts">
import { ref, onMounted } from 'vue';
import LocalLottie from '@/components/core/LocalLottie.vue';
import type { TLottieName } from '@/lib/lotties';
import { CalendarPlus, PenTool, Rocket } from 'lucide-vue-next';
import type { Component } from 'vue';
import { findElementById } from '@/adapters/browser/domQuery';

interface Step {
    num: string;
    title: string;
    desc: string;
    lottie: TLottieName;
    icon: Component;
}

const steps: Step[] = [
    {
        num: '01',
        title: 'Buat acara baru',
        desc: 'Tentukan nama acara, tanggal pelaksanaan, kuota peserta, dan deskripsi singkat. Hanya butuh beberapa detik.',
        lottie: 'landingStepCreate',
        icon: CalendarPlus,
    },
    {
        num: '02',
        title: 'Rancang formulir pendaftaran',
        desc: 'Gunakan form builder visual untuk menambahkan field — teks, dropdown, checkbox, dan lainnya. Atur validasi dan pratinjau hasilnya.',
        lottie: 'landingStepBuild',
        icon: PenTool,
    },
    {
        num: '03',
        title: 'Publikasi & pantau data',
        desc: 'Bagikan link formulir ke calon peserta. Pantau respons yang masuk secara real-time dan ekspor data kapan saja.',
        lottie: 'landingStepMonitor',
        icon: Rocket,
    },
];

const visible = ref(false);
onMounted(() => {
    const obs = new IntersectionObserver(
        ([e]) => {
            if (e?.isIntersecting) {
                visible.value = true;
                obs.disconnect();
            }
        },
        { threshold: 0.15 }
    );
    const el = findElementById('section-steps');
    if (el !== null) obs.observe(el);
});
</script>

<template>
    <section id="section-steps" class="bg-muted/30 py-24 md:py-32">
        <div class="mx-auto max-w-7xl px-6 lg:px-10">
            <div
                :class="[
                    'mx-auto mb-16 max-w-2xl text-center transition-all duration-500',
                    visible ? 'translate-y-0 opacity-100' : 'translate-y-4 opacity-0',
                ]"
            >
                <p class="text-primary text-xs font-semibold tracking-[0.2em] uppercase">Cara Kerja</p>
                <h2 class="font-display text-foreground mt-3 text-2xl font-bold tracking-tight sm:text-3xl">
                    Mulai dari nol dalam 3 langkah
                </h2>
                <p class="text-muted-foreground mx-auto mt-3 max-w-lg text-base leading-relaxed">
                    Tidak perlu keahlian teknis. Siapa pun bisa membuat dan mengelola formulir acara dalam hitungan
                    menit.
                </p>
            </div>

            <div class="relative grid gap-10 sm:grid-cols-3 lg:gap-8">
                <!-- Connecting line (desktop) -->
                <div
                    class="via-border pointer-events-none absolute top-[4.5rem] right-[17%] left-[17%] hidden h-px bg-gradient-to-r from-transparent to-transparent sm:block"
                    aria-hidden="true"
                />

                <div
                    v-for="(step, i) in steps"
                    :key="step.num"
                    :class="[
                        'relative flex flex-col items-center text-center transition-all duration-500',
                        visible ? 'translate-y-0 opacity-100' : 'translate-y-6 opacity-0',
                    ]"
                    :style="{ transitionDelay: `${120 + i * 100}ms` }"
                >
                    <!-- Step number -->
                    <div
                        class="bg-primary text-primary-foreground relative z-10 mb-6 flex size-12 items-center justify-center rounded-full text-sm font-bold shadow-sm"
                    >
                        <component :is="step.icon" class="size-5" />
                    </div>

                    <!-- Lottie card -->
                    <div
                        class="border-border/30 bg-background/80 mb-6 flex w-full justify-center overflow-hidden rounded-xl border p-5"
                    >
                        <LocalLottie :name="step.lottie" :height="140" :width="140" />
                    </div>

                    <h3 class="text-foreground text-base font-semibold">{{ step.title }}</h3>
                    <p class="text-muted-foreground mt-2 max-w-[280px] text-sm leading-relaxed">{{ step.desc }}</p>
                </div>
            </div>
        </div>
    </section>
</template>
