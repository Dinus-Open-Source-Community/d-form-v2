<script setup lang="ts">
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
    Eye,
    ToggleRight,
    FileText,
} from 'lucide-vue-next';
import type { Component } from 'vue';
import { useViewportReveal } from '@/hooks/useViewportReveal';

interface Feature {
    title: string;
    desc: string;
    icon: Component;
    highlight?: boolean;
}

const features: Feature[] = [
    {
        title: 'Visual Form Builder',
        desc: 'Rancang formulir dengan drag & drop. Tambahkan teks, dropdown, checkbox, radio, dan banyak lagi — tanpa menulis kode sama sekali.',
        icon: GripVertical,
        highlight: true,
    },
    {
        title: 'Dasbor Analitik',
        desc: 'Pantau jumlah pendaftar, tren harian, dan status setiap acara dari satu halaman ringkasan yang interaktif.',
        icon: BarChart3,
    },
    {
        title: 'Multi-Event Management',
        desc: 'Kelola puluhan acara secara bersamaan. Setiap acara punya formulir, peserta, dan konfigurasi independen.',
        icon: Layers,
    },
    {
        title: 'Ekspor ke Spreadsheet',
        desc: 'Unduh seluruh data pendaftaran dalam format yang siap diproses untuk kebutuhan administrasi dan pelaporan tim.',
        icon: Download,
    },
    {
        title: 'Responsif di Semua Perangkat',
        desc: 'Formulir, dasbor, dan form builder dirancang mobile-first. Peserta bisa mendaftar dari mana saja, kapan saja.',
        icon: Smartphone,
    },
    {
        title: 'Keamanan & Otorisasi',
        desc: 'Data peserta dilindungi autentikasi, validasi field berlapis, dan akses berbasis peran agar tetap aman.',
        icon: ShieldCheck,
    },
    {
        title: 'Kustomisasi Branding',
        desc: 'Sesuaikan banner, warna tema, dan identitas visual acara agar formulir terlihat profesional sesuai organisasi Anda.',
        icon: Palette,
    },
    {
        title: 'Real-time Updates',
        desc: 'Setiap data yang masuk langsung muncul di dasbor tanpa refresh. Keputusan bisa diambil lebih cepat dengan data terkini.',
        icon: Clock,
    },
    {
        title: 'Manajemen Tim',
        desc: 'Undang anggota tim sebagai admin atau moderator. Kolaborasi lebih efisien dalam mengelola peserta dan acara.',
        icon: Users,
    },
    {
        title: 'Pratinjau Formulir',
        desc: 'Cek tampilan formulir sebelum dipublikasikan. Pastikan setiap field sudah sesuai, baik secara visual maupun fungsional.',
        icon: Eye,
    },
    {
        title: 'Validasi Otomatis',
        desc: 'Atur field wajib, format email, batasan karakter, dan aturan custom — semua divalidasi otomatis saat peserta mengisi.',
        icon: ToggleRight,
    },
    {
        title: 'Laporan Acara',
        desc: 'Buat laporan komprehensif tentang pelaksanaan acara, data peserta, dan statistik pendaftaran untuk dokumentasi.',
        icon: FileText,
    },
];

const { visible } = useViewportReveal({ targetId: 'features-grid', threshold: 0.06 });
</script>

<template>
    <section id="features-grid" class="border-border/30 border-t py-24 md:py-32">
        <div class="mx-auto max-w-7xl px-6 lg:px-10">
            <div
                :class="[
                    'mx-auto mb-14 max-w-2xl text-center transition-all duration-500',
                    visible ? 'translate-y-0 opacity-100' : 'translate-y-4 opacity-0',
                ]"
            >
                <p class="text-primary text-xs font-semibold tracking-[0.2em] uppercase">Keunggulan</p>
                <h2 class="font-display text-foreground mt-3 text-2xl font-bold tracking-tight sm:text-3xl">
                    Fitur lengkap untuk setiap kebutuhan
                </h2>
                <p class="text-muted-foreground mx-auto mt-3 max-w-lg text-base leading-relaxed">
                    Dari pembuatan formulir hingga pelaporan — semua fitur dirancang agar penyelenggara bisa fokus pada
                    acara, bukan pada tools.
                </p>
            </div>

            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                <div
                    v-for="(feat, i) in features"
                    :key="feat.title"
                    :class="[
                        'group rounded-2xl border p-6 transition-all duration-400',
                        feat.highlight
                            ? 'border-primary/25 bg-primary/[0.04] ring-primary/10 hover:bg-primary/[0.06] ring-1'
                            : 'border-border/40 bg-muted/20 hover:border-primary/20 hover:bg-muted/40',
                        visible ? 'translate-y-0 opacity-100' : 'translate-y-5 opacity-0',
                    ]"
                    :style="{ transitionDelay: `${60 + i * 40}ms` }"
                >
                    <div
                        :class="[
                            'mb-4 flex size-10 items-center justify-center rounded-xl transition-colors duration-200',
                            feat.highlight
                                ? 'bg-primary text-primary-foreground'
                                : 'bg-primary/10 text-primary group-hover:bg-primary/15',
                        ]"
                    >
                        <component :is="feat.icon" class="size-5" />
                    </div>
                    <h3 class="text-foreground text-sm font-semibold">{{ feat.title }}</h3>
                    <p class="text-muted-foreground mt-2 text-xs leading-relaxed">{{ feat.desc }}</p>
                </div>
            </div>
        </div>
    </section>
</template>
