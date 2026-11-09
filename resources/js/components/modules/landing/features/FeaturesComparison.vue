<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { Check, X, Minus } from 'lucide-vue-next'
import { findElementById } from '@/adapters/browser/domQuery'

interface Row {
    feature: string
    desc: string
    dform: 'yes' | 'no' | 'partial'
    manual: 'yes' | 'no' | 'partial'
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
]

const visible = ref(false)
onMounted(() => {
    const obs = new IntersectionObserver(
        ([e]) => { if (e?.isIntersecting) { visible.value = true; obs.disconnect() } },
        { threshold: 0.08 },
    )
    const el = findElementById('features-compare')
    if (el !== null) obs.observe(el)
})
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
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-primary">Perbandingan</p>
                <h2 class="mt-3 font-display text-2xl font-bold tracking-tight text-foreground sm:text-3xl">
                    DForm vs cara manual
                </h2>
                <p class="mt-3 mx-auto max-w-lg text-base leading-relaxed text-muted-foreground">
                    Lihat perbedaan nyata antara mengelola pendaftaran acara dengan DForm
                    dibandingkan cara konvensional menggunakan spreadsheet atau formulir manual.
                </p>
            </div>

            <div
                :class="[
 'overflow-hidden rounded-2xl border border-border/40 bg-card shadow-sm transition-all duration-500',
 visible ? 'opacity-100' : 'opacity-0',
 ]"
            >
                <!-- Header -->
                <div class="grid grid-cols-[1fr_100px_100px] items-center border-b border-border/40 bg-muted/30 px-6 py-4 sm:grid-cols-[1fr_120px_120px]">
                    <span class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Fitur</span>
                    <span class="text-center text-xs font-bold uppercase tracking-wider text-primary">DForm</span>
                    <span class="text-center text-xs font-semibold uppercase tracking-wider text-muted-foreground">Manual</span>
                </div>

                <!-- Rows -->
                <div
                    v-for="(row, i) in rows"
                    :key="row.feature"
                    :class="[
 'grid grid-cols-[1fr_100px_100px] items-center px-6 py-4 sm:grid-cols-[1fr_120px_120px]',
 i < rows.length - 1 ? 'border-b border-border/20' : '',
 ]"
                >
                    <div>
                        <p class="text-sm font-medium text-foreground">{{ row.feature }}</p>
                        <p class="mt-0.5 text-xs text-muted-foreground hidden sm:block">{{ row.desc }}</p>
                    </div>
                    <div class="flex justify-center">
                        <div
                            class="flex size-7 items-center justify-center rounded-full bg-green-500/10"
                        >
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
                        <div
                            v-else
                            class="flex size-7 items-center justify-center rounded-full bg-red-500/10"
                        >
                            <X class="size-4 text-red-400" />
                        </div>
                    </div>
                </div>
            </div>

            <!-- Legend -->
            <div class="mt-6 flex flex-wrap items-center justify-center gap-6 text-xs text-muted-foreground">
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
