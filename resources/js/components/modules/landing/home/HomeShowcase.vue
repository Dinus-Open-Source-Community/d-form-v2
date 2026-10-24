<script setup lang="ts">
import { ref, onMounted } from 'vue'
import LocalLottie from '@/components/core/LocalLottie.vue'
import { CheckCircle2, TrendingUp, Globe, Lock } from 'lucide-vue-next'
import type { Component } from 'vue'

const visible = ref(false)
onMounted(() => {
    const obs = new IntersectionObserver(
        ([e]) => { if (e?.isIntersecting) { visible.value = true; obs.disconnect() } },
        { threshold: 0.12 },
    )
    const el = document.getElementById('section-showcase')
    if (el) obs.observe(el)
})

interface Advantage {
    icon: Component
    title: string
    desc: string
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
]
</script>

<template>
    <section id="section-showcase" class="relative overflow-hidden bg-muted/30 py-24 md:py-32">
        <div
            class="pointer-events-none absolute -left-40 top-1/2 -translate-y-1/2 size-[500px] rounded-full bg-primary/[0.04] blur-[120px]"
            aria-hidden="true"
        />

        <div class="relative mx-auto max-w-7xl px-6 lg:px-10">
            <div class="grid items-center gap-12 lg:grid-cols-2 lg:gap-20">
                <!-- Lottie illustration -->
                <div
                    :class="[
 'order-2 lg:order-1 transition-all duration-600',
 visible ? 'translate-y-0 opacity-100' : 'translate-y-6 opacity-0',
 ]"
                >
                    <div class="overflow-hidden rounded-2xl border border-border/30 bg-background/80 p-6 shadow-sm backdrop-blur-sm sm:p-8">
                        <LocalLottie name="landingShowcase" :height="280" width="100%" />
                    </div>
                </div>

                <!-- Text content -->
                <div
                    :class="[
 'order-1 lg:order-2 transition-all delay-75 duration-600',
 visible ? 'translate-y-0 opacity-100' : 'translate-y-4 opacity-0',
 ]"
                >
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-primary">Mengapa DForm?</p>
                    <h2 class="mt-3 font-display text-2xl font-bold tracking-tight text-foreground sm:text-3xl">
                        Dibuat untuk penyelenggara yang menghargai waktu
                    </h2>
                    <p class="mt-4 text-base leading-relaxed text-muted-foreground">
                        Tidak perlu lagi membuat formulir manual di spreadsheet atau mengelola
                        data peserta lewat grup chat. DForm mengotomatisasi semuanya.
                    </p>

                    <div class="mt-8 grid gap-5 sm:grid-cols-2">
                        <div
                            v-for="item in advantages"
                            :key="item.title"
                            class="flex gap-3.5"
                        >
                            <div class="mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary">
                                <component :is="item.icon" class="size-4" />
                            </div>
                            <div>
                                <h3 class="text-sm font-semibold text-foreground">{{ item.title }}</h3>
                                <p class="mt-1 text-xs leading-relaxed text-muted-foreground">{{ item.desc }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>
