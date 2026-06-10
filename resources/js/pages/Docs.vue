<script setup lang="ts">
import LandingLayout from '@/layouts/LandingLayout.vue'
import { ref, onMounted, onUnmounted, nextTick, computed } from 'vue'
import {
    BookOpen, Rocket, FormInput, Users, ShieldCheck, BarChart3,
    QrCode, Download, Palette, CalendarPlus, PenTool, Eye,
    CheckCircle2, UserPlus, Search,
    ChevronRight, Menu, X, ArrowUp,
    ToggleRight, Layers, FileText, Database, Code2, Lock,
    Settings,
} from 'lucide-vue-next'
import SeoHead from '@/components/seo/SeoHead.vue'
import { usePage } from '@inertiajs/vue3'
import type { SharedSeoProps } from '@/types/seo'
import { routes } from '@/lib/routes'

const page = usePage()
const seo = computed(() => (page.props as { seo: SharedSeoProps }).seo)

const docsDescription = computed(
    () =>
        `Dokumentasi resmi ${seo.value.siteName}: registrasi, menjelajah acara, mengisi formulir, absensi QR, peran organizer dan tim, serta tips integrasi.`,
)

const docsJsonLd = computed<Record<string, unknown>>(() => ({
    '@context': 'https://schema.org',
    '@type': 'TechArticle',
    headline: 'Dokumentasi DForm',
    description: docsDescription.value,
    url: `${seo.value.siteUrl}${routes.landing.docs}`,
    isPartOf: {
        '@type': 'WebSite',
        name: seo.value.siteName,
        url: `${seo.value.siteUrl}${routes.home}`,
    },
}))

const activeSection = ref('introduction')
const mobileNavOpen = ref(false)
const showScrollTop = ref(false)

interface NavItem {
    id: string
    label: string
    children?: { id: string; label: string }[]
}

const navSections: { group: string; items: NavItem[] }[] = [
    {
        group: 'Mulai',
        items: [
            { id: 'introduction', label: 'Pengenalan' },
            { id: 'quick-start', label: 'Quick Start' },
        ],
    },
    {
        group: 'Panduan Pengguna',
        items: [
            { id: 'auth', label: 'Registrasi & Login' },
            { id: 'browse-events', label: 'Menjelajahi Acara' },
            { id: 'fill-form', label: 'Mengisi Formulir' },
            { id: 'team-registration', label: 'Pendaftaran Tim' },
            { id: 'my-registration', label: 'Status Pendaftaran' },
            { id: 'profile', label: 'Profil Pengguna' },
        ],
    },
    {
        group: 'Panduan Penyelenggara',
        items: [
            { id: 'organizer-dashboard', label: 'Dasbor Penyelenggara' },
            { id: 'event-management', label: 'Manajemen Acara' },
            { id: 'form-builder', label: 'Form Builder' },
            { id: 'field-types', label: 'Jenis Field' },
            { id: 'form-settings', label: 'Pengaturan Formulir' },
            { id: 'submissions', label: 'Mengelola Submisi' },
            { id: 'registrants', label: 'Daftar Peserta' },
            { id: 'qr-scan', label: 'QR & Absensi' },
            { id: 'export', label: 'Ekspor Data' },
            { id: 'reporting', label: 'Laporan Acara' },
        ],
    },
    {
        group: 'Referensi Teknis',
        items: [
            { id: 'tech-stack', label: 'Tech Stack' },
            { id: 'auth-system', label: 'Autentikasi & Otorisasi' },
            { id: 'roles-permissions', label: 'Roles & Permissions' },
            { id: 'database-schema', label: 'Skema Database' },
            { id: 'registration-modes', label: 'Mode Registrasi' },
            { id: 'field-metadata', label: 'Metadata Field' },
            { id: 'services', label: 'Service Layer' },
        ],
    },
]

function scrollToSection(id: string) {
    activeSection.value = id
    mobileNavOpen.value = false
    const el = document.getElementById(id)
    if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' })
}

function onScroll() {
    showScrollTop.value = window.scrollY > 600
    const sections = document.querySelectorAll<HTMLElement>('[data-doc-section]')
    let current = 'introduction'
    for (const sec of sections) {
        if (sec.getBoundingClientRect().top <= 120) current = sec.id
    }
    activeSection.value = current
}

function scrollToTop() {
    window.scrollTo({ top: 0, behavior: 'smooth' })
}

onMounted(() => {
    window.addEventListener('scroll', onScroll, { passive: true })
    if (window.location.hash) {
        const id = window.location.hash.slice(1)
        nextTick(() => scrollToSection(id))
    }
})
onUnmounted(() => window.removeEventListener('scroll', onScroll))
</script>

<template>
    <LandingLayout>
        <SeoHead title="Dokumentasi" :description="docsDescription" :canonical-path="routes.landing.docs" :json-ld="docsJsonLd" />
        <!-- Hero -->
        <section class="relative overflow-hidden border-b border-border/30 bg-muted/20 pt-32 pb-16 md:pt-40 md:pb-20">
            <div class="pointer-events-none absolute inset-0 app-noise opacity-30" aria-hidden="true" />
            <div class="relative mx-auto max-w-7xl px-6 lg:px-10">
                <div class="mx-auto max-w-3xl text-center">
                    <span class="inline-flex items-center gap-2 rounded-full border border-primary/15 bg-primary/[0.06] px-4 py-1.5 text-xs font-semibold tracking-wide text-primary">
                        <BookOpen class="size-3.5" />
                        Dokumentasi
                    </span>
                    <h1 class="mt-6 font-display text-3xl font-bold tracking-tight text-foreground sm:text-4xl lg:text-5xl text-balance">
                        Panduan lengkap
                        <span class="bg-gradient-to-r from-primary to-primary/70 bg-clip-text text-transparent">DForm</span>
                    </h1>
                    <p class="mt-4 mx-auto max-w-xl text-base leading-relaxed text-muted-foreground md:text-lg">
                        Dokumentasi untuk pengguna, penyelenggara acara, dan developer.
                        Semua yang perlu Anda ketahui ada di sini.
                    </p>
                </div>
            </div>
        </section>

        <!-- Body: Sidebar + Content -->
        <div class="mx-auto max-w-7xl px-6 lg:px-10">
            <div class="relative flex gap-10 py-12 lg:py-16">

                <!-- Mobile nav toggle -->
                <button
                    class="fixed bottom-6 right-6 z-50 flex size-12 items-center justify-center rounded-full bg-primary text-primary-foreground shadow-sm lg:hidden"
                    @click="mobileNavOpen = !mobileNavOpen"
                >
                    <Menu v-if="!mobileNavOpen" class="size-5" />
                    <X v-else class="size-5" />
                </button>

                <!-- Sidebar -->
                <aside
                    :class="[
                        'fixed inset-y-0 left-0 z-40 w-72 overflow-y-auto border-r border-border/30 bg-background px-6 pt-24 pb-10 transition-transform duration-300 lg:sticky lg:top-24 lg:z-auto lg:h-[calc(100vh-6rem)] lg:w-56 lg:shrink-0 lg:translate-x-0 lg:border-0 lg:bg-transparent lg:px-0 lg:pt-0',
                        mobileNavOpen ? 'translate-x-0' : '-translate-x-full',
                    ]"
                >
                    <nav class="flex flex-col gap-6">
                        <div v-for="group in navSections" :key="group.group">
                            <p class="mb-2 text-[10px] font-bold uppercase tracking-[0.18em] text-muted-foreground">{{ group.group }}</p>
                            <ul class="flex flex-col gap-0.5">
                                <li v-for="item in group.items" :key="item.id">
                                    <button
                                        :class="[
                                            'flex w-full items-center gap-2 rounded-lg px-3 py-1.5 text-left text-[13px] font-medium transition-colors',
                                            activeSection === item.id
                                                ? 'bg-primary/10 text-primary'
                                                : 'text-muted-foreground hover:bg-muted/50 hover:text-foreground',
                                        ]"
                                        @click="scrollToSection(item.id)"
                                    >
                                        {{ item.label }}
                                    </button>
                                </li>
                            </ul>
                        </div>
                    </nav>
                </aside>

                <!-- Overlay for mobile -->
                <div
                    v-if="mobileNavOpen"
                    class="fixed inset-0 z-30 bg-black/40 lg:hidden"
                    @click="mobileNavOpen = false"
                />

                <!-- Content -->
                <main class="min-w-0 flex-1">
                    <div class="prose-doc mx-auto max-w-3xl">

                        <!-- ============================================== -->
                        <!-- INTRODUCTION -->
                        <!-- ============================================== -->
                        <section id="introduction" data-doc-section class="scroll-mt-24">
                            <h2 class="flex items-center gap-3 text-2xl font-bold tracking-tight text-foreground">
                                <BookOpen class="size-6 text-primary" />
                                Pengenalan
                            </h2>
                            <p class="mt-4 text-sm leading-relaxed text-muted-foreground">
                                <strong class="text-foreground">DForm</strong> adalah platform web untuk membuat formulir pendaftaran acara,
                                mengumpulkan data peserta, dan mengelola semuanya dari satu dasbor. Dibangun untuk
                                <strong class="text-foreground">DOSCOM (Dian Nuswantoro Computer Community)</strong> dan organisasi
                                sejenis yang membutuhkan alur pendaftaran yang simpel, terstruktur, dan profesional.
                            </p>
                            <div class="mt-6 grid gap-3 sm:grid-cols-3">
                                <div class="rounded-xl border border-border/40 bg-muted/20 p-4">
                                    <FormInput class="mb-2 size-5 text-primary" />
                                    <h4 class="text-sm font-semibold text-foreground">Form Builder Visual</h4>
                                    <p class="mt-1 text-xs text-muted-foreground">Drag & drop, tanpa coding.</p>
                                </div>
                                <div class="rounded-xl border border-border/40 bg-muted/20 p-4">
                                    <BarChart3 class="mb-2 size-5 text-primary" />
                                    <h4 class="text-sm font-semibold text-foreground">Real-time Dashboard</h4>
                                    <p class="mt-1 text-xs text-muted-foreground">Pantau data secara langsung.</p>
                                </div>
                                <div class="rounded-xl border border-border/40 bg-muted/20 p-4">
                                    <ShieldCheck class="mb-2 size-5 text-primary" />
                                    <h4 class="text-sm font-semibold text-foreground">Aman & Terverifikasi</h4>
                                    <p class="mt-1 text-xs text-muted-foreground">Role-based access control.</p>
                                </div>
                            </div>
                        </section>

                        <hr class="my-12 border-border/30" />

                        <!-- ============================================== -->
                        <!-- QUICK START -->
                        <!-- ============================================== -->
                        <section id="quick-start" data-doc-section class="scroll-mt-24">
                            <h2 class="flex items-center gap-3 text-2xl font-bold tracking-tight text-foreground">
                                <Rocket class="size-6 text-primary" />
                                Quick Start
                            </h2>
                            <p class="mt-4 text-sm leading-relaxed text-muted-foreground">
                                Mulai dari nol hingga siap menerima pendaftar dalam 5 menit.
                            </p>
                            <ol class="mt-6 flex flex-col gap-4">
                                <li class="flex gap-4">
                                    <span class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-primary text-xs font-bold text-primary-foreground">1</span>
                                    <div>
                                        <h4 class="text-sm font-semibold text-foreground">Buat akun</h4>
                                        <p class="mt-1 text-xs text-muted-foreground">Daftar di <code>/auth/register</code> dengan email atau melalui Google/GitHub OAuth.</p>
                                    </div>
                                </li>
                                <li class="flex gap-4">
                                    <span class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-primary text-xs font-bold text-primary-foreground">2</span>
                                    <div>
                                        <h4 class="text-sm font-semibold text-foreground">Buat acara</h4>
                                        <p class="mt-1 text-xs text-muted-foreground">Di dasbor penyelenggara, klik "Buat Acara". Isi judul, tanggal, lokasi, kuota, dan banner. Publikasikan acara.</p>
                                    </div>
                                </li>
                                <li class="flex gap-4">
                                    <span class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-primary text-xs font-bold text-primary-foreground">3</span>
                                    <div>
                                        <h4 class="text-sm font-semibold text-foreground">Rancang formulir</h4>
                                        <p class="mt-1 text-xs text-muted-foreground">Masuk ke Form Builder. Tambahkan field (teks, dropdown, checkbox, dsb.) dengan drag & drop. Atur validasi lalu simpan.</p>
                                    </div>
                                </li>
                                <li class="flex gap-4">
                                    <span class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-primary text-xs font-bold text-primary-foreground">4</span>
                                    <div>
                                        <h4 class="text-sm font-semibold text-foreground">Bagikan & pantau</h4>
                                        <p class="mt-1 text-xs text-muted-foreground">Bagikan link formulir. Lihat respons masuk secara real-time di dasbor. Ekspor data kapan saja.</p>
                                    </div>
                                </li>
                            </ol>
                        </section>

                        <hr class="my-12 border-border/30" />

                        <!-- ============================================== -->
                        <!-- AUTH -->
                        <!-- ============================================== -->
                        <section id="auth" data-doc-section class="scroll-mt-24">
                            <h2 class="flex items-center gap-3 text-2xl font-bold tracking-tight text-foreground">
                                <UserPlus class="size-6 text-primary" />
                                Registrasi & Login
                            </h2>
                            <div class="mt-6 space-y-5">
                                <div>
                                    <h4 class="text-sm font-semibold text-foreground">Registrasi Manual</h4>
                                    <p class="mt-1 text-xs leading-relaxed text-muted-foreground">
                                        Kunjungi <code>/auth/register</code> dan isi nama, email, serta password. Setelah berhasil, Anda akan otomatis login dan diarahkan ke dasbor pengguna. Akun baru secara otomatis mendapat role <code>member</code>.
                                    </p>
                                </div>
                                <div>
                                    <h4 class="text-sm font-semibold text-foreground">OAuth (Google & GitHub)</h4>
                                    <p class="mt-1 text-xs leading-relaxed text-muted-foreground">
                                        DForm mendukung login melalui Google dan GitHub. Jika akun belum ada, DForm akan membuat akun baru secara otomatis menggunakan data dari provider. Akun OAuth tidak memiliki password lokal — Anda hanya bisa login melalui provider tersebut.
                                    </p>
                                </div>
                                <div>
                                    <h4 class="text-sm font-semibold text-foreground">Reset Password</h4>
                                    <p class="mt-1 text-xs leading-relaxed text-muted-foreground">
                                        Kunjungi <code>/auth/forgot-password</code>, masukkan email Anda. Link reset akan dikirim ke inbox. Buka link tersebut untuk mengatur password baru. Fitur ini hanya tersedia untuk akun yang memiliki password lokal (bukan OAuth).
                                    </p>
                                </div>
                            </div>
                        </section>

                        <hr class="my-12 border-border/30" />

                        <!-- ============================================== -->
                        <!-- BROWSE EVENTS -->
                        <!-- ============================================== -->
                        <section id="browse-events" data-doc-section class="scroll-mt-24">
                            <h2 class="flex items-center gap-3 text-2xl font-bold tracking-tight text-foreground">
                                <Search class="size-6 text-primary" />
                                Menjelajahi Acara
                            </h2>
                            <p class="mt-4 text-sm leading-relaxed text-muted-foreground">
                                Halaman publik <code>/events</code> menampilkan semua acara yang sudah dipublikasikan dan membuka pendaftaran.
                                Anda bisa melihat informasi acara, status pendaftaran (Dibuka, Penuh, Ditutup), lokasi, dan kuota peserta.
                            </p>
                            <p class="mt-3 text-sm leading-relaxed text-muted-foreground">
                                Klik acara untuk melihat detail lengkap termasuk deskripsi, tanggal, dan tombol untuk mendaftar.
                                Setelah login, Anda juga bisa menjelajahi acara dari dasbor pengguna melalui menu <strong class="text-foreground">Browse Events</strong>.
                            </p>
                        </section>

                        <hr class="my-12 border-border/30" />

                        <!-- ============================================== -->
                        <!-- FILL FORM -->
                        <!-- ============================================== -->
                        <section id="fill-form" data-doc-section class="scroll-mt-24">
                            <h2 class="flex items-center gap-3 text-2xl font-bold tracking-tight text-foreground">
                                <PenTool class="size-6 text-primary" />
                                Mengisi Formulir
                            </h2>
                            <p class="mt-4 text-sm leading-relaxed text-muted-foreground">
                                Untuk mendaftar ke acara, Anda harus login terlebih dahulu. Setelah itu:
                            </p>
                            <ol class="mt-4 space-y-2 text-sm text-muted-foreground">
                                <li class="flex gap-2"><ChevronRight class="mt-0.5 size-4 shrink-0 text-primary" /> Buka halaman acara dan klik <strong class="text-foreground">Daftar</strong>.</li>
                                <li class="flex gap-2"><ChevronRight class="mt-0.5 size-4 shrink-0 text-primary" /> Jika acara memiliki lebih dari satu formulir, pilih formulir yang sesuai.</li>
                                <li class="flex gap-2"><ChevronRight class="mt-0.5 size-4 shrink-0 text-primary" /> Isi semua field yang wajib (ditandai required). Field akan divalidasi otomatis.</li>
                                <li class="flex gap-2"><ChevronRight class="mt-0.5 size-4 shrink-0 text-primary" /> Klik <strong class="text-foreground">Kirim</strong>. Konfirmasi akan muncul sebelum data benar-benar terkirim.</li>
                            </ol>
                            <div class="mt-5 rounded-xl border border-amber-500/20 bg-amber-500/5 p-4 text-xs leading-relaxed text-muted-foreground">
                                <strong class="text-foreground">Catatan:</strong> Setiap pengguna hanya bisa mengisi formulir satu kali per acara.
                                Jika kuota sudah penuh atau periode pendaftaran sudah ditutup, formulir tidak bisa diakses.
                            </div>
                        </section>

                        <hr class="my-12 border-border/30" />

                        <!-- ============================================== -->
                        <!-- TEAM REGISTRATION -->
                        <!-- ============================================== -->
                        <section id="team-registration" data-doc-section class="scroll-mt-24">
                            <h2 class="flex items-center gap-3 text-2xl font-bold tracking-tight text-foreground">
                                <Users class="size-6 text-primary" />
                                Pendaftaran Tim
                            </h2>
                            <p class="mt-4 text-sm leading-relaxed text-muted-foreground">
                                DForm mendukung tiga mode pendaftaran yang bisa diatur per formulir:
                            </p>
                            <div class="mt-5 space-y-4">
                                <div class="rounded-xl border border-border/40 bg-muted/20 p-4">
                                    <h4 class="text-sm font-semibold text-foreground">Single (Individual)</h4>
                                    <p class="mt-1 text-xs text-muted-foreground">
                                        Satu orang mengisi satu formulir. Mode default.
                                    </p>
                                </div>
                                <div class="rounded-xl border border-border/40 bg-muted/20 p-4">
                                    <h4 class="text-sm font-semibold text-foreground">Team</h4>
                                    <p class="mt-1 text-xs text-muted-foreground">
                                        Ketua tim mengisi formulir utama lalu memasukkan email anggota tim. Anggota akan menerima undangan
                                        untuk konfirmasi. Kuota acara dikurangi sesuai jumlah anggota tim.
                                    </p>
                                </div>
                                <div class="rounded-xl border border-border/40 bg-muted/20 p-4">
                                    <h4 class="text-sm font-semibold text-foreground">Bundle</h4>
                                    <p class="mt-1 text-xs text-muted-foreground">
                                        Mirip dengan team, tetapi ketua mengisi data untuk semua anggota sekaligus. Field yang ditandai
                                        <code>duplicatable</code> akan digandakan per anggota (contoh: nama, email, ukuran kaos masing-masing anggota).
                                    </p>
                                </div>
                            </div>
                        </section>

                        <hr class="my-12 border-border/30" />

                        <!-- ============================================== -->
                        <!-- MY REGISTRATION -->
                        <!-- ============================================== -->
                        <section id="my-registration" data-doc-section class="scroll-mt-24">
                            <h2 class="flex items-center gap-3 text-2xl font-bold tracking-tight text-foreground">
                                <CheckCircle2 class="size-6 text-primary" />
                                Status Pendaftaran
                            </h2>
                            <p class="mt-4 text-sm leading-relaxed text-muted-foreground">
                                Setelah mengisi formulir, Anda bisa melihat status pendaftaran dari dasbor pengguna.
                                Setiap pendaftaran memiliki status review:
                            </p>
                            <div class="mt-5 space-y-2">
                                <div class="flex items-center gap-3 rounded-lg border border-border/30 px-4 py-2.5 text-xs">
                                    <span class="rounded-full bg-amber-500/10 px-2.5 py-0.5 font-semibold text-amber-600">Pending</span>
                                    <span class="text-muted-foreground">Menunggu review dari penyelenggara.</span>
                                </div>
                                <div class="flex items-center gap-3 rounded-lg border border-border/30 px-4 py-2.5 text-xs">
                                    <span class="rounded-full bg-green-500/10 px-2.5 py-0.5 font-semibold text-green-600">Accepted</span>
                                    <span class="text-muted-foreground">Pendaftaran diterima. Kode registrasi dan QR code tersedia.</span>
                                </div>
                                <div class="flex items-center gap-3 rounded-lg border border-border/30 px-4 py-2.5 text-xs">
                                    <span class="rounded-full bg-red-500/10 px-2.5 py-0.5 font-semibold text-red-400">Rejected</span>
                                    <span class="text-muted-foreground">Pendaftaran ditolak oleh penyelenggara.</span>
                                </div>
                            </div>
                            <p class="mt-4 text-sm leading-relaxed text-muted-foreground">
                                Jika pendaftaran diterima, Anda mendapatkan <strong class="text-foreground">kode registrasi unik</strong>
                                dan <strong class="text-foreground">QR code</strong> yang bisa digunakan untuk absensi di hari acara.
                            </p>
                        </section>

                        <hr class="my-12 border-border/30" />

                        <!-- ============================================== -->
                        <!-- PROFILE -->
                        <!-- ============================================== -->
                        <section id="profile" data-doc-section class="scroll-mt-24">
                            <h2 class="flex items-center gap-3 text-2xl font-bold tracking-tight text-foreground">
                                <Settings class="size-6 text-primary" />
                                Profil Pengguna
                            </h2>
                            <p class="mt-4 text-sm leading-relaxed text-muted-foreground">
                                Di halaman profil (<code>/dashboard/profile</code>), Anda bisa:
                            </p>
                            <ul class="mt-3 space-y-1.5 text-sm text-muted-foreground">
                                <li class="flex gap-2"><ChevronRight class="mt-0.5 size-4 shrink-0 text-primary" /> Mengunggah atau menghapus foto profil (avatar)</li>
                                <li class="flex gap-2"><ChevronRight class="mt-0.5 size-4 shrink-0 text-primary" /> Mengubah nama dan email</li>
                                <li class="flex gap-2"><ChevronRight class="mt-0.5 size-4 shrink-0 text-primary" /> Mengubah password (hanya untuk akun non-OAuth)</li>
                            </ul>
                        </section>

                        <hr class="my-12 border-border/30" />

                        <!-- ============================================== -->
                        <!-- ORGANIZER DASHBOARD -->
                        <!-- ============================================== -->
                        <section id="organizer-dashboard" data-doc-section class="scroll-mt-24">
                            <h2 class="flex items-center gap-3 text-2xl font-bold tracking-tight text-foreground">
                                <BarChart3 class="size-6 text-primary" />
                                Dasbor Penyelenggara
                            </h2>
                            <p class="mt-4 text-sm leading-relaxed text-muted-foreground">
                                Dasbor penyelenggara (<code>/admin/dashboard</code>) tersedia untuk pengguna dengan role <code>admin</code> atau
                                <code>super-admin</code>. Halaman ini menampilkan:
                            </p>
                            <ul class="mt-3 space-y-1.5 text-sm text-muted-foreground">
                                <li class="flex gap-2"><ChevronRight class="mt-0.5 size-4 shrink-0 text-primary" /> <strong class="text-foreground">KPI Cards</strong> — Total acara, acara aktif, total pendaftar, tingkat penyelesaian</li>
                                <li class="flex gap-2"><ChevronRight class="mt-0.5 size-4 shrink-0 text-primary" /> <strong class="text-foreground">Acara Terbaru</strong> — Daftar acara yang baru dibuat</li>
                                <li class="flex gap-2"><ChevronRight class="mt-0.5 size-4 shrink-0 text-primary" /> <strong class="text-foreground">Grafik Analitik</strong> — Tren pendaftaran dan breakdown kategori</li>
                                <li class="flex gap-2"><ChevronRight class="mt-0.5 size-4 shrink-0 text-primary" /> <strong class="text-foreground">Kalender</strong> — Timeline acara yang akan datang</li>
                            </ul>
                        </section>

                        <hr class="my-12 border-border/30" />

                        <!-- ============================================== -->
                        <!-- EVENT MANAGEMENT -->
                        <!-- ============================================== -->
                        <section id="event-management" data-doc-section class="scroll-mt-24">
                            <h2 class="flex items-center gap-3 text-2xl font-bold tracking-tight text-foreground">
                                <CalendarPlus class="size-6 text-primary" />
                                Manajemen Acara
                            </h2>
                            <p class="mt-4 text-sm leading-relaxed text-muted-foreground">
                                Penyelenggara bisa membuat, mengedit, dan menghapus acara dari dasbor.
                            </p>
                            <div class="mt-5 space-y-4">
                                <div>
                                    <h4 class="text-sm font-semibold text-foreground">Membuat Acara</h4>
                                    <p class="mt-1 text-xs leading-relaxed text-muted-foreground">
                                        Navigasi ke <code>/admin/dashboard/events/create</code>. Field yang wajib diisi:
                                        judul, deskripsi, tanggal mulai dan selesai, lokasi, kuota peserta, dan banner.
                                        Slug URL otomatis digenerate dari judul. Anda bisa memilih untuk mempublikasikan acara langsung atau menyimpan sebagai draft.
                                    </p>
                                </div>
                                <div>
                                    <h4 class="text-sm font-semibold text-foreground">Mengedit Acara</h4>
                                    <p class="mt-1 text-xs leading-relaxed text-muted-foreground">
                                        Dari halaman detail acara, klik Edit. Semua field bisa diubah termasuk banner dan tanggal.
                                        Perubahan kuota tidak otomatis menolak pendaftar yang sudah diterima.
                                    </p>
                                </div>
                                <div>
                                    <h4 class="text-sm font-semibold text-foreground">Arsip & Restore</h4>
                                    <p class="mt-1 text-xs leading-relaxed text-muted-foreground">
                                        Acara yang dihapus menggunakan <em>soft delete</em> — masih bisa di-restore kapan saja melalui tombol
                                        Restore di halaman detail acara.
                                    </p>
                                </div>
                            </div>
                        </section>

                        <hr class="my-12 border-border/30" />

                        <!-- ============================================== -->
                        <!-- FORM BUILDER -->
                        <!-- ============================================== -->
                        <section id="form-builder" data-doc-section class="scroll-mt-24">
                            <h2 class="flex items-center gap-3 text-2xl font-bold tracking-tight text-foreground">
                                <Palette class="size-6 text-primary" />
                                Form Builder
                            </h2>
                            <p class="mt-4 text-sm leading-relaxed text-muted-foreground">
                                Form Builder adalah fitur utama DForm untuk merancang formulir secara visual. Builder terdiri dari tiga area:
                            </p>
                            <div class="mt-5 grid gap-3 sm:grid-cols-3">
                                <div class="rounded-xl border border-border/40 bg-muted/20 p-4">
                                    <h4 class="text-sm font-semibold text-foreground">Palette (Kiri)</h4>
                                    <p class="mt-1 text-xs text-muted-foreground">Daftar semua jenis field yang bisa ditambahkan. Drag field dari sini ke canvas.</p>
                                </div>
                                <div class="rounded-xl border border-border/40 bg-muted/20 p-4">
                                    <h4 class="text-sm font-semibold text-foreground">Canvas (Tengah)</h4>
                                    <p class="mt-1 text-xs text-muted-foreground">Area kerja utama. Drop field di sini, atur urutan dengan drag, dan lihat preview langsung.</p>
                                </div>
                                <div class="rounded-xl border border-border/40 bg-muted/20 p-4">
                                    <h4 class="text-sm font-semibold text-foreground">Inspector (Kanan)</h4>
                                    <p class="mt-1 text-xs text-muted-foreground">Panel properti untuk field yang sedang dipilih. Atur label, placeholder, validasi, dan opsi.</p>
                                </div>
                            </div>
                            <p class="mt-5 text-sm leading-relaxed text-muted-foreground">
                                Setelah selesai menambahkan field, klik <strong class="text-foreground">Simpan</strong> untuk menyimpan
                                formulir. Gunakan tombol <strong class="text-foreground">Preview</strong> untuk melihat tampilan
                                formulir seperti yang dilihat peserta.
                            </p>
                        </section>

                        <hr class="my-12 border-border/30" />

                        <!-- ============================================== -->
                        <!-- FIELD TYPES -->
                        <!-- ============================================== -->
                        <section id="field-types" data-doc-section class="scroll-mt-24">
                            <h2 class="flex items-center gap-3 text-2xl font-bold tracking-tight text-foreground">
                                <FormInput class="size-6 text-primary" />
                                Jenis Field
                            </h2>
                            <p class="mt-4 text-sm leading-relaxed text-muted-foreground">
                                Field dikelompokkan berdasarkan kategori:
                            </p>

                            <div class="mt-5 space-y-6">
                                <div>
                                    <h4 class="mb-3 text-xs font-bold uppercase tracking-[0.15em] text-primary">Teks</h4>
                                    <div class="overflow-hidden rounded-xl border border-border/40">
                                        <table class="w-full text-xs">
                                            <thead><tr class="border-b border-border/30 bg-muted/30"><th class="px-4 py-2 text-left font-semibold text-foreground">Field</th><th class="px-4 py-2 text-left font-semibold text-foreground">Keterangan</th></tr></thead>
                                            <tbody class="divide-y divide-border/20">
                                                <tr><td class="px-4 py-2 font-medium text-foreground">Short Text</td><td class="px-4 py-2 text-muted-foreground">Input teks satu baris. Cocok untuk nama, NIM, dsb.</td></tr>
                                                <tr><td class="px-4 py-2 font-medium text-foreground">Long Text</td><td class="px-4 py-2 text-muted-foreground">Textarea multi-baris. Untuk deskripsi, motivasi, dsb.</td></tr>
                                                <tr><td class="px-4 py-2 font-medium text-foreground">Email</td><td class="px-4 py-2 text-muted-foreground">Input dengan validasi format email otomatis.</td></tr>
                                                <tr><td class="px-4 py-2 font-medium text-foreground">Phone</td><td class="px-4 py-2 text-muted-foreground">Input untuk nomor telepon.</td></tr>
                                                <tr><td class="px-4 py-2 font-medium text-foreground">Number</td><td class="px-4 py-2 text-muted-foreground">Input angka dengan validasi numerik.</td></tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                                <div>
                                    <h4 class="mb-3 text-xs font-bold uppercase tracking-[0.15em] text-primary">Pilihan</h4>
                                    <div class="overflow-hidden rounded-xl border border-border/40">
                                        <table class="w-full text-xs">
                                            <thead><tr class="border-b border-border/30 bg-muted/30"><th class="px-4 py-2 text-left font-semibold text-foreground">Field</th><th class="px-4 py-2 text-left font-semibold text-foreground">Keterangan</th></tr></thead>
                                            <tbody class="divide-y divide-border/20">
                                                <tr><td class="px-4 py-2 font-medium text-foreground">Dropdown</td><td class="px-4 py-2 text-muted-foreground">Select menu. Hanya mendukung opsi teks.</td></tr>
                                                <tr><td class="px-4 py-2 font-medium text-foreground">Checkbox</td><td class="px-4 py-2 text-muted-foreground">Pilihan ganda (multiple select). Bisa teks atau gambar.</td></tr>
                                                <tr><td class="px-4 py-2 font-medium text-foreground">Radio</td><td class="px-4 py-2 text-muted-foreground">Pilihan tunggal. Bisa teks atau gambar.</td></tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                                <div>
                                    <h4 class="mb-3 text-xs font-bold uppercase tracking-[0.15em] text-primary">Media</h4>
                                    <div class="overflow-hidden rounded-xl border border-border/40">
                                        <table class="w-full text-xs">
                                            <thead><tr class="border-b border-border/30 bg-muted/30"><th class="px-4 py-2 text-left font-semibold text-foreground">Field</th><th class="px-4 py-2 text-left font-semibold text-foreground">Keterangan</th></tr></thead>
                                            <tbody class="divide-y divide-border/20">
                                                <tr><td class="px-4 py-2 font-medium text-foreground">Image Upload</td><td class="px-4 py-2 text-muted-foreground">Upload gambar (jpg, png, webp). Max size bisa diatur.</td></tr>
                                                <tr><td class="px-4 py-2 font-medium text-foreground">File Upload</td><td class="px-4 py-2 text-muted-foreground">Upload file umum. MIME type dan max size bisa dikonfigurasi.</td></tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                                <div>
                                    <h4 class="mb-3 text-xs font-bold uppercase tracking-[0.15em] text-primary">Tanggal & Waktu</h4>
                                    <div class="overflow-hidden rounded-xl border border-border/40">
                                        <table class="w-full text-xs">
                                            <thead><tr class="border-b border-border/30 bg-muted/30"><th class="px-4 py-2 text-left font-semibold text-foreground">Field</th><th class="px-4 py-2 text-left font-semibold text-foreground">Keterangan</th></tr></thead>
                                            <tbody class="divide-y divide-border/20">
                                                <tr><td class="px-4 py-2 font-medium text-foreground">Date</td><td class="px-4 py-2 text-muted-foreground">Date picker untuk memilih tanggal.</td></tr>
                                                <tr><td class="px-4 py-2 font-medium text-foreground">Time</td><td class="px-4 py-2 text-muted-foreground">Time picker untuk memilih waktu.</td></tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                                <div>
                                    <h4 class="mb-3 text-xs font-bold uppercase tracking-[0.15em] text-primary">Konten & Layout</h4>
                                    <div class="overflow-hidden rounded-xl border border-border/40">
                                        <table class="w-full text-xs">
                                            <thead><tr class="border-b border-border/30 bg-muted/30"><th class="px-4 py-2 text-left font-semibold text-foreground">Field</th><th class="px-4 py-2 text-left font-semibold text-foreground">Keterangan</th></tr></thead>
                                            <tbody class="divide-y divide-border/20">
                                                <tr><td class="px-4 py-2 font-medium text-foreground">Heading</td><td class="px-4 py-2 text-muted-foreground">Judul section dalam formulir (tidak mengumpulkan data).</td></tr>
                                                <tr><td class="px-4 py-2 font-medium text-foreground">Paragraph</td><td class="px-4 py-2 text-muted-foreground">Teks deskriptif/instruksi dalam formulir.</td></tr>
                                                <tr><td class="px-4 py-2 font-medium text-foreground">Divider</td><td class="px-4 py-2 text-muted-foreground">Garis pembatas antar section.</td></tr>
                                                <tr><td class="px-4 py-2 font-medium text-foreground">Rating</td><td class="px-4 py-2 text-muted-foreground">Star rating (jumlah bintang bisa dikonfigurasi).</td></tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </section>

                        <hr class="my-12 border-border/30" />

                        <!-- ============================================== -->
                        <!-- FORM SETTINGS -->
                        <!-- ============================================== -->
                        <section id="form-settings" data-doc-section class="scroll-mt-24">
                            <h2 class="flex items-center gap-3 text-2xl font-bold tracking-tight text-foreground">
                                <ToggleRight class="size-6 text-primary" />
                                Pengaturan Formulir
                            </h2>
                            <p class="mt-4 text-sm leading-relaxed text-muted-foreground">
                                Setiap formulir memiliki pengaturan berikut:
                            </p>
                            <div class="mt-5 space-y-3">
                                <div class="rounded-lg border border-border/30 px-4 py-3">
                                    <h4 class="text-xs font-semibold text-foreground">Judul & Deskripsi</h4>
                                    <p class="mt-0.5 text-xs text-muted-foreground">Nama formulir dan penjelasan singkat yang dilihat peserta.</p>
                                </div>
                                <div class="rounded-lg border border-border/30 px-4 py-3">
                                    <h4 class="text-xs font-semibold text-foreground">Banner</h4>
                                    <p class="mt-0.5 text-xs text-muted-foreground">Gambar header dan caption yang ditampilkan di bagian atas formulir.</p>
                                </div>
                                <div class="rounded-lg border border-border/30 px-4 py-3">
                                    <h4 class="text-xs font-semibold text-foreground">Batas Waktu (closed_at)</h4>
                                    <p class="mt-0.5 text-xs text-muted-foreground">Tanggal dan waktu formulir otomatis ditutup. Setelah waktu ini, peserta tidak bisa mengisi.</p>
                                </div>
                                <div class="rounded-lg border border-border/30 px-4 py-3">
                                    <h4 class="text-xs font-semibold text-foreground">Visibilitas (visible_for)</h4>
                                    <p class="mt-0.5 text-xs text-muted-foreground">Mengatur siapa yang bisa melihat formulir: semua orang, hanya member, atau role tertentu.</p>
                                </div>
                                <div class="rounded-lg border border-border/30 px-4 py-3">
                                    <h4 class="text-xs font-semibold text-foreground">Mode Registrasi</h4>
                                    <p class="mt-0.5 text-xs text-muted-foreground">Single, Team, atau Bundle. Disimpan di metadata formulir. Lihat bagian <a href="#registration-modes" class="font-medium text-primary underline underline-offset-2">Mode Registrasi</a>.</p>
                                </div>
                            </div>
                        </section>

                        <hr class="my-12 border-border/30" />

                        <!-- ============================================== -->
                        <!-- SUBMISSIONS -->
                        <!-- ============================================== -->
                        <section id="submissions" data-doc-section class="scroll-mt-24">
                            <h2 class="flex items-center gap-3 text-2xl font-bold tracking-tight text-foreground">
                                <Eye class="size-6 text-primary" />
                                Mengelola Submisi
                            </h2>
                            <p class="mt-4 text-sm leading-relaxed text-muted-foreground">
                                Halaman submisi menampilkan semua jawaban yang masuk untuk sebuah formulir. Penyelenggara bisa:
                            </p>
                            <ul class="mt-3 space-y-1.5 text-sm text-muted-foreground">
                                <li class="flex gap-2"><ChevronRight class="mt-0.5 size-4 shrink-0 text-primary" /> Melihat ringkasan jawaban setiap pendaftar dalam panel detail</li>
                                <li class="flex gap-2"><ChevronRight class="mt-0.5 size-4 shrink-0 text-primary" /> <strong class="text-foreground">Accept</strong> — Menerima pendaftaran. Secara otomatis men-generate kode registrasi unik dan mengirim email konfirmasi</li>
                                <li class="flex gap-2"><ChevronRight class="mt-0.5 size-4 shrink-0 text-primary" /> <strong class="text-foreground">Reject</strong> — Menolak pendaftaran. Email penolakan dikirim otomatis</li>
                            </ul>
                            <p class="mt-4 text-sm leading-relaxed text-muted-foreground">
                                KPI strip di bagian atas menampilkan jumlah total, pending, accepted, dan rejected.
                            </p>
                        </section>

                        <hr class="my-12 border-border/30" />

                        <!-- ============================================== -->
                        <!-- REGISTRANTS -->
                        <!-- ============================================== -->
                        <section id="registrants" data-doc-section class="scroll-mt-24">
                            <h2 class="flex items-center gap-3 text-2xl font-bold tracking-tight text-foreground">
                                <Layers class="size-6 text-primary" />
                                Daftar Peserta
                            </h2>
                            <p class="mt-4 text-sm leading-relaxed text-muted-foreground">
                                Halaman Registrants menampilkan peserta dari seluruh formulir acara (gabungan pengiriman), dengan kolom sumber form, pencarian nama/email/kode/judul form, dan filter status review serta filter per formulir.
                            </p>
                        </section>

                        <hr class="my-12 border-border/30" />

                        <!-- ============================================== -->
                        <!-- QR SCAN -->
                        <!-- ============================================== -->
                        <section id="qr-scan" data-doc-section class="scroll-mt-24">
                            <h2 class="flex items-center gap-3 text-2xl font-bold tracking-tight text-foreground">
                                <QrCode class="size-6 text-primary" />
                                QR & Absensi
                            </h2>
                            <p class="mt-4 text-sm leading-relaxed text-muted-foreground">
                                DForm menyediakan fitur absensi digital melalui QR scan.
                            </p>
                            <div class="mt-5 space-y-4">
                                <div>
                                    <h4 class="text-sm font-semibold text-foreground">Untuk Peserta</h4>
                                    <p class="mt-1 text-xs leading-relaxed text-muted-foreground">
                                        Setelah pendaftaran diterima, QR code tersedia di halaman detail pendaftaran. Tunjukkan QR code ini ke penyelenggara saat check-in.
                                    </p>
                                </div>
                                <div>
                                    <h4 class="text-sm font-semibold text-foreground">Untuk Penyelenggara</h4>
                                    <p class="mt-1 text-xs leading-relaxed text-muted-foreground">
                                        Buka halaman Scan (<code>/admin/dashboard/events/{event}/scan</code>). Arahkan kamera ke QR peserta, atau masukkan kode registrasi secara manual. Sistem akan memverifikasi dan mencatat kehadiran. Setiap peserta hanya bisa di-scan sekali per acara.
                                    </p>
                                </div>
                            </div>
                        </section>

                        <hr class="my-12 border-border/30" />

                        <!-- ============================================== -->
                        <!-- EXPORT -->
                        <!-- ============================================== -->
                        <section id="export" data-doc-section class="scroll-mt-24">
                            <h2 class="flex items-center gap-3 text-2xl font-bold tracking-tight text-foreground">
                                <Download class="size-6 text-primary" />
                                Ekspor Data
                            </h2>
                            <p class="mt-4 text-sm leading-relaxed text-muted-foreground">
                                Dua jenis ekspor CSV tersedia dari halaman detail acara:
                            </p>
                            <div class="mt-5 grid gap-3 sm:grid-cols-2">
                                <div class="rounded-xl border border-border/40 bg-muted/20 p-4">
                                    <h4 class="text-sm font-semibold text-foreground">Registrations CSV</h4>
                                    <p class="mt-1 text-xs text-muted-foreground">
                                        Semua data pendaftaran termasuk ringkasan jawaban yang sudah di-humanize (label → value).
                                        URL: <code>.../exports/registrations.csv</code>
                                    </p>
                                </div>
                                <div class="rounded-xl border border-border/40 bg-muted/20 p-4">
                                    <h4 class="text-sm font-semibold text-foreground">Attendance CSV</h4>
                                    <p class="mt-1 text-xs text-muted-foreground">
                                        Log absensi (scan) termasuk waktu scan dan siapa yang men-scan.
                                        URL: <code>.../exports/attendance.csv</code>
                                    </p>
                                </div>
                            </div>
                        </section>

                        <hr class="my-12 border-border/30" />

                        <!-- ============================================== -->
                        <!-- REPORTING -->
                        <!-- ============================================== -->
                        <section id="reporting" data-doc-section class="scroll-mt-24">
                            <h2 class="flex items-center gap-3 text-2xl font-bold tracking-tight text-foreground">
                                <FileText class="size-6 text-primary" />
                                Laporan Acara
                            </h2>
                            <p class="mt-4 text-sm leading-relaxed text-muted-foreground">
                                Halaman Laporan (<code>/admin/dashboard/events/{event}/laporan</code>) menyediakan ringkasan komprehensif per acara:
                            </p>
                            <ul class="mt-3 space-y-1.5 text-sm text-muted-foreground">
                                <li class="flex gap-2"><ChevronRight class="mt-0.5 size-4 shrink-0 text-primary" /> KPI ringkasan (total pendaftar, accepted, attendance rate)</li>
                                <li class="flex gap-2"><ChevronRight class="mt-0.5 size-4 shrink-0 text-primary" /> Panel log absensi dengan pagination</li>
                                <li class="flex gap-2"><ChevronRight class="mt-0.5 size-4 shrink-0 text-primary" /> Link langsung ke CSV export untuk arsip</li>
                            </ul>
                        </section>

                        <hr class="my-12 border-border/30" />

                        <!-- ============================================== -->
                        <!-- TECH STACK -->
                        <!-- ============================================== -->
                        <section id="tech-stack" data-doc-section class="scroll-mt-24">
                            <h2 class="flex items-center gap-3 text-2xl font-bold tracking-tight text-foreground">
                                <Code2 class="size-6 text-primary" />
                                Tech Stack
                            </h2>
                            <div class="mt-5 overflow-hidden rounded-xl border border-border/40">
                                <table class="w-full text-xs">
                                    <thead><tr class="border-b border-border/30 bg-muted/30"><th class="px-4 py-2.5 text-left font-semibold text-foreground">Layer</th><th class="px-4 py-2.5 text-left font-semibold text-foreground">Teknologi</th></tr></thead>
                                    <tbody class="divide-y divide-border/20">
                                        <tr><td class="px-4 py-2 font-medium text-foreground">Backend</td><td class="px-4 py-2 text-muted-foreground">Laravel 11 (PHP 8.2+)</td></tr>
                                        <tr><td class="px-4 py-2 font-medium text-foreground">Frontend</td><td class="px-4 py-2 text-muted-foreground">Vue 3 (Composition API) + TypeScript</td></tr>
                                        <tr><td class="px-4 py-2 font-medium text-foreground">Bridge</td><td class="px-4 py-2 text-muted-foreground">Inertia.js (server-driven SPA)</td></tr>
                                        <tr><td class="px-4 py-2 font-medium text-foreground">Styling</td><td class="px-4 py-2 text-muted-foreground">Tailwind CSS v4 + shadcn-vue (reka-ui)</td></tr>
                                        <tr><td class="px-4 py-2 font-medium text-foreground">Auth</td><td class="px-4 py-2 text-muted-foreground">Laravel Session + Socialite (Google, GitHub)</td></tr>
                                        <tr><td class="px-4 py-2 font-medium text-foreground">Roles</td><td class="px-4 py-2 text-muted-foreground">Spatie laravel-permission</td></tr>
                                        <tr><td class="px-4 py-2 font-medium text-foreground">Database</td><td class="px-4 py-2 text-muted-foreground">MySQL / MariaDB (UUID primary keys)</td></tr>
                                        <tr><td class="px-4 py-2 font-medium text-foreground">Animations</td><td class="px-4 py-2 text-muted-foreground">vue3-lottie (LottieFiles)</td></tr>
                                        <tr><td class="px-4 py-2 font-medium text-foreground">Build</td><td class="px-4 py-2 text-muted-foreground">Vite</td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </section>

                        <hr class="my-12 border-border/30" />

                        <!-- ============================================== -->
                        <!-- AUTH SYSTEM -->
                        <!-- ============================================== -->
                        <section id="auth-system" data-doc-section class="scroll-mt-24">
                            <h2 class="flex items-center gap-3 text-2xl font-bold tracking-tight text-foreground">
                                <Lock class="size-6 text-primary" />
                                Autentikasi & Otorisasi
                            </h2>
                            <p class="mt-4 text-sm leading-relaxed text-muted-foreground">
                                DForm menggunakan <strong class="text-foreground">Laravel session guard</strong> untuk autentikasi.
                                OAuth via <strong class="text-foreground">Laravel Socialite</strong> mendukung Google dan GitHub.
                                Akun OAuth disimpan tanpa password lokal (<code>password</code> nullable).
                            </p>
                            <p class="mt-3 text-sm leading-relaxed text-muted-foreground">
                                Middleware utama:
                            </p>
                            <div class="mt-3 overflow-hidden rounded-xl border border-border/40">
                                <table class="w-full text-xs">
                                    <thead><tr class="border-b border-border/30 bg-muted/30"><th class="px-4 py-2.5 text-left font-semibold text-foreground">Middleware</th><th class="px-4 py-2.5 text-left font-semibold text-foreground">Fungsi</th></tr></thead>
                                    <tbody class="divide-y divide-border/20">
                                        <tr><td class="px-4 py-2 font-mono font-medium text-foreground">auth</td><td class="px-4 py-2 text-muted-foreground">Memastikan user sudah login (redirect ke login jika belum)</td></tr>
                                        <tr><td class="px-4 py-2 font-mono font-medium text-foreground">organizer</td><td class="px-4 py-2 text-muted-foreground">Memerlukan permission <code>events.list</code>; redirect ke user portal jika tidak</td></tr>
                                        <tr><td class="px-4 py-2 font-mono font-medium text-foreground">member_portal</td><td class="px-4 py-2 text-muted-foreground">Memblokir pure organizer dari user portal (harus punya role member)</td></tr>
                                        <tr><td class="px-4 py-2 font-mono font-medium text-foreground">guest</td><td class="px-4 py-2 text-muted-foreground">Hanya untuk user yang belum login (halaman auth)</td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </section>

                        <hr class="my-12 border-border/30" />

                        <!-- ============================================== -->
                        <!-- ROLES & PERMISSIONS -->
                        <!-- ============================================== -->
                        <section id="roles-permissions" data-doc-section class="scroll-mt-24">
                            <h2 class="flex items-center gap-3 text-2xl font-bold tracking-tight text-foreground">
                                <ShieldCheck class="size-6 text-primary" />
                                Roles & Permissions
                            </h2>
                            <p class="mt-4 text-sm leading-relaxed text-muted-foreground">
                                Menggunakan <strong class="text-foreground">Spatie laravel-permission</strong>. Tiga role utama:
                            </p>
                            <div class="mt-5 space-y-4">
                                <div class="rounded-xl border border-border/40 bg-muted/20 p-4">
                                    <h4 class="text-sm font-semibold text-foreground">super-admin</h4>
                                    <p class="mt-1 text-xs text-muted-foreground">
                                        Akses penuh ke semua fitur tanpa batasan. Semua permission otomatis diberikan.
                                    </p>
                                </div>
                                <div class="rounded-xl border border-border/40 bg-muted/20 p-4">
                                    <h4 class="text-sm font-semibold text-foreground">admin</h4>
                                    <p class="mt-1 text-xs text-muted-foreground">
                                        Penyelenggara acara. Bisa membuat, mengedit, dan menghapus acara & formulir. Bisa mereview submisi, scan absensi, dan mengekspor data. Tidak bisa mengelola user, role, atau permission secara langsung.
                                    </p>
                                </div>
                                <div class="rounded-xl border border-border/40 bg-muted/20 p-4">
                                    <h4 class="text-sm font-semibold text-foreground">member</h4>
                                    <p class="mt-1 text-xs text-muted-foreground">
                                        Peserta/pengguna biasa. Bisa menjelajahi acara, mengisi formulir, melihat status pendaftaran, dan mengelola profil. Diberikan otomatis saat registrasi.
                                    </p>
                                </div>
                            </div>
                        </section>

                        <hr class="my-12 border-border/30" />

                        <!-- ============================================== -->
                        <!-- DATABASE SCHEMA -->
                        <!-- ============================================== -->
                        <section id="database-schema" data-doc-section class="scroll-mt-24">
                            <h2 class="flex items-center gap-3 text-2xl font-bold tracking-tight text-foreground">
                                <Database class="size-6 text-primary" />
                                Skema Database
                            </h2>
                            <p class="mt-4 text-sm leading-relaxed text-muted-foreground">
                                Semua tabel menggunakan <strong class="text-foreground">UUID</strong> sebagai primary key. Relasi utama:
                            </p>
                            <div class="mt-5 space-y-4">
                                <div class="rounded-xl border border-border/40 p-4">
                                    <h4 class="font-mono text-sm font-semibold text-foreground">users</h4>
                                    <p class="mt-1 text-xs text-muted-foreground">id, name, email, password (nullable), avatar, google_id, github_id, soft deletes</p>
                                </div>
                                <div class="rounded-xl border border-border/40 p-4">
                                    <h4 class="font-mono text-sm font-semibold text-foreground">events</h4>
                                    <p class="mt-1 text-xs text-muted-foreground">id, title, slug (unique), description, start_date, end_date, registration_start/end, location, quota, registered_count, banner, price, session, status, category, soft deletes</p>
                                </div>
                                <div class="rounded-xl border border-border/40 p-4">
                                    <h4 class="font-mono text-sm font-semibold text-foreground">forms</h4>
                                    <p class="mt-1 text-xs text-muted-foreground">id, title, description, visible_for (JSON), closed_at, event_id (FK), banner_url, banner_caption, metadata (JSON), soft deletes</p>
                                </div>
                                <div class="rounded-xl border border-border/40 p-4">
                                    <h4 class="font-mono text-sm font-semibold text-foreground">form_fields</h4>
                                    <p class="mt-1 text-xs text-muted-foreground">id, input_type, label, description, name, metadata (JSON), form_id (FK), order, is_append, soft deletes</p>
                                </div>
                                <div class="rounded-xl border border-border/40 p-4">
                                    <h4 class="font-mono text-sm font-semibold text-foreground">form_answers</h4>
                                    <p class="mt-1 text-xs text-muted-foreground">id, answers (JSON), user_id (FK), form_id (FK), review_status, registration_code (unique), team/bundle columns (leader_form_answer_id, registration_role, invitation_token, group_token, member_confirmation_status, invited_email), soft deletes</p>
                                </div>
                                <div class="rounded-xl border border-border/40 p-4">
                                    <h4 class="font-mono text-sm font-semibold text-foreground">event_attendances</h4>
                                    <p class="mt-1 text-xs text-muted-foreground">id, event_id (FK), form_answer_id (FK), scanned_by_user_id (FK), scanned_at. Unique: (event_id, form_answer_id)</p>
                                </div>
                                <div class="rounded-xl border border-border/40 p-4">
                                    <h4 class="font-mono text-sm font-semibold text-foreground">email_logs</h4>
                                    <p class="mt-1 text-xs text-muted-foreground">id, form_answer_id, event_id, user_id, recipient_email, status, notification_type, error_message, sent_at</p>
                                </div>
                            </div>
                        </section>

                        <hr class="my-12 border-border/30" />

                        <!-- ============================================== -->
                        <!-- REGISTRATION MODES -->
                        <!-- ============================================== -->
                        <section id="registration-modes" data-doc-section class="scroll-mt-24">
                            <h2 class="flex items-center gap-3 text-2xl font-bold tracking-tight text-foreground">
                                <Users class="size-6 text-primary" />
                                Mode Registrasi (Teknis)
                            </h2>
                            <p class="mt-4 text-sm leading-relaxed text-muted-foreground">
                                Mode registrasi disimpan di <code>forms.metadata.registration_mode</code>:
                            </p>
                            <div class="mt-5 overflow-hidden rounded-xl border border-border/40">
                                <table class="w-full text-xs">
                                    <thead><tr class="border-b border-border/30 bg-muted/30"><th class="px-4 py-2.5 text-left font-semibold text-foreground">Mode</th><th class="px-4 py-2.5 text-left font-semibold text-foreground">Behavior</th></tr></thead>
                                    <tbody class="divide-y divide-border/20">
                                        <tr>
                                            <td class="px-4 py-2 font-mono font-medium text-foreground">single</td>
                                            <td class="px-4 py-2 text-muted-foreground">1 user = 1 FormAnswer. Kuota -= 1.</td>
                                        </tr>
                                        <tr>
                                            <td class="px-4 py-2 font-mono font-medium text-foreground">team</td>
                                            <td class="px-4 py-2 text-muted-foreground">Leader mengisi form + menginput email anggota. Setiap anggota mendapat FormAnswer dengan <code>registration_role = member</code>, <code>member_confirmation_status = pending</code>, dan <code>invitation_token</code>. Kuota -= team_size. Anggota mengonfirmasi via link undangan.</td>
                                        </tr>
                                        <tr>
                                            <td class="px-4 py-2 font-mono font-medium text-foreground">bundle</td>
                                            <td class="px-4 py-2 text-muted-foreground">Leader mengisi data semua anggota sekaligus. Field dengan <code>is_append: true</code> dan <code>metadata.duplicatable: true</code> digandakan per slot (<code>bundle__{name}__{index}</code>). Kuota -= max_team_size. Semua <code>group_token</code> sama.</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </section>

                        <hr class="my-12 border-border/30" />

                        <!-- ============================================== -->
                        <!-- FIELD METADATA -->
                        <!-- ============================================== -->
                        <section id="field-metadata" data-doc-section class="scroll-mt-24">
                            <h2 class="flex items-center gap-3 text-2xl font-bold tracking-tight text-foreground">
                                <Settings class="size-6 text-primary" />
                                Metadata Field (Teknis)
                            </h2>
                            <p class="mt-4 text-sm leading-relaxed text-muted-foreground">
                                Setiap <code>form_fields.metadata</code> (JSON) berisi konfigurasi field:
                            </p>
                            <div class="mt-5 overflow-hidden rounded-xl border border-border/40">
                                <table class="w-full text-xs">
                                    <thead><tr class="border-b border-border/30 bg-muted/30"><th class="px-4 py-2.5 text-left font-semibold text-foreground">Key</th><th class="px-4 py-2.5 text-left font-semibold text-foreground">Keterangan</th></tr></thead>
                                    <tbody class="divide-y divide-border/20">
                                        <tr><td class="px-4 py-2 font-mono font-medium text-foreground">builderType</td><td class="px-4 py-2 text-muted-foreground">Tipe field di UI builder (short_text, dropdown, radio, dll)</td></tr>
                                        <tr><td class="px-4 py-2 font-mono font-medium text-foreground">type</td><td class="px-4 py-2 text-muted-foreground">Subtipe input HTML (text, email, tel, number)</td></tr>
                                        <tr><td class="px-4 py-2 font-mono font-medium text-foreground">placeholder</td><td class="px-4 py-2 text-muted-foreground">Teks placeholder pada input</td></tr>
                                        <tr><td class="px-4 py-2 font-mono font-medium text-foreground">content</td><td class="px-4 py-2 text-muted-foreground">Konten HTML untuk heading/paragraph</td></tr>
                                        <tr><td class="px-4 py-2 font-mono font-medium text-foreground">optionChoices</td><td class="px-4 py-2 text-muted-foreground">Array opsi untuk dropdown/checkbox/radio: <code>[{ id, type, label, imageUrl }]</code></td></tr>
                                        <tr><td class="px-4 py-2 font-mono font-medium text-foreground">rules</td><td class="px-4 py-2 text-muted-foreground">Objek aturan validasi: required, min, max, mimes, max_size, email, url, in (CSV), is_checkbox, is_multiple</td></tr>
                                        <tr><td class="px-4 py-2 font-mono font-medium text-foreground">maxStars</td><td class="px-4 py-2 text-muted-foreground">Jumlah bintang untuk field rating</td></tr>
                                        <tr><td class="px-4 py-2 font-mono font-medium text-foreground">duplicatable</td><td class="px-4 py-2 text-muted-foreground">Boolean. Jika true, field digandakan per slot pada mode bundle</td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </section>

                        <hr class="my-12 border-border/30" />

                        <!-- ============================================== -->
                        <!-- SERVICES -->
                        <!-- ============================================== -->
                        <section id="services" data-doc-section class="scroll-mt-24">
                            <h2 class="flex items-center gap-3 text-2xl font-bold tracking-tight text-foreground">
                                <Layers class="size-6 text-primary" />
                                Service Layer
                            </h2>
                            <p class="mt-4 text-sm leading-relaxed text-muted-foreground">
                                Business logic dikelompokkan dalam service classes di <code>app/Services/</code>:
                            </p>
                            <div class="mt-5 space-y-3">
                                <div class="rounded-lg border border-border/30 px-4 py-3">
                                    <h4 class="font-mono text-xs font-semibold text-foreground">Event/EventService</h4>
                                    <p class="mt-0.5 text-xs text-muted-foreground">Create, update, delete, restore events. Banner storage. Slug generation. Registration status.</p>
                                </div>
                                <div class="rounded-lg border border-border/30 px-4 py-3">
                                    <h4 class="font-mono text-xs font-semibold text-foreground">Form/FormAccessGuard</h4>
                                    <p class="mt-0.5 text-xs text-muted-foreground">Central access checks: visibility, closure time, registration window, quota, duplicate prevention.</p>
                                </div>
                                <div class="rounded-lg border border-border/30 px-4 py-3">
                                    <h4 class="font-mono text-xs font-semibold text-foreground">Form/RulesBuilder</h4>
                                    <p class="mt-0.5 text-xs text-muted-foreground">Converts field metadata into Laravel validation rules dynamically.</p>
                                </div>
                                <div class="rounded-lg border border-border/30 px-4 py-3">
                                    <h4 class="font-mono text-xs font-semibold text-foreground">Registration/TeamRegistrationSubmitter</h4>
                                    <p class="mt-0.5 text-xs text-muted-foreground">Team mode: creates leader + member FormAnswers, generates invitation tokens, dispatches email jobs.</p>
                                </div>
                                <div class="rounded-lg border border-border/30 px-4 py-3">
                                    <h4 class="font-mono text-xs font-semibold text-foreground">Registration/BundleRegistrationSubmitter</h4>
                                    <p class="mt-0.5 text-xs text-muted-foreground">Bundle mode: group token, per-member answers for duplicatable fields.</p>
                                </div>
                                <div class="rounded-lg border border-border/30 px-4 py-3">
                                    <h4 class="font-mono text-xs font-semibold text-foreground">Registration/RegistrationCodeIssuer</h4>
                                    <p class="mt-0.5 text-xs text-muted-foreground">Generates human-friendly unique registration codes on acceptance.</p>
                                </div>
                                <div class="rounded-lg border border-border/30 px-4 py-3">
                                    <h4 class="font-mono text-xs font-semibold text-foreground">Registration/RegistrationQrPngGenerator</h4>
                                    <p class="mt-0.5 text-xs text-muted-foreground">Generates QR code PNG from FormAnswer ID for attendance scanning.</p>
                                </div>
                                <div class="rounded-lg border border-border/30 px-4 py-3">
                                    <h4 class="font-mono text-xs font-semibold text-foreground">Attendance/AttendanceScanSubmissionResolver</h4>
                                    <p class="mt-0.5 text-xs text-muted-foreground">Resolves scan input (UUID, registration code, or QR payload) to a FormAnswer.</p>
                                </div>
                                <div class="rounded-lg border border-border/30 px-4 py-3">
                                    <h4 class="font-mono text-xs font-semibold text-foreground">Reporting/EventReportingQuery</h4>
                                    <p class="mt-0.5 text-xs text-muted-foreground">Trends, category breakdowns, global/event summaries, attendance pagination for reporting page.</p>
                                </div>
                            </div>
                        </section>

                        <div class="mt-16 rounded-2xl border border-primary/20 bg-primary/[0.04] p-6 text-center">
                            <h3 class="text-lg font-bold text-foreground">Ada pertanyaan?</h3>
                            <p class="mt-2 text-sm text-muted-foreground">
                                Hubungi tim DOSCOM atau buka issue di repository untuk bantuan lebih lanjut.
                            </p>
                        </div>
                    </div>
                </main>
            </div>
        </div>

        <!-- Scroll to top -->
        <button
            v-if="showScrollTop"
            class="fixed bottom-6 right-20 z-40 flex size-10 items-center justify-center rounded-full border border-border/50 bg-card text-muted-foreground shadow-sm transition-all hover:text-primary lg:right-6"
            @click="scrollToTop"
        >
            <ArrowUp class="size-4" />
        </button>
    </LandingLayout>
</template>

<style scoped>
@reference "../../css/app.css";

.prose-doc code {
    @apply rounded bg-muted px-1.5 py-0.5 text-[11px] font-medium text-foreground;
}
</style>
