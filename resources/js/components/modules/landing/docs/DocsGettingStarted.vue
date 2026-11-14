<script setup lang="ts">
import { BookOpen, Rocket, FormInput, BarChart3, ShieldCheck } from 'lucide-vue-next';
</script>

<template>
    <section id="introduction" data-doc-section class="scroll-mt-24">
        <h2 class="text-foreground flex items-center gap-3 text-2xl font-bold tracking-tight">
            <BookOpen class="text-primary size-6" />
            Pengenalan
        </h2>
        <p class="text-muted-foreground mt-4 text-sm leading-relaxed">
            <strong class="text-foreground">DForm</strong> adalah platform web untuk membuat formulir pendaftaran acara,
            mengumpulkan data peserta, dan mengelola semuanya dari satu dasbor. Dibangun untuk
            <strong class="text-foreground">DOSCOM (Dian Nuswantoro Computer Community)</strong> dan organisasi sejenis
            yang membutuhkan alur pendaftaran yang simpel, terstruktur, dan profesional.
        </p>
        <div class="mt-6 grid gap-3 sm:grid-cols-3">
            <div class="border-border/40 bg-muted/20 rounded-xl border p-4">
                <FormInput class="text-primary mb-2 size-5" />
                <h3 class="text-foreground text-sm font-semibold">Form Builder Visual</h3>
                <p class="text-muted-foreground mt-1 text-xs">Drag & drop, tanpa coding.</p>
            </div>
            <div class="border-border/40 bg-muted/20 rounded-xl border p-4">
                <BarChart3 class="text-primary mb-2 size-5" />
                <h3 class="text-foreground text-sm font-semibold">Real-time Dashboard</h3>
                <p class="text-muted-foreground mt-1 text-xs">Pantau data secara langsung.</p>
            </div>
            <div class="border-border/40 bg-muted/20 rounded-xl border p-4">
                <ShieldCheck class="text-primary mb-2 size-5" />
                <h3 class="text-foreground text-sm font-semibold">Aman & Terverifikasi</h3>
                <p class="text-muted-foreground mt-1 text-xs">Role-based access control.</p>
            </div>
        </div>
    </section>

    <hr class="border-border/30 my-12" />

    <section id="quick-start" data-doc-section class="scroll-mt-24">
        <h2 class="text-foreground flex items-center gap-3 text-2xl font-bold tracking-tight">
            <Rocket class="text-primary size-6" />
            Quick Start
        </h2>
        <p class="text-muted-foreground mt-4 text-sm leading-relaxed">
            Mulai dari nol hingga siap menerima pendaftar dalam 5 menit.
        </p>
        <ol class="mt-6 flex flex-col gap-4">
            <li class="flex gap-4">
                <span
                    class="bg-primary text-primary-foreground flex size-8 shrink-0 items-center justify-center rounded-lg text-xs font-bold"
                    >1</span
                >
                <div>
                    <h3 class="text-foreground text-sm font-semibold">Buat akun</h3>
                    <p class="text-muted-foreground mt-1 text-xs">
                        Daftar di <code>/auth/register</code> dengan email atau melalui Google/GitHub OAuth.
                    </p>
                </div>
            </li>
            <li class="flex gap-4">
                <span
                    class="bg-primary text-primary-foreground flex size-8 shrink-0 items-center justify-center rounded-lg text-xs font-bold"
                    >2</span
                >
                <div>
                    <h3 class="text-foreground text-sm font-semibold">Buat acara</h3>
                    <p class="text-muted-foreground mt-1 text-xs">
                        Di dasbor penyelenggara, klik "Buat Acara". Isi judul, tanggal, lokasi, kuota, dan banner.
                        Publikasikan acara.
                    </p>
                </div>
            </li>
            <li class="flex gap-4">
                <span
                    class="bg-primary text-primary-foreground flex size-8 shrink-0 items-center justify-center rounded-lg text-xs font-bold"
                    >3</span
                >
                <div>
                    <h3 class="text-foreground text-sm font-semibold">Rancang formulir</h3>
                    <p class="text-muted-foreground mt-1 text-xs">
                        Masuk ke Form Builder. Tambahkan field (teks, dropdown, checkbox, dsb.) dengan drag & drop. Atur
                        validasi lalu simpan.
                    </p>
                </div>
            </li>
            <li class="flex gap-4">
                <span
                    class="bg-primary text-primary-foreground flex size-8 shrink-0 items-center justify-center rounded-lg text-xs font-bold"
                    >4</span
                >
                <div>
                    <h3 class="text-foreground text-sm font-semibold">Bagikan & pantau</h3>
                    <p class="text-muted-foreground mt-1 text-xs">
                        Bagikan link formulir. Lihat respons masuk secara real-time di dasbor. Ekspor data kapan saja.
                    </p>
                </div>
            </li>
        </ol>
    </section>
</template>
