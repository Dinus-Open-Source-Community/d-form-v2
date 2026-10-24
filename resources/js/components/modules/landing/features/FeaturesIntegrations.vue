<script setup lang="ts">
import { ref, onMounted } from 'vue'
import LocalLottie from '@/components/core/LocalLottie.vue'
import { FileSpreadsheet, Bell, Webhook, Code2, Link2, Share2 } from 'lucide-vue-next'
import type { Component } from 'vue'

interface Integration {
    icon: Component
    title: string
    desc: string
}

const integrations: Integration[] = [
    {
        icon: FileSpreadsheet,
        title: 'Ekspor CSV & Excel',
        desc: 'Unduh data peserta dalam format spreadsheet yang langsung bisa diolah.',
    },
    {
        icon: Bell,
        title: 'Notifikasi Real-time',
        desc: 'Dapatkan pemberitahuan setiap kali ada pendaftar baru masuk.',
    },
    {
        icon: Link2,
        title: 'Shareable Link',
        desc: 'Bagikan link formulir langsung ke WhatsApp, Instagram, atau platform lain.',
    },
    {
        icon: Share2,
        title: 'Embed Formulir',
        desc: 'Sematkan formulir ke website organisasi Anda dengan kode embed sederhana.',
    },
    {
        icon: Webhook,
        title: 'Webhook',
        desc: 'Hubungkan ke sistem eksternal dengan webhook untuk otomasi alur kerja.',
    },
    {
        icon: Code2,
        title: 'API Access',
        desc: 'Akses data pendaftaran secara programatik untuk integrasi custom.',
    },
]

const visible = ref(false)
onMounted(() => {
    const obs = new IntersectionObserver(
        ([e]) => { if (e?.isIntersecting) { visible.value = true; obs.disconnect() } },
        { threshold: 0.1 },
    )
    const el = document.getElementById('features-integrations')
    if (el) obs.observe(el)
})
</script>

<template>
    <section id="features-integrations" class="py-24 md:py-32">
        <div class="mx-auto max-w-7xl px-6 lg:px-10">
            <div class="grid items-center gap-14 lg:grid-cols-2 lg:gap-20">
                <!-- Lottie -->
                <div
                    :class="[
 'transition-all duration-600',
 visible ? 'translate-y-0 opacity-100' : 'translate-y-6 opacity-0',
 ]"
                >
                    <div class="overflow-hidden rounded-2xl border border-border/30 bg-gradient-to-b from-card to-muted/20 p-6 shadow-sm sm:p-8">
                        <LocalLottie name="featuresIntegrations" :height="300" width="100%" />
                    </div>
                </div>

                <!-- Text content -->
                <div
                    :class="[
 'transition-all delay-75 duration-600',
 visible ? 'translate-y-0 opacity-100' : 'translate-y-4 opacity-0',
 ]"
                >
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-primary">Integrasi & Konektivitas</p>
                    <h2 class="mt-3 font-display text-2xl font-bold tracking-tight text-foreground sm:text-3xl">
                        Terhubung dengan alur kerja yang sudah Anda gunakan
                    </h2>
                    <p class="mt-4 text-base leading-relaxed text-muted-foreground">
                        DForm tidak berdiri sendiri — platform ini dirancang untuk bekerja bersama
                        tools dan workflow yang sudah ada di organisasi Anda.
                    </p>

                    <div class="mt-8 grid gap-5 sm:grid-cols-2">
                        <div
                            v-for="(item, i) in integrations"
                            :key="item.title"
                            :class="[
 'flex gap-3.5 transition-all duration-400',
 visible ? 'translate-y-0 opacity-100' : 'translate-y-3 opacity-0',
 ]"
                            :style="{ transitionDelay: `${150 + i * 60}ms` }"
                        >
                            <div class="mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary">
                                <component :is="item.icon" class="size-4" />
                            </div>
                            <div>
                                <h3 class="text-sm font-semibold text-foreground">{{ item.title }}</h3>
                                <p class="mt-0.5 text-xs leading-relaxed text-muted-foreground">{{ item.desc }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>
