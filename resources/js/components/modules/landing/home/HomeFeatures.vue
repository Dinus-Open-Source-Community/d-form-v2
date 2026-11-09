<script setup lang="ts">
import { ref, onMounted } from 'vue'
import {
    GripVertical,
    BarChart3,
    Layers,
    Download,
    Smartphone,
    ShieldCheck,
    Palette,
    Clock,
    Users,
} from 'lucide-vue-next'
import type { Component } from 'vue'
import { findElementById } from '@/adapters/browser/domQuery'

interface Feature {
    title: string
    desc: string
    icon: Component
    accent?: boolean
}

const features: Feature[] = [
    {
        title: 'Visual Form Builder',
        desc: 'Rancang formulir secara visual dengan drag & drop. Tambahkan teks, dropdown, checkbox, radio, dan lainnya — tanpa menulis satu baris kode pun.',
        icon: GripVertical,
        accent: true,
    },
    {
        title: 'Dasbor Analitik',
        desc: 'Pantau jumlah pendaftar, tren harian, dan status acara dari satu halaman ringkas dan interaktif.',
        icon: BarChart3,
    },
    {
        title: 'Multi-Event Management',
        desc: 'Kelola puluhan acara sekaligus. Setiap acara punya formulir, peserta, dan pengaturan sendiri.',
        icon: Layers,
    },
    {
        title: 'Ekspor ke Spreadsheet',
        desc: 'Unduh seluruh data peserta dalam format yang siap diproses untuk kebutuhan administrasi dan pelaporan.',
        icon: Download,
    },
    {
        title: 'Responsif di Semua Perangkat',
        desc: 'Formulir dan dasbor tampil sempurna di desktop, tablet, maupun ponsel. Peserta bisa mendaftar dari mana saja.',
        icon: Smartphone,
    },
    {
        title: 'Keamanan Berlapis',
        desc: 'Data peserta dilindungi autentikasi, validasi field, dan akses berbasis peran. Hanya pihak berwenang yang bisa mengakses.',
        icon: ShieldCheck,
    },
    {
        title: 'Kustomisasi Tampilan',
        desc: 'Sesuaikan banner, warna, dan branding acara agar formulir terlihat profesional dan sesuai identitas organisasi Anda.',
        icon: Palette,
    },
    {
        title: 'Real-time Response',
        desc: 'Setiap data yang masuk langsung tersedia di dasbor tanpa perlu refresh halaman. Respons instan, keputusan lebih cepat.',
        icon: Clock,
    },
    {
        title: 'Manajemen Tim',
        desc: 'Undang anggota tim sebagai admin atau moderator acara. Kolaborasi lebih efisien dalam mengelola peserta.',
        icon: Users,
    },
]

const visible = ref(false)
onMounted(() => {
    const obs = new IntersectionObserver(
        ([e]) => { if (e?.isIntersecting) { visible.value = true; obs.disconnect() } },
        { threshold: 0.08 },
    )
    const el = findElementById('section-features')
    if (el !== null) obs.observe(el)
})
</script>

<template>
    <section id="section-features" class="py-24 md:py-32">
        <div class="mx-auto max-w-7xl px-6 lg:px-10">
            <div
                :class="[
 'mx-auto mb-14 max-w-2xl text-center transition-all duration-500',
 visible ? 'translate-y-0 opacity-100' : 'translate-y-4 opacity-0',
 ]"
            >
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-primary">Fitur Unggulan</p>
                <h2 class="mt-3 font-display text-2xl font-bold tracking-tight text-foreground sm:text-3xl">
                    Semua yang Anda butuhkan, tidak lebih
                </h2>
                <p class="mt-3 max-w-lg mx-auto text-base leading-relaxed text-muted-foreground">
                    Dirancang khusus untuk penyelenggara acara kampus dan organisasi —
                    fokus pada kemudahan, bukan kompleksitas.
                </p>
            </div>

            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <div
                    v-for="(feat, i) in features"
                    :key="feat.title"
                    :class="[
 'group rounded-2xl border border-border/40 bg-muted/20 p-6 transition-all duration-400 hover:border-primary/20 hover:bg-muted/40',
 feat.accent ? 'sm:col-span-2 lg:col-span-1 ring-1 ring-primary/10 bg-primary/[0.03]' : '',
 visible ? 'translate-y-0 opacity-100' : 'translate-y-5 opacity-0',
 ]"
                    :style="{ transitionDelay: `${80 + i * 50}ms` }"
                >
                    <div
                        :class="[
 'mb-4 flex size-10 items-center justify-center rounded-xl transition-colors duration-200',
 feat.accent
 ? 'bg-primary text-primary-foreground'
 : 'bg-primary/10 text-primary group-hover:bg-primary/15',
 ]"
                    >
                        <component :is="feat.icon" class="size-5" />
                    </div>
                    <h3 class="text-base font-semibold text-foreground">{{ feat.title }}</h3>
                    <p class="mt-2 text-sm leading-relaxed text-muted-foreground">
                        {{ feat.desc }}
                    </p>
                </div>
            </div>
        </div>
    </section>
</template>
