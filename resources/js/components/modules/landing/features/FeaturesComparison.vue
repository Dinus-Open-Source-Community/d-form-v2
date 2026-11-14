<script setup lang="ts">
import { Check, X, Minus } from 'lucide-vue-next';
import { useViewportReveal } from '@/hooks/useViewportReveal';

interface Row {
    feature: string;
    desc: string;
    dform: 'yes' | 'no' | 'partial';
    manual: 'yes' | 'no' | 'partial';
}

const rows: Row[] = [
    { feature: 'Form Builder Visual', desc: 'Rancang formulir dengan drag & drop', dform: 'yes', manual: 'no' },
    { feature: 'Dasbor Real-time', desc: 'Pantau data pendaftar secara langsung', dform: 'yes', manual: 'no' },
    { feature: 'Multi-Acara', desc: 'Kelola banyak acara dari satu akun', dform: 'yes', manual: 'no' },
    { feature: 'Ekspor Data', desc: 'Unduh ke CSV atau spreadsheet', dform: 'yes', manual: 'partial' },
    { feature: 'Validasi Otomatis', desc: 'Cek format dan field wajib otomatis', dform: 'yes', manual: 'no' },
    { feature: 'Responsif', desc: 'Optimal di semua ukuran layar', dform: 'yes', manual: 'no' },
    { feature: 'Keamanan Berlapis', desc: 'Auth, role-based access, validasi', dform: 'yes', manual: 'no' },
    { feature: 'Notifikasi Real-time', desc: 'Pemberitahuan pendaftar baru', dform: 'yes', manual: 'no' },
    { feature: 'Kustomisasi Branding', desc: 'Banner, warna, dan identitas acara', dform: 'yes', manual: 'partial' },
    { feature: 'Laporan & Statistik', desc: 'Ringkasan data untuk pelaporan', dform: 'yes', manual: 'partial' },
];

const { visible } = useViewportReveal({ targetId: 'features-compare', threshold: 0.08 });
</script>

<template>
    <section id="features-compare" class="bg-muted/30 py-24 md:py-32">
        <div class="mx-auto max-w-4xl px-6 lg:px-10">
            <div
                :class="[
                    'mx-auto mb-14 max-w-2xl text-center transition-all duration-500',
                    visible ? 'translate-y-0 opacity-100' : 'translate-y-4 opacity-0',
                ]"
            >
                <p class="text-primary text-xs font-semibold tracking-[0.2em] uppercase">Perbandingan</p>
                <h2 class="font-display text-foreground mt-3 text-2xl font-bold tracking-tight sm:text-3xl">
                    DForm vs cara manual
                </h2>
                <p class="text-muted-foreground mx-auto mt-3 max-w-lg text-base leading-relaxed">
                    Lihat perbedaan nyata antara mengelola pendaftaran acara dengan DForm dibandingkan cara konvensional
                    menggunakan spreadsheet atau formulir manual.
                </p>
            </div>

            <div
                :class="[
                    'border-border/40 bg-card overflow-hidden rounded-2xl border shadow-sm transition-all duration-500',
                    visible ? 'opacity-100' : 'opacity-0',
                ]"
            >
                <div
                    class="border-border/40 bg-muted/30 grid grid-cols-[1fr_100px_100px] items-center border-b px-6 py-4 sm:grid-cols-[1fr_120px_120px]"
                >
                    <span class="text-muted-foreground text-xs font-semibold tracking-wider uppercase">Fitur</span>
                    <span class="text-primary text-center text-xs font-bold tracking-wider uppercase">DForm</span>
                    <span class="text-muted-foreground text-center text-xs font-semibold tracking-wider uppercase"
                        >Manual</span
                    >
                </div>

                <div
                    v-for="(row, i) in rows"
                    :key="row.feature"
                    :class="[
                        'grid grid-cols-[1fr_100px_100px] items-center px-6 py-4 sm:grid-cols-[1fr_120px_120px]',
                        i < rows.length - 1 ? 'border-border/20 border-b' : '',
                    ]"
                >
                    <div>
                        <p class="text-foreground text-sm font-medium">{{ row.feature }}</p>
                        <p class="text-muted-foreground mt-0.5 hidden text-xs sm:block">{{ row.desc }}</p>
                    </div>
                    <div class="flex justify-center">
                        <div class="flex size-7 items-center justify-center rounded-full bg-green-500/10">
                            <Check class="size-4 text-green-600" />
                        </div>
                    </div>
                    <div class="flex justify-center">
                        <div
                            v-if="row.manual === 'yes'"
                            class="flex size-7 items-center justify-center rounded-full bg-green-500/10"
                        >
                            <Check class="size-4 text-green-600" />
                        </div>
                        <div
                            v-else-if="row.manual === 'partial'"
                            class="flex size-7 items-center justify-center rounded-full bg-amber-500/10"
                        >
                            <Minus class="size-4 text-amber-500" />
                        </div>
                        <div v-else class="flex size-7 items-center justify-center rounded-full bg-red-500/10">
                            <X class="size-4 text-red-400" />
                        </div>
                    </div>
                </div>
            </div>

            <div class="text-muted-foreground mt-6 flex flex-wrap items-center justify-center gap-6 text-xs">
                <span class="flex items-center gap-2">
                    <div class="flex size-5 items-center justify-center rounded-full bg-green-500/10">
                        <Check class="size-3 text-green-600" />
                    </div>
                    Tersedia
                </span>
                <span class="flex items-center gap-2">
                    <div class="flex size-5 items-center justify-center rounded-full bg-amber-500/10">
                        <Minus class="size-3 text-amber-500" />
                    </div>
                    Terbatas
                </span>
                <span class="flex items-center gap-2">
                    <div class="flex size-5 items-center justify-center rounded-full bg-red-500/10">
                        <X class="size-3 text-red-400" />
                    </div>
                    Tidak tersedia
                </span>
            </div>
        </div>
    </section>
</template>
