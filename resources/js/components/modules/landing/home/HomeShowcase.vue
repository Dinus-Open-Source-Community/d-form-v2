<script setup lang="ts">
import { ref, onMounted } from 'vue';
import LocalLottie from '@/components/core/LocalLottie.vue';
import { CheckCircle2, TrendingUp, Globe, Lock } from 'lucide-vue-next';
import type { Component } from 'vue';
import { findElementById } from '@/adapters/browser/domQuery';

const visible = ref(false);
onMounted(() => {
    const obs = new IntersectionObserver(
        ([e]) => {
            if (e?.isIntersecting) {
                visible.value = true;
                obs.disconnect();
            }
        },
        { threshold: 0.12 }
    );
    const el = findElementById('section-showcase');
    if (el !== null) obs.observe(el);
});

interface Advantage {
    icon: Component;
    title: string;
    desc: string;
}

const advantages: Advantage[] = [
    {
        icon: TrendingUp,
        title: 'Data terstruktur & terukur',
        desc: 'Setiap respons tersimpan rapi dan siap diolah untuk kebutuhan laporan acara.',
    },
    {
        icon: Globe,
        title: 'Akses dari mana saja',
        desc: 'Peserta cukup buka link formulir — tidak perlu install aplikasi apa pun.',
    },
    {
        icon: Lock,
        title: 'Privasi terjaga',
        desc: 'Data peserta hanya bisa diakses oleh penyelenggara yang berwenang.',
    },
    {
        icon: CheckCircle2,
        title: 'Validasi otomatis',
        desc: 'Field wajib, format email, dan batas karakter — semua divalidasi secara otomatis.',
    },
];
</script>

<template>
    <section id="section-showcase" class="bg-muted/30 relative overflow-hidden py-24 md:py-32">
        <div
            class="bg-primary/[0.04] pointer-events-none absolute top-1/2 -left-40 size-[500px] -translate-y-1/2 rounded-full blur-[120px]"
            aria-hidden="true"
        />

        <div class="relative mx-auto max-w-7xl px-6 lg:px-10">
            <div class="grid items-center gap-12 lg:grid-cols-2 lg:gap-20">
                <!-- Lottie illustration -->
                <div
                    :class="[
                        'order-2 transition-all duration-600 lg:order-1',
                        visible ? 'translate-y-0 opacity-100' : 'translate-y-6 opacity-0',
                    ]"
                >
                    <div
                        class="border-border/30 bg-background/80 overflow-hidden rounded-2xl border p-6 shadow-sm backdrop-blur-sm sm:p-8"
                    >
                        <LocalLottie name="landingShowcase" :height="280" width="100%" />
                    </div>
                </div>

                <!-- Text content -->
                <div
                    :class="[
                        'order-1 transition-all delay-75 duration-600 lg:order-2',
                        visible ? 'translate-y-0 opacity-100' : 'translate-y-4 opacity-0',
                    ]"
                >
                    <p class="text-primary text-xs font-semibold tracking-[0.2em] uppercase">Mengapa DForm?</p>
                    <h2 class="font-display text-foreground mt-3 text-2xl font-bold tracking-tight sm:text-3xl">
                        Dibuat untuk penyelenggara yang menghargai waktu
                    </h2>
                    <p class="text-muted-foreground mt-4 text-base leading-relaxed">
                        Tidak perlu lagi membuat formulir manual di spreadsheet atau mengelola data peserta lewat grup
                        chat. DForm mengotomatisasi semuanya.
                    </p>

                    <div class="mt-8 grid gap-5 sm:grid-cols-2">
                        <div v-for="item in advantages" :key="item.title" class="flex gap-3.5">
                            <div
                                class="bg-primary/10 text-primary mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-full"
                            >
                                <component :is="item.icon" class="size-4" />
                            </div>
                            <div>
                                <h3 class="text-foreground text-sm font-semibold">{{ item.title }}</h3>
                                <p class="text-muted-foreground mt-1 text-xs leading-relaxed">{{ item.desc }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>
