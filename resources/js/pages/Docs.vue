<script setup lang="ts">
import LandingLayout from '@/layouts/LandingLayout.vue';
import { ref, onMounted, onUnmounted, nextTick, computed } from 'vue';
import {
    BookOpen,
    Rocket,
    FormInput,
    Users,
    ShieldCheck,
    BarChart3,
    QrCode,
    Download,
    Palette,
    CalendarPlus,
    PenTool,
    Eye,
    CheckCircle2,
    UserPlus,
    Search,
    ChevronRight,
    Menu,
    X,
    ArrowUp,
    ToggleRight,
    Layers,
    FileText,
    Database,
    Code2,
    Lock,
    Settings,
} from 'lucide-vue-next';
import SeoHead from '@/components/seo/SeoHead.vue';
import { usePage } from '@inertiajs/vue3';
import type { ISharedSeoProps } from '@/types/seo';
import { routes } from '@/lib/routes';
import { findElements, scrollToElementById } from '@/adapters/browser/domQuery';
import { currentHash, readScrollY, scrollToTop } from '@/adapters/browser/navigation';
import { onWindowEvent } from '@/adapters/browser/domEvents';

const page = usePage();
const seo = computed(() => (page.props as { seo: ISharedSeoProps }).seo);

const docsDescription = computed(
    () =>
        `Dokumentasi resmi ${seo.value.siteName}: registrasi, menjelajah acara, mengisi formulir, absensi QR, peran organizer dan tim, serta tips integrasi.`
);

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
}));

const activeSection = ref('introduction');
const mobileNavOpen = ref(false);
const showScrollTop = ref(false);

interface NavItem {
    id: string;
    label: string;
    children?: { id: string; label: string }[];
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
];

function scrollToSection(id: string) {
    activeSection.value = id;
    mobileNavOpen.value = false;
    scrollToElementById(id);
}

function onScroll() {
    showScrollTop.value = readScrollY() > 600;
    const sections = findElements('[data-doc-section]');
    let current = 'introduction';
    for (const sec of sections) {
        if (sec.getBoundingClientRect().top <= 120) current = sec.id;
    }
    activeSection.value = current;
}

let releaseScroll: (() => void) | null = null;

onMounted(() => {
    releaseScroll = onWindowEvent('scroll', onScroll, { passive: true });
    const hash = currentHash();
    if (hash) {
        const id = hash.slice(1);
        nextTick(() => scrollToSection(id));
    }
});
onUnmounted(() => {
    releaseScroll?.();
    releaseScroll = null;
});
</script>

<template>
    <LandingLayout>
        <SeoHead
            title="Dokumentasi"
            :description="docsDescription"
            :canonical-path="routes.landing.docs"
            :json-ld="docsJsonLd"
        />
        <!-- Hero -->
        <section class="border-border/30 bg-muted/20 relative overflow-hidden border-b pt-32 pb-16 md:pt-40 md:pb-20">
            <div class="app-noise pointer-events-none absolute inset-0 opacity-30" aria-hidden="true" />
            <div class="relative mx-auto max-w-7xl px-6 lg:px-10">
                <div class="mx-auto max-w-3xl text-center">
                    <span
                        class="border-primary/15 bg-primary/[0.06] text-primary inline-flex items-center gap-2 rounded-full border px-4 py-1.5 text-xs font-semibold tracking-wide"
                    >
                        <BookOpen class="size-3.5" />
                        Dokumentasi
                    </span>
                    <h1
                        class="font-display text-foreground mt-6 text-3xl font-bold tracking-tight text-balance sm:text-4xl lg:text-5xl"
                    >
                        Panduan lengkap
                        <span class="from-primary to-primary/70 bg-gradient-to-r bg-clip-text text-transparent"
                            >DForm</span
                        >
                    </h1>
                    <p class="text-muted-foreground mx-auto mt-4 max-w-xl text-base leading-relaxed md:text-lg">
                        Dokumentasi untuk pengguna, penyelenggara acara, dan developer. Semua yang perlu Anda ketahui
                        ada di sini.
                    </p>
                </div>
            </div>
        </section>

        <!-- Body: Sidebar + Content -->
        <div class="mx-auto max-w-7xl px-6 lg:px-10">
            <div class="relative flex gap-10 py-12 lg:py-16">
                <!-- Mobile nav toggle -->
                <button
                    class="bg-primary text-primary-foreground fixed right-6 bottom-6 z-50 flex size-12 items-center justify-center shadow-sm lg:hidden"
                    @click="mobileNavOpen = !mobileNavOpen"
                >
                    <Menu v-if="!mobileNavOpen" class="size-5" />
                    <X v-else class="size-5" />
                </button>

                <!-- Sidebar -->
                <aside
                    :class="[
                        'border-border/30 bg-background fixed inset-y-0 left-0 z-40 w-72 overflow-y-auto border-r px-6 pt-24 pb-10 transition-transform duration-300 lg:sticky lg:top-24 lg:z-auto lg:h-[calc(100vh-6rem)] lg:w-56 lg:shrink-0 lg:translate-x-0 lg:border-0 lg:bg-transparent lg:px-0 lg:pt-0',
                        mobileNavOpen ? 'translate-x-0' : '-translate-x-full',
                    ]"
                >
                    <nav class="flex flex-col gap-6">
                        <div v-for="group in navSections" :key="group.group">
                            <p class="text-muted-foreground mb-2 text-[10px] font-bold tracking-[0.18em] uppercase">
                                {{ group.group }}
                            </p>
                            <ul class="flex flex-col gap-0.5">
                                <li v-for="item in group.items" :key="item.id">
                                    <button
                                        :class="[
                                            'flex w-full items-center gap-2 px-3 py-1.5 text-left text-[13px] font-medium transition-colors',
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
                <div class="min-w-0 flex-1">
                    <div class="prose-doc mx-auto max-w-3xl">
                        <!-- ============================================== -->
                        <!-- INTRODUCTION -->
                        <!-- ============================================== -->
                        <section id="introduction" data-doc-section class="scroll-mt-24">
                            <h2 class="text-foreground flex items-center gap-3 text-2xl font-bold tracking-tight">
                                <BookOpen class="text-primary size-6" />
                                Pengenalan
                            </h2>
                            <p class="text-muted-foreground mt-4 text-sm leading-relaxed">
                                <strong class="text-foreground">DForm</strong> adalah platform web untuk membuat
                                formulir pendaftaran acara, mengumpulkan data peserta, dan mengelola semuanya dari satu
                                dasbor. Dibangun untuk
                                <strong class="text-foreground">DOSCOM (Dian Nuswantoro Computer Community)</strong> dan
                                organisasi sejenis yang membutuhkan alur pendaftaran yang simpel, terstruktur, dan
                                profesional.
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

                        <!-- ============================================== -->
                        <!-- QUICK START -->
                        <!-- ============================================== -->
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
                                            Daftar di <code>/auth/register</code> dengan email atau melalui
                                            Google/GitHub OAuth.
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
                                            Di dasbor penyelenggara, klik "Buat Acara". Isi judul, tanggal, lokasi,
                                            kuota, dan banner. Publikasikan acara.
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
                                            Masuk ke Form Builder. Tambahkan field (teks, dropdown, checkbox, dsb.)
                                            dengan drag & drop. Atur validasi lalu simpan.
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
                                            Bagikan link formulir. Lihat respons masuk secara real-time di dasbor.
                                            Ekspor data kapan saja.
                                        </p>
                                    </div>
                                </li>
                            </ol>
                        </section>

                        <hr class="border-border/30 my-12" />

                        <!-- ============================================== -->
                        <!-- AUTH -->
                        <!-- ============================================== -->
                        <section id="auth" data-doc-section class="scroll-mt-24">
                            <h2 class="text-foreground flex items-center gap-3 text-2xl font-bold tracking-tight">
                                <UserPlus class="text-primary size-6" />
                                Registrasi & Login
                            </h2>
                            <div class="mt-6 space-y-5">
                                <div>
                                    <h3 class="text-foreground text-sm font-semibold">Registrasi Manual</h3>
                                    <p class="text-muted-foreground mt-1 text-xs leading-relaxed">
                                        Kunjungi <code>/auth/register</code> dan isi nama, email, serta password.
                                        Setelah berhasil, Anda akan otomatis login dan diarahkan ke dasbor pengguna.
                                        Akun baru secara otomatis mendapat role <code>member</code>.
                                    </p>
                                </div>
                                <div>
                                    <h3 class="text-foreground text-sm font-semibold">OAuth (Google & GitHub)</h3>
                                    <p class="text-muted-foreground mt-1 text-xs leading-relaxed">
                                        DForm mendukung login melalui Google dan GitHub. Jika akun belum ada, DForm akan
                                        membuat akun baru secara otomatis menggunakan data dari provider. Akun OAuth
                                        tidak memiliki password lokal — Anda hanya bisa login melalui provider tersebut.
                                    </p>
                                </div>
                                <div>
                                    <h3 class="text-foreground text-sm font-semibold">Reset Password</h3>
                                    <p class="text-muted-foreground mt-1 text-xs leading-relaxed">
                                        Kunjungi <code>/auth/forgot-password</code>, masukkan email Anda. Link reset
                                        akan dikirim ke inbox. Buka link tersebut untuk mengatur password baru. Fitur
                                        ini hanya tersedia untuk akun yang memiliki password lokal (bukan OAuth).
                                    </p>
                                </div>
                            </div>
                        </section>

                        <hr class="border-border/30 my-12" />

                        <!-- ============================================== -->
                        <!-- BROWSE EVENTS -->
                        <!-- ============================================== -->
                        <section id="browse-events" data-doc-section class="scroll-mt-24">
                            <h2 class="text-foreground flex items-center gap-3 text-2xl font-bold tracking-tight">
                                <Search class="text-primary size-6" />
                                Menjelajahi Acara
                            </h2>
                            <p class="text-muted-foreground mt-4 text-sm leading-relaxed">
                                Halaman publik <code>/events</code> menampilkan semua acara yang sudah dipublikasikan
                                dan membuka pendaftaran. Anda bisa melihat informasi acara, status pendaftaran (Dibuka,
                                Penuh, Ditutup), lokasi, dan kuota peserta.
                            </p>
                            <p class="text-muted-foreground mt-3 text-sm leading-relaxed">
                                Klik acara untuk melihat detail lengkap termasuk deskripsi, tanggal, dan tombol untuk
                                mendaftar. Setelah login, Anda juga bisa menjelajahi acara dari dasbor pengguna melalui
                                menu <strong class="text-foreground">Browse Events</strong>.
                            </p>
                        </section>

                        <hr class="border-border/30 my-12" />

                        <!-- ============================================== -->
                        <!-- FILL FORM -->
                        <!-- ============================================== -->
                        <section id="fill-form" data-doc-section class="scroll-mt-24">
                            <h2 class="text-foreground flex items-center gap-3 text-2xl font-bold tracking-tight">
                                <PenTool class="text-primary size-6" />
                                Mengisi Formulir
                            </h2>
                            <p class="text-muted-foreground mt-4 text-sm leading-relaxed">
                                Untuk mendaftar ke acara, Anda harus login terlebih dahulu. Setelah itu:
                            </p>
                            <ol class="text-muted-foreground mt-4 space-y-2 text-sm">
                                <li class="flex gap-2">
                                    <ChevronRight class="text-primary mt-0.5 size-4 shrink-0" /> Buka halaman acara dan
                                    klik <strong class="text-foreground">Daftar</strong>.
                                </li>
                                <li class="flex gap-2">
                                    <ChevronRight class="text-primary mt-0.5 size-4 shrink-0" /> Jika acara memiliki
                                    lebih dari satu formulir, pilih formulir yang sesuai.
                                </li>
                                <li class="flex gap-2">
                                    <ChevronRight class="text-primary mt-0.5 size-4 shrink-0" /> Isi semua field yang
                                    wajib (ditandai required). Field akan divalidasi otomatis.
                                </li>
                                <li class="flex gap-2">
                                    <ChevronRight class="text-primary mt-0.5 size-4 shrink-0" /> Klik
                                    <strong class="text-foreground">Kirim</strong>. Konfirmasi akan muncul sebelum data
                                    benar-benar terkirim.
                                </li>
                            </ol>
                            <div
                                class="text-muted-foreground mt-5 rounded-xl border border-amber-500/20 bg-amber-500/5 p-4 text-xs leading-relaxed"
                            >
                                <strong class="text-foreground">Catatan:</strong> Setiap pengguna hanya bisa mengisi
                                formulir satu kali per acara. Jika kuota sudah penuh atau periode pendaftaran sudah
                                ditutup, formulir tidak bisa diakses.
                            </div>
                        </section>

                        <hr class="border-border/30 my-12" />

                        <!-- ============================================== -->
                        <!-- TEAM REGISTRATION -->
                        <!-- ============================================== -->
                        <section id="team-registration" data-doc-section class="scroll-mt-24">
                            <h2 class="text-foreground flex items-center gap-3 text-2xl font-bold tracking-tight">
                                <Users class="text-primary size-6" />
                                Pendaftaran Tim
                            </h2>
                            <p class="text-muted-foreground mt-4 text-sm leading-relaxed">
                                DForm mendukung tiga mode pendaftaran yang bisa diatur per formulir:
                            </p>
                            <div class="mt-5 space-y-4">
                                <div class="border-border/40 bg-muted/20 rounded-xl border p-4">
                                    <h3 class="text-foreground text-sm font-semibold">Single (Individual)</h3>
                                    <p class="text-muted-foreground mt-1 text-xs">
                                        Satu orang mengisi satu formulir. Mode default.
                                    </p>
                                </div>
                                <div class="border-border/40 bg-muted/20 rounded-xl border p-4">
                                    <h3 class="text-foreground text-sm font-semibold">Team</h3>
                                    <p class="text-muted-foreground mt-1 text-xs">
                                        Ketua tim mengisi formulir utama lalu memasukkan email anggota tim. Anggota akan
                                        menerima undangan untuk konfirmasi. Kuota acara dikurangi sesuai jumlah anggota
                                        tim.
                                    </p>
                                </div>
                                <div class="border-border/40 bg-muted/20 rounded-xl border p-4">
                                    <h3 class="text-foreground text-sm font-semibold">Bundle</h3>
                                    <p class="text-muted-foreground mt-1 text-xs">
                                        Mirip dengan team, tetapi ketua mengisi data untuk semua anggota sekaligus.
                                        Field yang ditandai
                                        <code>duplicatable</code> akan digandakan per anggota (contoh: nama, email,
                                        ukuran kaos masing-masing anggota).
                                    </p>
                                </div>
                            </div>
                        </section>

                        <hr class="border-border/30 my-12" />

                        <!-- ============================================== -->
                        <!-- MY REGISTRATION -->
                        <!-- ============================================== -->
                        <section id="my-registration" data-doc-section class="scroll-mt-24">
                            <h2 class="text-foreground flex items-center gap-3 text-2xl font-bold tracking-tight">
                                <CheckCircle2 class="text-primary size-6" />
                                Status Pendaftaran
                            </h2>
                            <p class="text-muted-foreground mt-4 text-sm leading-relaxed">
                                Setelah mengisi formulir, Anda bisa melihat status pendaftaran dari dasbor pengguna.
                                Setiap pendaftaran memiliki status review:
                            </p>
                            <div class="mt-5 space-y-2">
                                <div
                                    class="border-border/30 flex items-center gap-3 rounded-lg border px-4 py-2.5 text-xs"
                                >
                                    <span
                                        class="rounded-full bg-amber-500/10 px-2.5 py-0.5 font-semibold text-amber-600"
                                        >Pending</span
                                    >
                                    <span class="text-muted-foreground">Menunggu review dari penyelenggara.</span>
                                </div>
                                <div
                                    class="border-border/30 flex items-center gap-3 rounded-lg border px-4 py-2.5 text-xs"
                                >
                                    <span
                                        class="rounded-full bg-green-500/10 px-2.5 py-0.5 font-semibold text-green-600"
                                        >Accepted</span
                                    >
                                    <span class="text-muted-foreground"
                                        >Pendaftaran diterima. Kode registrasi dan QR code tersedia.</span
                                    >
                                </div>
                                <div
                                    class="border-border/30 flex items-center gap-3 rounded-lg border px-4 py-2.5 text-xs"
                                >
                                    <span class="rounded-full bg-red-500/10 px-2.5 py-0.5 font-semibold text-red-400"
                                        >Rejected</span
                                    >
                                    <span class="text-muted-foreground">Pendaftaran ditolak oleh penyelenggara.</span>
                                </div>
                            </div>
                            <p class="text-muted-foreground mt-4 text-sm leading-relaxed">
                                Jika pendaftaran diterima, Anda mendapatkan
                                <strong class="text-foreground">kode registrasi unik</strong> dan
                                <strong class="text-foreground">QR code</strong> yang bisa digunakan untuk absensi di
                                hari acara.
                            </p>
                        </section>

                        <hr class="border-border/30 my-12" />

                        <!-- ============================================== -->
                        <!-- PROFILE -->
                        <!-- ============================================== -->
                        <section id="profile" data-doc-section class="scroll-mt-24">
                            <h2 class="text-foreground flex items-center gap-3 text-2xl font-bold tracking-tight">
                                <Settings class="text-primary size-6" />
                                Profil Pengguna
                            </h2>
                            <p class="text-muted-foreground mt-4 text-sm leading-relaxed">
                                Di halaman profil (<code>/profile</code>), Anda bisa:
                            </p>
                            <ul class="text-muted-foreground mt-3 space-y-1.5 text-sm">
                                <li class="flex gap-2">
                                    <ChevronRight class="text-primary mt-0.5 size-4 shrink-0" /> Mengunggah atau
                                    menghapus foto profil (avatar)
                                </li>
                                <li class="flex gap-2">
                                    <ChevronRight class="text-primary mt-0.5 size-4 shrink-0" /> Mengubah nama dan email
                                </li>
                                <li class="flex gap-2">
                                    <ChevronRight class="text-primary mt-0.5 size-4 shrink-0" /> Mengubah password
                                    (hanya untuk akun non-OAuth)
                                </li>
                            </ul>
                        </section>

                        <hr class="border-border/30 my-12" />

                        <!-- ============================================== -->
                        <!-- ORGANIZER DASHBOARD -->
                        <!-- ============================================== -->
                        <section id="organizer-dashboard" data-doc-section class="scroll-mt-24">
                            <h2 class="text-foreground flex items-center gap-3 text-2xl font-bold tracking-tight">
                                <BarChart3 class="text-primary size-6" />
                                Dasbor Penyelenggara
                            </h2>
                            <p class="text-muted-foreground mt-4 text-sm leading-relaxed">
                                Dasbor penyelenggara (<code>/admin/dashboard</code>) tersedia untuk pengguna dengan role
                                <code>admin</code> atau <code>super-admin</code>. Halaman ini menampilkan:
                            </p>
                            <ul class="text-muted-foreground mt-3 space-y-1.5 text-sm">
                                <li class="flex gap-2">
                                    <ChevronRight class="text-primary mt-0.5 size-4 shrink-0" />
                                    <strong class="text-foreground">KPI Cards</strong> — Total acara, acara aktif, total
                                    pendaftar, tingkat penyelesaian
                                </li>
                                <li class="flex gap-2">
                                    <ChevronRight class="text-primary mt-0.5 size-4 shrink-0" />
                                    <strong class="text-foreground">Acara Terbaru</strong> — Daftar acara yang baru
                                    dibuat
                                </li>
                                <li class="flex gap-2">
                                    <ChevronRight class="text-primary mt-0.5 size-4 shrink-0" />
                                    <strong class="text-foreground">Grafik Analitik</strong> — Tren pendaftaran dan
                                    breakdown kategori
                                </li>
                                <li class="flex gap-2">
                                    <ChevronRight class="text-primary mt-0.5 size-4 shrink-0" />
                                    <strong class="text-foreground">Kalender</strong> — Timeline acara yang akan datang
                                </li>
                            </ul>
                        </section>

                        <hr class="border-border/30 my-12" />

                        <!-- ============================================== -->
                        <!-- EVENT MANAGEMENT -->
                        <!-- ============================================== -->
                        <section id="event-management" data-doc-section class="scroll-mt-24">
                            <h2 class="text-foreground flex items-center gap-3 text-2xl font-bold tracking-tight">
                                <CalendarPlus class="text-primary size-6" />
                                Manajemen Acara
                            </h2>
                            <p class="text-muted-foreground mt-4 text-sm leading-relaxed">
                                Penyelenggara bisa membuat, mengedit, dan menghapus acara dari dasbor.
                            </p>
                            <div class="mt-5 space-y-4">
                                <div>
                                    <h3 class="text-foreground text-sm font-semibold">Membuat Acara</h3>
                                    <p class="text-muted-foreground mt-1 text-xs leading-relaxed">
                                        Navigasi ke <code>/admin/events/create</code>. Field yang wajib diisi: judul,
                                        deskripsi, tanggal mulai dan selesai, lokasi, kuota peserta, dan banner. Slug
                                        URL otomatis digenerate dari judul. Anda bisa memilih untuk mempublikasikan
                                        acara langsung atau menyimpan sebagai draft.
                                    </p>
                                </div>
                                <div>
                                    <h3 class="text-foreground text-sm font-semibold">Mengedit Acara</h3>
                                    <p class="text-muted-foreground mt-1 text-xs leading-relaxed">
                                        Dari halaman detail acara, klik Edit. Semua field bisa diubah termasuk banner
                                        dan tanggal. Perubahan kuota tidak otomatis menolak pendaftar yang sudah
                                        diterima.
                                    </p>
                                </div>
                                <div>
                                    <h3 class="text-foreground text-sm font-semibold">Arsip & Restore</h3>
                                    <p class="text-muted-foreground mt-1 text-xs leading-relaxed">
                                        Acara yang dihapus menggunakan <em>soft delete</em> — masih bisa di-restore
                                        kapan saja melalui tombol Restore di halaman detail acara.
                                    </p>
                                </div>
                            </div>
                        </section>

                        <hr class="border-border/30 my-12" />

                        <!-- ============================================== -->
                        <!-- FORM BUILDER -->
                        <!-- ============================================== -->
                        <section id="form-builder" data-doc-section class="scroll-mt-24">
                            <h2 class="text-foreground flex items-center gap-3 text-2xl font-bold tracking-tight">
                                <Palette class="text-primary size-6" />
                                Form Builder
                            </h2>
                            <p class="text-muted-foreground mt-4 text-sm leading-relaxed">
                                Form Builder adalah fitur utama DForm untuk merancang formulir secara visual. Builder
                                terdiri dari tiga area:
                            </p>
                            <div class="mt-5 grid gap-3 sm:grid-cols-3">
                                <div class="border-border/40 bg-muted/20 rounded-xl border p-4">
                                    <h3 class="text-foreground text-sm font-semibold">Palette (Kiri)</h3>
                                    <p class="text-muted-foreground mt-1 text-xs">
                                        Daftar semua jenis field yang bisa ditambahkan. Drag field dari sini ke canvas.
                                    </p>
                                </div>
                                <div class="border-border/40 bg-muted/20 rounded-xl border p-4">
                                    <h3 class="text-foreground text-sm font-semibold">Canvas (Tengah)</h3>
                                    <p class="text-muted-foreground mt-1 text-xs">
                                        Area kerja utama. Drop field di sini, atur urutan dengan drag, dan lihat preview
                                        langsung.
                                    </p>
                                </div>
                                <div class="border-border/40 bg-muted/20 rounded-xl border p-4">
                                    <h3 class="text-foreground text-sm font-semibold">Inspector (Kanan)</h3>
                                    <p class="text-muted-foreground mt-1 text-xs">
                                        Panel properti untuk field yang sedang dipilih. Atur label, placeholder,
                                        validasi, dan opsi.
                                    </p>
                                </div>
                            </div>
                            <p class="text-muted-foreground mt-5 text-sm leading-relaxed">
                                Setelah selesai menambahkan field, klik
                                <strong class="text-foreground">Simpan</strong> untuk menyimpan formulir. Gunakan tombol
                                <strong class="text-foreground">Preview</strong> untuk melihat tampilan formulir seperti
                                yang dilihat peserta.
                            </p>
                        </section>

                        <hr class="border-border/30 my-12" />

                        <!-- ============================================== -->
                        <!-- FIELD TYPES -->
                        <!-- ============================================== -->
                        <section id="field-types" data-doc-section class="scroll-mt-24">
                            <h2 class="text-foreground flex items-center gap-3 text-2xl font-bold tracking-tight">
                                <FormInput class="text-primary size-6" />
                                Jenis Field
                            </h2>
                            <p class="text-muted-foreground mt-4 text-sm leading-relaxed">
                                Field dikelompokkan berdasarkan kategori:
                            </p>

                            <div class="mt-5 space-y-6">
                                <div>
                                    <h3 class="text-primary mb-3 text-xs font-bold tracking-[0.15em] uppercase">
                                        Teks
                                    </h3>
                                    <div class="border-border/40 overflow-hidden rounded-xl border">
                                        <table class="w-full text-xs">
                                            <thead>
                                                <tr class="border-border/30 bg-muted/30 border-b">
                                                    <th class="text-foreground px-4 py-2 text-left font-semibold">
                                                        Field
                                                    </th>
                                                    <th class="text-foreground px-4 py-2 text-left font-semibold">
                                                        Keterangan
                                                    </th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-border/20 divide-y">
                                                <tr>
                                                    <td class="text-foreground px-4 py-2 font-medium">Short Text</td>
                                                    <td class="text-muted-foreground px-4 py-2">
                                                        Input teks satu baris. Cocok untuk nama, NIM, dsb.
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td class="text-foreground px-4 py-2 font-medium">Long Text</td>
                                                    <td class="text-muted-foreground px-4 py-2">
                                                        Textarea multi-baris. Untuk deskripsi, motivasi, dsb.
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td class="text-foreground px-4 py-2 font-medium">Email</td>
                                                    <td class="text-muted-foreground px-4 py-2">
                                                        Input dengan validasi format email otomatis.
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td class="text-foreground px-4 py-2 font-medium">Phone</td>
                                                    <td class="text-muted-foreground px-4 py-2">
                                                        Input untuk nomor telepon.
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td class="text-foreground px-4 py-2 font-medium">Number</td>
                                                    <td class="text-muted-foreground px-4 py-2">
                                                        Input angka dengan validasi numerik.
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                                <div>
                                    <h3 class="text-primary mb-3 text-xs font-bold tracking-[0.15em] uppercase">
                                        Pilihan
                                    </h3>
                                    <div class="border-border/40 overflow-hidden rounded-xl border">
                                        <table class="w-full text-xs">
                                            <thead>
                                                <tr class="border-border/30 bg-muted/30 border-b">
                                                    <th class="text-foreground px-4 py-2 text-left font-semibold">
                                                        Field
                                                    </th>
                                                    <th class="text-foreground px-4 py-2 text-left font-semibold">
                                                        Keterangan
                                                    </th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-border/20 divide-y">
                                                <tr>
                                                    <td class="text-foreground px-4 py-2 font-medium">Dropdown</td>
                                                    <td class="text-muted-foreground px-4 py-2">
                                                        Select menu. Hanya mendukung opsi teks.
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td class="text-foreground px-4 py-2 font-medium">Checkbox</td>
                                                    <td class="text-muted-foreground px-4 py-2">
                                                        Pilihan ganda (multiple select). Bisa teks atau gambar.
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td class="text-foreground px-4 py-2 font-medium">Radio</td>
                                                    <td class="text-muted-foreground px-4 py-2">
                                                        Pilihan tunggal. Bisa teks atau gambar.
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                                <div>
                                    <h3 class="text-primary mb-3 text-xs font-bold tracking-[0.15em] uppercase">
                                        Media
                                    </h3>
                                    <div class="border-border/40 overflow-hidden rounded-xl border">
                                        <table class="w-full text-xs">
                                            <thead>
                                                <tr class="border-border/30 bg-muted/30 border-b">
                                                    <th class="text-foreground px-4 py-2 text-left font-semibold">
                                                        Field
                                                    </th>
                                                    <th class="text-foreground px-4 py-2 text-left font-semibold">
                                                        Keterangan
                                                    </th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-border/20 divide-y">
                                                <tr>
                                                    <td class="text-foreground px-4 py-2 font-medium">Image Upload</td>
                                                    <td class="text-muted-foreground px-4 py-2">
                                                        Upload gambar (jpg, png, webp). Max size bisa diatur.
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td class="text-foreground px-4 py-2 font-medium">File Upload</td>
                                                    <td class="text-muted-foreground px-4 py-2">
                                                        Upload file umum. MIME type dan max size bisa dikonfigurasi.
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                                <div>
                                    <h3 class="text-primary mb-3 text-xs font-bold tracking-[0.15em] uppercase">
                                        Tanggal & Waktu
                                    </h3>
                                    <div class="border-border/40 overflow-hidden rounded-xl border">
                                        <table class="w-full text-xs">
                                            <thead>
                                                <tr class="border-border/30 bg-muted/30 border-b">
                                                    <th class="text-foreground px-4 py-2 text-left font-semibold">
                                                        Field
                                                    </th>
                                                    <th class="text-foreground px-4 py-2 text-left font-semibold">
                                                        Keterangan
                                                    </th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-border/20 divide-y">
                                                <tr>
                                                    <td class="text-foreground px-4 py-2 font-medium">Date</td>
                                                    <td class="text-muted-foreground px-4 py-2">
                                                        Date picker untuk memilih tanggal.
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td class="text-foreground px-4 py-2 font-medium">Time</td>
                                                    <td class="text-muted-foreground px-4 py-2">
                                                        Time picker untuk memilih waktu.
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                                <div>
                                    <h3 class="text-primary mb-3 text-xs font-bold tracking-[0.15em] uppercase">
                                        Konten & Layout
                                    </h3>
                                    <div class="border-border/40 overflow-hidden rounded-xl border">
                                        <table class="w-full text-xs">
                                            <thead>
                                                <tr class="border-border/30 bg-muted/30 border-b">
                                                    <th class="text-foreground px-4 py-2 text-left font-semibold">
                                                        Field
                                                    </th>
                                                    <th class="text-foreground px-4 py-2 text-left font-semibold">
                                                        Keterangan
                                                    </th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-border/20 divide-y">
                                                <tr>
                                                    <td class="text-foreground px-4 py-2 font-medium">Heading</td>
                                                    <td class="text-muted-foreground px-4 py-2">
                                                        Judul section dalam formulir (tidak mengumpulkan data).
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td class="text-foreground px-4 py-2 font-medium">Paragraph</td>
                                                    <td class="text-muted-foreground px-4 py-2">
                                                        Teks deskriptif/instruksi dalam formulir.
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td class="text-foreground px-4 py-2 font-medium">Divider</td>
                                                    <td class="text-muted-foreground px-4 py-2">
                                                        Garis pembatas antar section.
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td class="text-foreground px-4 py-2 font-medium">Rating</td>
                                                    <td class="text-muted-foreground px-4 py-2">
                                                        Star rating (jumlah bintang bisa dikonfigurasi).
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </section>

                        <hr class="border-border/30 my-12" />

                        <!-- ============================================== -->
                        <!-- FORM SETTINGS -->
                        <!-- ============================================== -->
                        <section id="form-settings" data-doc-section class="scroll-mt-24">
                            <h2 class="text-foreground flex items-center gap-3 text-2xl font-bold tracking-tight">
                                <ToggleRight class="text-primary size-6" />
                                Pengaturan Formulir
                            </h2>
                            <p class="text-muted-foreground mt-4 text-sm leading-relaxed">
                                Setiap formulir memiliki pengaturan berikut:
                            </p>
                            <div class="mt-5 space-y-3">
                                <div class="border-border/30 rounded-lg border px-4 py-3">
                                    <h3 class="text-foreground text-xs font-semibold">Judul & Deskripsi</h3>
                                    <p class="text-muted-foreground mt-0.5 text-xs">
                                        Nama formulir dan penjelasan singkat yang dilihat peserta.
                                    </p>
                                </div>
                                <div class="border-border/30 rounded-lg border px-4 py-3">
                                    <h3 class="text-foreground text-xs font-semibold">Banner</h3>
                                    <p class="text-muted-foreground mt-0.5 text-xs">
                                        Gambar header dan caption yang ditampilkan di bagian atas formulir.
                                    </p>
                                </div>
                                <div class="border-border/30 rounded-lg border px-4 py-3">
                                    <h3 class="text-foreground text-xs font-semibold">Batas Waktu (closed_at)</h3>
                                    <p class="text-muted-foreground mt-0.5 text-xs">
                                        Tanggal dan waktu formulir otomatis ditutup. Setelah waktu ini, peserta tidak
                                        bisa mengisi.
                                    </p>
                                </div>
                                <div class="border-border/30 rounded-lg border px-4 py-3">
                                    <h3 class="text-foreground text-xs font-semibold">Visibilitas (visible_for)</h3>
                                    <p class="text-muted-foreground mt-0.5 text-xs">
                                        Mengatur siapa yang bisa melihat formulir: semua orang, hanya member, atau role
                                        tertentu.
                                    </p>
                                </div>
                                <div class="border-border/30 rounded-lg border px-4 py-3">
                                    <h3 class="text-foreground text-xs font-semibold">Mode Registrasi</h3>
                                    <p class="text-muted-foreground mt-0.5 text-xs">
                                        Single, Team, atau Bundle. Disimpan di metadata formulir. Lihat bagian
                                        <a
                                            href="#registration-modes"
                                            class="text-primary font-medium underline underline-offset-2"
                                            >Mode Registrasi</a
                                        >.
                                    </p>
                                </div>
                            </div>
                        </section>

                        <hr class="border-border/30 my-12" />

                        <!-- ============================================== -->
                        <!-- SUBMISSIONS -->
                        <!-- ============================================== -->
                        <section id="submissions" data-doc-section class="scroll-mt-24">
                            <h2 class="text-foreground flex items-center gap-3 text-2xl font-bold tracking-tight">
                                <Eye class="text-primary size-6" />
                                Mengelola Submisi
                            </h2>
                            <p class="text-muted-foreground mt-4 text-sm leading-relaxed">
                                Halaman submisi menampilkan semua jawaban yang masuk untuk sebuah formulir.
                                Penyelenggara bisa:
                            </p>
                            <ul class="text-muted-foreground mt-3 space-y-1.5 text-sm">
                                <li class="flex gap-2">
                                    <ChevronRight class="text-primary mt-0.5 size-4 shrink-0" /> Melihat ringkasan
                                    jawaban setiap pendaftar dalam panel detail
                                </li>
                                <li class="flex gap-2">
                                    <ChevronRight class="text-primary mt-0.5 size-4 shrink-0" />
                                    <strong class="text-foreground">Accept</strong> — Menerima pendaftaran. Secara
                                    otomatis men-generate kode registrasi unik dan mengirim email konfirmasi
                                </li>
                                <li class="flex gap-2">
                                    <ChevronRight class="text-primary mt-0.5 size-4 shrink-0" />
                                    <strong class="text-foreground">Reject</strong> — Menolak pendaftaran. Email
                                    penolakan dikirim otomatis
                                </li>
                            </ul>
                            <p class="text-muted-foreground mt-4 text-sm leading-relaxed">
                                KPI strip di bagian atas menampilkan jumlah total, pending, accepted, dan rejected.
                            </p>
                        </section>

                        <hr class="border-border/30 my-12" />

                        <!-- ============================================== -->
                        <!-- REGISTRANTS -->
                        <!-- ============================================== -->
                        <section id="registrants" data-doc-section class="scroll-mt-24">
                            <h2 class="text-foreground flex items-center gap-3 text-2xl font-bold tracking-tight">
                                <Layers class="text-primary size-6" />
                                Daftar Peserta
                            </h2>
                            <p class="text-muted-foreground mt-4 text-sm leading-relaxed">
                                Halaman Registrants menampilkan peserta dari seluruh formulir acara (gabungan
                                pengiriman), dengan kolom sumber form, pencarian nama/email/kode/judul form, dan filter
                                status review serta filter per formulir.
                            </p>
                        </section>

                        <hr class="border-border/30 my-12" />

                        <!-- ============================================== -->
                        <!-- QR SCAN -->
                        <!-- ============================================== -->
                        <section id="qr-scan" data-doc-section class="scroll-mt-24">
                            <h2 class="text-foreground flex items-center gap-3 text-2xl font-bold tracking-tight">
                                <QrCode class="text-primary size-6" />
                                QR & Absensi
                            </h2>
                            <p class="text-muted-foreground mt-4 text-sm leading-relaxed">
                                DForm menyediakan fitur absensi digital melalui QR scan.
                            </p>
                            <div class="mt-5 space-y-4">
                                <div>
                                    <h3 class="text-foreground text-sm font-semibold">Untuk Peserta</h3>
                                    <p class="text-muted-foreground mt-1 text-xs leading-relaxed">
                                        Setelah pendaftaran diterima, QR code tersedia di halaman detail pendaftaran.
                                        Tunjukkan QR code ini ke penyelenggara saat check-in.
                                    </p>
                                </div>
                                <div>
                                    <h3 class="text-foreground text-sm font-semibold">Untuk Penyelenggara</h3>
                                    <p class="text-muted-foreground mt-1 text-xs leading-relaxed">
                                        Buka halaman Scan (<code>/admin/scan</code>). Arahkan kamera ke QR peserta, atau
                                        masukkan kode registrasi secara manual. Sistem akan memverifikasi dan mencatat
                                        kehadiran. Setiap peserta hanya bisa di-scan sekali per acara.
                                    </p>
                                </div>
                            </div>
                        </section>

                        <hr class="border-border/30 my-12" />

                        <!-- ============================================== -->
                        <!-- EXPORT -->
                        <!-- ============================================== -->
                        <section id="export" data-doc-section class="scroll-mt-24">
                            <h2 class="text-foreground flex items-center gap-3 text-2xl font-bold tracking-tight">
                                <Download class="text-primary size-6" />
                                Ekspor Data
                            </h2>
                            <p class="text-muted-foreground mt-4 text-sm leading-relaxed">
                                Dua jenis ekspor CSV tersedia dari halaman detail acara:
                            </p>
                            <div class="mt-5 grid gap-3 sm:grid-cols-2">
                                <div class="border-border/40 bg-muted/20 rounded-xl border p-4">
                                    <h3 class="text-foreground text-sm font-semibold">Registrations CSV</h3>
                                    <p class="text-muted-foreground mt-1 text-xs">
                                        Semua data pendaftaran termasuk ringkasan jawaban yang sudah di-humanize (label
                                        → value). URL: <code>.../exports/registrations.csv</code>
                                    </p>
                                </div>
                                <div class="border-border/40 bg-muted/20 rounded-xl border p-4">
                                    <h3 class="text-foreground text-sm font-semibold">Attendance CSV</h3>
                                    <p class="text-muted-foreground mt-1 text-xs">
                                        Log absensi (scan) termasuk waktu scan dan siapa yang men-scan. URL:
                                        <code>.../exports/attendance.csv</code>
                                    </p>
                                </div>
                            </div>
                        </section>

                        <hr class="border-border/30 my-12" />

                        <!-- ============================================== -->
                        <!-- REPORTING -->
                        <!-- ============================================== -->
                        <section id="reporting" data-doc-section class="scroll-mt-24">
                            <h2 class="text-foreground flex items-center gap-3 text-2xl font-bold tracking-tight">
                                <FileText class="text-primary size-6" />
                                Laporan Acara
                            </h2>
                            <p class="text-muted-foreground mt-4 text-sm leading-relaxed">
                                Halaman Laporan (<code>/admin/events/{event}/laporan</code>) menyediakan ringkasan
                                komprehensif per acara:
                            </p>
                            <ul class="text-muted-foreground mt-3 space-y-1.5 text-sm">
                                <li class="flex gap-2">
                                    <ChevronRight class="text-primary mt-0.5 size-4 shrink-0" /> KPI ringkasan (total
                                    pendaftar, accepted, attendance rate)
                                </li>
                                <li class="flex gap-2">
                                    <ChevronRight class="text-primary mt-0.5 size-4 shrink-0" /> Panel log absensi
                                    dengan pagination
                                </li>
                                <li class="flex gap-2">
                                    <ChevronRight class="text-primary mt-0.5 size-4 shrink-0" /> Link langsung ke CSV
                                    export untuk arsip
                                </li>
                            </ul>
                        </section>

                        <hr class="border-border/30 my-12" />

                        <!-- ============================================== -->
                        <!-- TECH STACK -->
                        <!-- ============================================== -->
                        <section id="tech-stack" data-doc-section class="scroll-mt-24">
                            <h2 class="text-foreground flex items-center gap-3 text-2xl font-bold tracking-tight">
                                <Code2 class="text-primary size-6" />
                                Tech Stack
                            </h2>
                            <div class="border-border/40 mt-5 overflow-hidden rounded-xl border">
                                <table class="w-full text-xs">
                                    <thead>
                                        <tr class="border-border/30 bg-muted/30 border-b">
                                            <th class="text-foreground px-4 py-2.5 text-left font-semibold">Layer</th>
                                            <th class="text-foreground px-4 py-2.5 text-left font-semibold">
                                                Teknologi
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-border/20 divide-y">
                                        <tr>
                                            <td class="text-foreground px-4 py-2 font-medium">Backend</td>
                                            <td class="text-muted-foreground px-4 py-2">Laravel 11 (PHP 8.2+)</td>
                                        </tr>
                                        <tr>
                                            <td class="text-foreground px-4 py-2 font-medium">Frontend</td>
                                            <td class="text-muted-foreground px-4 py-2">
                                                Vue 3 (Composition API) + TypeScript
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="text-foreground px-4 py-2 font-medium">Bridge</td>
                                            <td class="text-muted-foreground px-4 py-2">
                                                Inertia.js (server-driven SPA)
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="text-foreground px-4 py-2 font-medium">Styling</td>
                                            <td class="text-muted-foreground px-4 py-2">
                                                Tailwind CSS v4 + shadcn-vue (reka-ui)
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="text-foreground px-4 py-2 font-medium">Auth</td>
                                            <td class="text-muted-foreground px-4 py-2">
                                                Laravel Session + Socialite (Google, GitHub)
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="text-foreground px-4 py-2 font-medium">Roles</td>
                                            <td class="text-muted-foreground px-4 py-2">Spatie laravel-permission</td>
                                        </tr>
                                        <tr>
                                            <td class="text-foreground px-4 py-2 font-medium">Database</td>
                                            <td class="text-muted-foreground px-4 py-2">
                                                MySQL / MariaDB (UUID primary keys)
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="text-foreground px-4 py-2 font-medium">Animations</td>
                                            <td class="text-muted-foreground px-4 py-2">vue3-lottie (LottieFiles)</td>
                                        </tr>
                                        <tr>
                                            <td class="text-foreground px-4 py-2 font-medium">Build</td>
                                            <td class="text-muted-foreground px-4 py-2">Vite</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </section>

                        <hr class="border-border/30 my-12" />

                        <!-- ============================================== -->
                        <!-- AUTH SYSTEM -->
                        <!-- ============================================== -->
                        <section id="auth-system" data-doc-section class="scroll-mt-24">
                            <h2 class="text-foreground flex items-center gap-3 text-2xl font-bold tracking-tight">
                                <Lock class="text-primary size-6" />
                                Autentikasi & Otorisasi
                            </h2>
                            <p class="text-muted-foreground mt-4 text-sm leading-relaxed">
                                DForm menggunakan <strong class="text-foreground">Laravel session guard</strong> untuk
                                autentikasi. OAuth via
                                <strong class="text-foreground">Laravel Socialite</strong> mendukung Google dan GitHub.
                                Akun OAuth disimpan tanpa password lokal (<code>password</code> nullable).
                            </p>
                            <p class="text-muted-foreground mt-3 text-sm leading-relaxed">Middleware utama:</p>
                            <div class="border-border/40 mt-3 overflow-hidden rounded-xl border">
                                <table class="w-full text-xs">
                                    <thead>
                                        <tr class="border-border/30 bg-muted/30 border-b">
                                            <th class="text-foreground px-4 py-2.5 text-left font-semibold">
                                                Middleware
                                            </th>
                                            <th class="text-foreground px-4 py-2.5 text-left font-semibold">Fungsi</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-border/20 divide-y">
                                        <tr>
                                            <td class="text-foreground px-4 py-2 font-mono font-medium">auth</td>
                                            <td class="text-muted-foreground px-4 py-2">
                                                Memastikan user sudah login (redirect ke login jika belum)
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="text-foreground px-4 py-2 font-mono font-medium">organizer</td>
                                            <td class="text-muted-foreground px-4 py-2">
                                                Memerlukan permission <code>events.list</code>; redirect ke user portal
                                                jika tidak
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="text-foreground px-4 py-2 font-mono font-medium">
                                                member_portal
                                            </td>
                                            <td class="text-muted-foreground px-4 py-2">
                                                Memblokir pure organizer dari user portal (harus punya role member)
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="text-foreground px-4 py-2 font-mono font-medium">guest</td>
                                            <td class="text-muted-foreground px-4 py-2">
                                                Hanya untuk user yang belum login (halaman auth)
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </section>

                        <hr class="border-border/30 my-12" />

                        <!-- ============================================== -->
                        <!-- ROLES & PERMISSIONS -->
                        <!-- ============================================== -->
                        <section id="roles-permissions" data-doc-section class="scroll-mt-24">
                            <h2 class="text-foreground flex items-center gap-3 text-2xl font-bold tracking-tight">
                                <ShieldCheck class="text-primary size-6" />
                                Roles & Permissions
                            </h2>
                            <p class="text-muted-foreground mt-4 text-sm leading-relaxed">
                                Menggunakan <strong class="text-foreground">Spatie laravel-permission</strong>. Tiga
                                role utama:
                            </p>
                            <div class="mt-5 space-y-4">
                                <div class="border-border/40 bg-muted/20 rounded-xl border p-4">
                                    <h3 class="text-foreground text-sm font-semibold">super-admin</h3>
                                    <p class="text-muted-foreground mt-1 text-xs">
                                        Akses penuh ke semua fitur tanpa batasan. Semua permission otomatis diberikan.
                                    </p>
                                </div>
                                <div class="border-border/40 bg-muted/20 rounded-xl border p-4">
                                    <h3 class="text-foreground text-sm font-semibold">admin</h3>
                                    <p class="text-muted-foreground mt-1 text-xs">
                                        Penyelenggara acara. Bisa membuat, mengedit, dan menghapus acara & formulir.
                                        Bisa mereview submisi, scan absensi, dan mengekspor data. Tidak bisa mengelola
                                        user, role, atau permission secara langsung.
                                    </p>
                                </div>
                                <div class="border-border/40 bg-muted/20 rounded-xl border p-4">
                                    <h3 class="text-foreground text-sm font-semibold">member</h3>
                                    <p class="text-muted-foreground mt-1 text-xs">
                                        Peserta/pengguna biasa. Bisa menjelajahi acara, mengisi formulir, melihat status
                                        pendaftaran, dan mengelola profil. Diberikan otomatis saat registrasi.
                                    </p>
                                </div>
                            </div>
                        </section>

                        <hr class="border-border/30 my-12" />

                        <!-- ============================================== -->
                        <!-- DATABASE SCHEMA -->
                        <!-- ============================================== -->
                        <section id="database-schema" data-doc-section class="scroll-mt-24">
                            <h2 class="text-foreground flex items-center gap-3 text-2xl font-bold tracking-tight">
                                <Database class="text-primary size-6" />
                                Skema Database
                            </h2>
                            <p class="text-muted-foreground mt-4 text-sm leading-relaxed">
                                Semua tabel menggunakan <strong class="text-foreground">UUID</strong> sebagai primary
                                key. Relasi utama:
                            </p>
                            <div class="mt-5 space-y-4">
                                <div class="border-border/40 rounded-xl border p-4">
                                    <h3 class="text-foreground font-mono text-sm font-semibold">users</h3>
                                    <p class="text-muted-foreground mt-1 text-xs">
                                        id, name, email, password (nullable), avatar, google_id, github_id, soft deletes
                                    </p>
                                </div>
                                <div class="border-border/40 rounded-xl border p-4">
                                    <h3 class="text-foreground font-mono text-sm font-semibold">events</h3>
                                    <p class="text-muted-foreground mt-1 text-xs">
                                        id, title, slug (unique), description, start_date, end_date,
                                        registration_start/end, location, quota, registered_count, banner, price,
                                        session, status, category, soft deletes
                                    </p>
                                </div>
                                <div class="border-border/40 rounded-xl border p-4">
                                    <h3 class="text-foreground font-mono text-sm font-semibold">forms</h3>
                                    <p class="text-muted-foreground mt-1 text-xs">
                                        id, title, description, visible_for (JSON), closed_at, event_id (FK),
                                        banner_url, banner_caption, metadata (JSON), soft deletes
                                    </p>
                                </div>
                                <div class="border-border/40 rounded-xl border p-4">
                                    <h3 class="text-foreground font-mono text-sm font-semibold">form_fields</h3>
                                    <p class="text-muted-foreground mt-1 text-xs">
                                        id, input_type, label, description, name, metadata (JSON), form_id (FK), order,
                                        is_append, soft deletes
                                    </p>
                                </div>
                                <div class="border-border/40 rounded-xl border p-4">
                                    <h3 class="text-foreground font-mono text-sm font-semibold">form_answers</h3>
                                    <p class="text-muted-foreground mt-1 text-xs">
                                        id, answers (JSON), user_id (FK), form_id (FK), review_status, registration_code
                                        (unique), team/bundle columns (leader_form_answer_id, registration_role,
                                        invitation_token, group_token, member_confirmation_status, invited_email), soft
                                        deletes
                                    </p>
                                </div>
                                <div class="border-border/40 rounded-xl border p-4">
                                    <h3 class="text-foreground font-mono text-sm font-semibold">event_attendances</h3>
                                    <p class="text-muted-foreground mt-1 text-xs">
                                        id, event_id (FK), form_answer_id (FK), scanned_by_user_id (FK), scanned_at.
                                        Unique: (event_id, form_answer_id)
                                    </p>
                                </div>
                                <div class="border-border/40 rounded-xl border p-4">
                                    <h3 class="text-foreground font-mono text-sm font-semibold">email_logs</h3>
                                    <p class="text-muted-foreground mt-1 text-xs">
                                        id, form_answer_id, event_id, user_id, recipient_email, status,
                                        notification_type, error_message, sent_at
                                    </p>
                                </div>
                            </div>
                        </section>

                        <hr class="border-border/30 my-12" />

                        <!-- ============================================== -->
                        <!-- REGISTRATION MODES -->
                        <!-- ============================================== -->
                        <section id="registration-modes" data-doc-section class="scroll-mt-24">
                            <h2 class="text-foreground flex items-center gap-3 text-2xl font-bold tracking-tight">
                                <Users class="text-primary size-6" />
                                Mode Registrasi (Teknis)
                            </h2>
                            <p class="text-muted-foreground mt-4 text-sm leading-relaxed">
                                Mode registrasi disimpan di <code>forms.metadata.registration_mode</code>:
                            </p>
                            <div class="border-border/40 mt-5 overflow-hidden rounded-xl border">
                                <table class="w-full text-xs">
                                    <thead>
                                        <tr class="border-border/30 bg-muted/30 border-b">
                                            <th class="text-foreground px-4 py-2.5 text-left font-semibold">Mode</th>
                                            <th class="text-foreground px-4 py-2.5 text-left font-semibold">
                                                Behavior
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-border/20 divide-y">
                                        <tr>
                                            <td class="text-foreground px-4 py-2 font-mono font-medium">single</td>
                                            <td class="text-muted-foreground px-4 py-2">
                                                1 user = 1 FormAnswer. Kuota -= 1.
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="text-foreground px-4 py-2 font-mono font-medium">team</td>
                                            <td class="text-muted-foreground px-4 py-2">
                                                Leader mengisi form + menginput email anggota. Setiap anggota mendapat
                                                FormAnswer dengan <code>registration_role = member</code>,
                                                <code>member_confirmation_status = pending</code>, dan
                                                <code>invitation_token</code>. Kuota -= team_size. Anggota mengonfirmasi
                                                via link undangan.
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="text-foreground px-4 py-2 font-mono font-medium">bundle</td>
                                            <td class="text-muted-foreground px-4 py-2">
                                                Leader mengisi data semua anggota sekaligus. Field dengan
                                                <code>is_append: true</code> dan
                                                <code>metadata.duplicatable: true</code> digandakan per slot
                                                (<code>bundle__{name}__{index}</code>). Kuota -= max_team_size. Semua
                                                <code>group_token</code> sama.
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </section>

                        <hr class="border-border/30 my-12" />

                        <!-- ============================================== -->
                        <!-- FIELD METADATA -->
                        <!-- ============================================== -->
                        <section id="field-metadata" data-doc-section class="scroll-mt-24">
                            <h2 class="text-foreground flex items-center gap-3 text-2xl font-bold tracking-tight">
                                <Settings class="text-primary size-6" />
                                Metadata Field (Teknis)
                            </h2>
                            <p class="text-muted-foreground mt-4 text-sm leading-relaxed">
                                Setiap <code>form_fields.metadata</code> (JSON) berisi konfigurasi field:
                            </p>
                            <div class="border-border/40 mt-5 overflow-hidden rounded-xl border">
                                <table class="w-full text-xs">
                                    <thead>
                                        <tr class="border-border/30 bg-muted/30 border-b">
                                            <th class="text-foreground px-4 py-2.5 text-left font-semibold">Key</th>
                                            <th class="text-foreground px-4 py-2.5 text-left font-semibold">
                                                Keterangan
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-border/20 divide-y">
                                        <tr>
                                            <td class="text-foreground px-4 py-2 font-mono font-medium">builderType</td>
                                            <td class="text-muted-foreground px-4 py-2">
                                                Tipe field di UI builder (short_text, dropdown, radio, dll)
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="text-foreground px-4 py-2 font-mono font-medium">type</td>
                                            <td class="text-muted-foreground px-4 py-2">
                                                Subtipe input HTML (text, email, tel, number)
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="text-foreground px-4 py-2 font-mono font-medium">placeholder</td>
                                            <td class="text-muted-foreground px-4 py-2">Teks placeholder pada input</td>
                                        </tr>
                                        <tr>
                                            <td class="text-foreground px-4 py-2 font-mono font-medium">content</td>
                                            <td class="text-muted-foreground px-4 py-2">
                                                Konten HTML untuk heading/paragraph
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="text-foreground px-4 py-2 font-mono font-medium">
                                                optionChoices
                                            </td>
                                            <td class="text-muted-foreground px-4 py-2">
                                                Array opsi untuk dropdown/checkbox/radio:
                                                <code>[{ id, type, label, imageUrl }]</code>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="text-foreground px-4 py-2 font-mono font-medium">rules</td>
                                            <td class="text-muted-foreground px-4 py-2">
                                                Objek aturan validasi: required, min, max, mimes, max_size, email, url,
                                                in (CSV), is_checkbox, is_multiple
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="text-foreground px-4 py-2 font-mono font-medium">maxStars</td>
                                            <td class="text-muted-foreground px-4 py-2">
                                                Jumlah bintang untuk field rating
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="text-foreground px-4 py-2 font-mono font-medium">
                                                duplicatable
                                            </td>
                                            <td class="text-muted-foreground px-4 py-2">
                                                Boolean. Jika true, field digandakan per slot pada mode bundle
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </section>

                        <hr class="border-border/30 my-12" />

                        <!-- ============================================== -->
                        <!-- SERVICES -->
                        <!-- ============================================== -->
                        <section id="services" data-doc-section class="scroll-mt-24">
                            <h2 class="text-foreground flex items-center gap-3 text-2xl font-bold tracking-tight">
                                <Layers class="text-primary size-6" />
                                Service Layer
                            </h2>
                            <p class="text-muted-foreground mt-4 text-sm leading-relaxed">
                                Business logic dikelompokkan dalam service classes di <code>app/Services/</code>:
                            </p>
                            <div class="mt-5 space-y-3">
                                <div class="border-border/30 rounded-lg border px-4 py-3">
                                    <h3 class="text-foreground font-mono text-xs font-semibold">Event/EventService</h3>
                                    <p class="text-muted-foreground mt-0.5 text-xs">
                                        Create, update, delete, restore events. Banner storage. Slug generation.
                                        Registration status.
                                    </p>
                                </div>
                                <div class="border-border/30 rounded-lg border px-4 py-3">
                                    <h3 class="text-foreground font-mono text-xs font-semibold">
                                        Form/FormAccessGuard
                                    </h3>
                                    <p class="text-muted-foreground mt-0.5 text-xs">
                                        Central access checks: visibility, closure time, registration window, quota,
                                        duplicate prevention.
                                    </p>
                                </div>
                                <div class="border-border/30 rounded-lg border px-4 py-3">
                                    <h3 class="text-foreground font-mono text-xs font-semibold">Form/RulesBuilder</h3>
                                    <p class="text-muted-foreground mt-0.5 text-xs">
                                        Converts field metadata into Laravel validation rules dynamically.
                                    </p>
                                </div>
                                <div class="border-border/30 rounded-lg border px-4 py-3">
                                    <h3 class="text-foreground font-mono text-xs font-semibold">
                                        Registration/TeamRegistrationSubmitter
                                    </h3>
                                    <p class="text-muted-foreground mt-0.5 text-xs">
                                        Team mode: creates leader + member FormAnswers, generates invitation tokens,
                                        dispatches email jobs.
                                    </p>
                                </div>
                                <div class="border-border/30 rounded-lg border px-4 py-3">
                                    <h3 class="text-foreground font-mono text-xs font-semibold">
                                        Registration/BundleRegistrationSubmitter
                                    </h3>
                                    <p class="text-muted-foreground mt-0.5 text-xs">
                                        Bundle mode: group token, per-member answers for duplicatable fields.
                                    </p>
                                </div>
                                <div class="border-border/30 rounded-lg border px-4 py-3">
                                    <h3 class="text-foreground font-mono text-xs font-semibold">
                                        Registration/RegistrationCodeIssuer
                                    </h3>
                                    <p class="text-muted-foreground mt-0.5 text-xs">
                                        Generates human-friendly unique registration codes on acceptance.
                                    </p>
                                </div>
                                <div class="border-border/30 rounded-lg border px-4 py-3">
                                    <h3 class="text-foreground font-mono text-xs font-semibold">
                                        Registration/RegistrationQrPngGenerator
                                    </h3>
                                    <p class="text-muted-foreground mt-0.5 text-xs">
                                        Generates QR code PNG from FormAnswer ID for attendance scanning.
                                    </p>
                                </div>
                                <div class="border-border/30 rounded-lg border px-4 py-3">
                                    <h3 class="text-foreground font-mono text-xs font-semibold">
                                        Scan/GlobalScanResolver
                                    </h3>
                                    <p class="text-muted-foreground mt-0.5 text-xs">
                                        Discriminates one raw scan payload into an event submission or a recruitment
                                        application (QR JSON, submission UUID, or registration code).
                                    </p>
                                </div>
                                <div class="border-border/30 rounded-lg border px-4 py-3">
                                    <h3 class="text-foreground font-mono text-xs font-semibold">
                                        Reporting/EventReportingQuery
                                    </h3>
                                    <p class="text-muted-foreground mt-0.5 text-xs">
                                        Trends, category breakdowns, global/event summaries, attendance pagination for
                                        reporting page.
                                    </p>
                                </div>
                            </div>
                        </section>

                        <div class="border-primary/20 bg-primary/[0.04] mt-16 rounded-2xl border p-6 text-center">
                            <h3 class="text-foreground text-lg font-bold">Ada pertanyaan?</h3>
                            <p class="text-muted-foreground mt-2 text-sm">
                                Hubungi tim DOSCOM atau buka issue di repository untuk bantuan lebih lanjut.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Scroll to top -->
        <button
            v-if="showScrollTop"
            class="border-border/50 bg-card text-muted-foreground hover:text-primary fixed right-20 bottom-6 z-40 flex size-10 items-center justify-center border shadow-sm transition-all lg:right-6"
            @click="scrollToTop"
        >
            <ArrowUp class="size-4" />
        </button>
    </LandingLayout>
</template>

<style scoped>
@reference "../../css/app.css";

.prose-doc code {
    @apply bg-muted text-foreground rounded px-1.5 py-0.5 text-[11px] font-medium;
}
</style>
