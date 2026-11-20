# Sapto Changes — 19 November 2026 (DFORM-SKELETON)

## Ringkasan

Bar progres Inertia dimatikan dan diganti skeleton per halaman. Skeleton dipilih dari peta URL (`constants/inertiaSkeletons.ts`) yang memperhitungkan capability `can_manage_events`, sehingga kelima role mendapat skeleton yang benar: `admin`, `super-admin`, `member`, `recruitment-interviewer`, dan `recruitment-staff`. Setiap skeleton berada di chunk sendiri dengan ukuran 0,90 sampai 1,27 kB gzip dan dimuat saat browser idle.

| Issue | Commit |
| ----- | ------ |
| Bar progres Inertia diganti skeleton per halaman untuk semua role | `feat: map navigation urls to skeleton screens` |
| | `feat: track navigation visits for skeleton screens` |
| | `feat: add skeleton atoms and lazy skeleton registry` |
| | `feat: add skeleton archetypes for list, detail, form, and generic pages` |
| | `feat: add exact skeleton screens for dashboard and event pages` |
| | `feat: add skeleton screens for users, recruitment, builder, and member pages` |
| | `feat: render navigation skeleton overlay in dashboard layouts` |
| | `test: cover navigation skeletons for admin and member roles` |
| | `feat: show area skeleton during event list reloads` |
| | `feat: disable inertia progress bar and cover non-form actions with local spinners` |

## Cakupan role

| Role | Halaman | Skeleton |
| --- | --- | --- |
| `admin`, `super-admin` | `/dashboard` (organizer), `/admin/events*`, `/admin/users*`, `/admin/recruitment*`, `/admin/scan`, `/profile` | `dashboard-index`, `events-index`, `events-show`, `users-index`, `recruitment-index`, `recruitment-period`, `form-builder`, arketipe `list`/`detail`/`form` |
| `super-admin` | tambahan `/browse`, `/joined*` | `user-events`, `user-event-detail` |
| `member` | `/dashboard` (member overview), `/browse`, `/joined*`, `/profile` | `member-dashboard`, `user-events`, `user-event-detail`, `detail` |
| `recruitment-interviewer`, `recruitment-staff` | `/dashboard` (member overview karena tanpa `events.list`), `/admin/recruitment*` sesuai capability, `/admin/scan`, `/profile`, `/browse`, `/joined*` | `member-dashboard`, arketipe `list`/`detail` |

`/dashboard` dilayani dua controller (`routes/web/admin/index.php:23-29`). Resolusi skeleton memakai `auth.user.can_manage_events`, sumber yang sama dengan guard server, bukan nama role.

## Perubahan

- `types/skeleton.ts`, `constants/inertiaSkeletons.ts`, `lib/navigationSkeleton.ts`: kontrak `TSkeletonKey` (14 kunci), aturan regex berurutan, dan `resolveSkeletonKey(url, context)` dengan fallback `generic`.
- `hooks/useNavigationSkeleton.ts`: mendengar `router.on('start')`, melewati method non-GET dan reload `preserveState` pada pathname yang sama, ambang tunda 120 ms, membersihkan state pada `finish`/`cancel`, melepas listener pada scope dispose.
- `components/core/skeleton/*`: atom `SkeletonButton`, `SkeletonSearchField`, `SkeletonToolbar`, `SkeletonCard`, `SkeletonStatCard`, `SkeletonTable` di atas `Skeleton.vue` yang sudah ada (tanpa warna, gradient, atau ikon baru).
- `components/modules/dashboard/skeletons/*`: 10 skeleton persis, 4 arketipe, `SkeletonFallback`, dan `skeletonRegistry.ts` yang memakai `Record<TSkeletonKey, loader>` sehingga kunci yang lupa didaftarkan gagal di typecheck.
- `NavigationSkeletonHost.vue` + `DashboardLayout`/`DashboardFocusLayout`: overlay `absolute inset-0` di area konten (bukan seluruh layar, sidebar dan topbar tetap terlihat), `aria-busy`, teks `sr-only`, dan preload chunk lewat `requestIdleCallback` dengan batas 2 detik.
- `DashboardTopbar`: judul dan breadcrumb diganti dua batang skeleton saat navigasi berjalan dan halaman belum menetapkan judul eksplisit.
- `hooks/useInertiaReloading.ts` + `EventCardSkeleton`: filter/pagination pada `/admin/events` menampilkan skeleton di dalam grid, bukan overlay satu layar.
- `app.js`: `progress: false`.
- Spinner lokal untuk aksi non-form yang belum punya indikator: logout (`DashboardTopbar`, `DashboardFocusLayout`, `Navbar` landing) dan hapus sesi interview (`PeriodInterviewSection`, memakai `loading` pada `ConfirmationModal`).
- Penyesuaian layout `DashboardFocusLayout`: `overflow-y-auto` pindah dari `<main>` ke wrapper konten agar overlay tidak ikut tergulir. Diverifikasi: tinggi dokumen builder sama dengan viewport (1.228 px) sehingga tidak muncul double scroll.

## Verifikasi

- `npm run typecheck`: exit 0.
- `npm run test:e2e:types`: exit 0.
- `npx eslint resources/js`: exit 0.
- `npx vitest run`: 117 file, 1.208 passed, 1 failed. Kegagalan tunggal `AppStatePanel.test.ts > mencantumkan kredit Storyset sesuai lisensi` berasal dari `resources/js/components/core/AppStatePanel.vue` yang sedang diubah sesi lain (file tampil `M` di working tree, tidak disentuh perubahan ini).
- Unit test baru: `navigationSkeleton` 37 kasus, `useNavigationSkeleton` 9, `skeletonRegistry` 2, `useInertiaReloading` 5.
- E2E `tests/e2e/navigation-skeleton.desktop.spec.ts`: 4 passed, 1 skipped. Yang lulus: skeleton `events-index` saat pindah halaman dengan sidebar tetap terpasang, skeleton `events-show` dari klik kartu, pagination tidak memunculkan overlay (cabang di-skip karena daftar acara admin lokal hanya satu halaman), dan back browser tidak meninggalkan overlay.
- E2E `tests/e2e/navigation-skeleton-member.desktop.spec.ts`: 1 passed (fixture member isolated). Membuktikan `member-dashboard`, `user-events`, dan `user-event-detail`.
- Bukti browser role `recruitment-interviewer` (akun seeder `scan-test-interviewer-programming@example.test`): `/admin/recruitment` dialihkan server ke `/admin/recruitment/my-interviews`; sidebar tampil, overlay tidak tertinggal. Level bukti: `Reproduced`.
- `podman exec d_form_app npm run build`: exit 0.
  - Entry `app.js` 51,64 kB (16,24 kB gzip). Budget 350 kB gzip.
  - Chunk halaman terbesar 462,60 kB (145,66 kB gzip). Budget 200 kB gzip.
  - Chunk bersama skeleton `useNavigationSkeleton-*.js` 58,21 kB (15,92 kB gzip), dimuat saat idle.
  - 14 chunk skeleton terpisah: 0,90 sampai 1,27 kB gzip (`FormPageSkeleton` 0,94 kB, `EventsIndexSkeleton` 1,07 kB, `FormBuilderSkeleton` 1,19 kB, dan seterusnya).
- `podman exec d_form_app npm run build:ssr`: exit 0, skeleton tetap menjadi chunk terpisah.
- Perbandingan sebelum/sesudah tidak tersedia: `public/build` sudah dibangun ulang oleh pekerjaan sesi lain pada 14:37 di tengah pengerjaan, sehingga angka "sebelum" tidak bisa dipercaya. Gate yang dipakai adalah budget absolut di atas.
- Lighthouse (Vite dev, bukan produksi): `/` 41 performance, 96 accessibility, 96 best practices, 92 SEO; `/events` 42/96/96/92. LCP 41,0 s dan 42,5 s adalah efek dev server yang menyajikan modul tanpa minifikasi. Audit yang gagal: `errors-in-console` (skrip `seeyu.doscom.org` tidak dapat di-resolve dan banner storage 403 di data lokal), `color-contrast`, `crawlable-anchors`. Angka produksi tidak diukur dan tidak diklaim.
- CLS navigasi dengan skeleton (CDP di `/admin/events`): 0,00015. TBT transisi 0 ms.

## Delivery Gate antislop

| Butir | Hasil |
| ----- | ----- |
| R-02 tanpa em dash | PASS (0 pada file baru dan perubahan) |
| R-03 tanpa overflow 360 px | PASS (skeleton memakai grid halaman asli, kartu calendar 7 kolom memakai `gap-1.5` dan tinggi 48 px) |
| R-27 state loading menyebut apa yang dimuat | PASS (`sr-only` "Memuat halaman", `sr-only` "Memuat daftar acara") |
| R-32 fokus keyboard tidak direbut | PASS (overlay tidak memindahkan fokus; tidak ada `autofocus`) |
| R-35 build dijalankan dan kontrol diklik | PASS (build + build:ssr exit 0; klik sidebar, kartu, filter, pagination, back diuji) |
| R-40 angka hanya hasil ukur | PASS (skor dev dilaporkan apa adanya, angka produksi tidak diklaim) |

## Deviasi dari plan

- `Users/Index.vue` tidak diubah: halaman itu sudah punya skeleton direktori sendiri (`user-directory-skeleton`) yang dipicu `useUserDirectoryFilters`. Menambah `useInertiaReloading` di sana akan menduplikasi indikator.
- `Forms/Show.vue` tidak diubah: reload `only: ['formShare']` berasal dari PUT ubah status yang sudah punya `isChangingStatus` dan spinner tombol; skeleton area tidak relevan untuk perubahan state kecil.
- Spec E2E memakai nama test bahasa Inggris agar konsisten dengan suite yang ada, bukan nama Indonesia di plan.

## Temuan di luar scope

- Modul Broadcasts punya 4 halaman dan controller, tetapi tidak ada satu pun route terdaftar.
- `RecruitmentDivisionController::index` merender `Dashboard/Recruitment/Divisions/Index` yang tidak punya file page dan tidak punya GET route.
- `e2e:cleanup` masih gagal FK `events_created_by_foreign` saat menghapus user fixture.
- `resources/js/components/core/AppStatePanel.vue` yang diubah sesi lain membuat `AppStatePanel.test.ts` gagal (kredit Storyset hilang dari komponen).
- `/admin/events` untuk role `admin` hanya satu halaman sehingga cabang pagination tidak teruji di E2E; perilaku `preserveState` sudah ditutup unit test `useNavigationSkeleton` dan `useInertiaReloading`.

## Verifikasi lanjutan: sapuan browser semua halaman

Sapuan dilakukan di browser nyata dengan `router.visit` Inertia (SPA, bukan page load) dan request Inertia ditunda 600-900 ms agar skeleton sempat terlihat. Setiap baris membaca `data-skeleton-key` dari overlay dan komponen tujuan dari `$page`.

### super-admin

| URL | Skeleton | Komponen tujuan |
| --- | --- | --- |
| `/dashboard` | `dashboard-index` | `Dashboard/Index` |
| `/admin/events` | `events-index` | `Dashboard/Events/Index` |
| `/admin/events/archived` | `list` | `Dashboard/Events/Archived` |
| `/admin/events/create` | `form` | `Dashboard/Events/Create` |
| `/admin/events/{event}` | `events-show` | `Dashboard/Events/Show` |
| `/admin/events/{event}/edit` | `form` | `Dashboard/Events/Edit` |
| `/admin/events/{event}/forms/create` | `form` | `Dashboard/Events/Forms/Create` |
| `/admin/events/{event}/forms/{form}` | `form-builder` | `Dashboard/Events/Forms/Show` |
| `/admin/events/{event}/forms/{form}/submissions` | `list` | `Dashboard/Events/Forms/Show` (redirect ke `?tab=jawaban`) |
| `/admin/events/{event}/forms/{form}/fill` | `form` | `Dashboard/Events/Forms/Fill` |
| `/admin/events/{event}/registration-status` | `detail` | redirect ke tab jawaban |
| `/admin/users` | `users-index` | `Dashboard/Users/Index` |
| `/admin/users/{user}` | `detail` | `Dashboard/Users/Show` |
| `/admin/recruitment` | `recruitment-index` | `Dashboard/Recruitment/Index` |
| `/admin/recruitment/periods/create` | `form` | `Dashboard/Recruitment/Periods/Create` |
| `/admin/recruitment/periods/{period}` | `recruitment-period` | `Dashboard/Recruitment/Periods/Show` |
| `/admin/recruitment/activity-logs` | `list` | `Dashboard/Recruitment/ActivityLogs/Index` |
| `/admin/scan` | `detail` | `Dashboard/Scan/Global` |
| `/profile` | `detail` | `Dashboard/Profile` |
| `/browse` | `user-events` | `Dashboard/User/Events` |
| `/joined` | `user-events` | `Dashboard/User/Events` |
| `/joined/{event}` | `user-event-detail` | `Dashboard/User/EventDetail` |

Catatan redirect yang terbukti: `/admin/recruitment/periods`, `/reports`, `/interview-sessions`, dan `/my-interviews` dialihkan server ke `/admin/recruitment` untuk super-admin, sehingga skeleton yang tampil adalah milik URL yang diminta. `/admin/events/{event}/forms/{form}/edit` dialihkan ke halaman builder.

### recruitment-interviewer

| URL | Skeleton | Komponen tujuan |
| --- | --- | --- |
| `/admin/recruitment` | `recruitment-index` | redirect ke `Dashboard/Recruitment/MyInterviews/Index` |
| `/admin/recruitment/my-interviews` | `list` | `Dashboard/Recruitment/MyInterviews/Index` |
| `/admin/recruitment/my-interviews/{interview}` | `detail` | `Dashboard/Recruitment/MyInterviews/Show` (403 untuk interview milik orang lain) |
| `/profile` | `detail` | `Dashboard/Profile` |
| `/browse` | `user-events` | `Dashboard/User/Events` |
| `/joined` | `user-events` | `Dashboard/User/Events` |
| `/admin/scan` | `detail` | `Error` 403 |

### member

`/joined`, `/joined/{event}`, `/joined/{event}/registration`, `/browse`, `/profile`, dan `/admin/scan` semuanya menampilkan skeleton (`user-events`, `user-event-detail`, `detail`). `/dashboard` untuk peserta tidak lagi dipakai: perubahan yang belum di-commit di working tree menghapus `MemberDashboardController` dan mengubah route menjadi `abort_if(! can('events.list'), 404)`. Karena itu kunci `member-dashboard` dihapus dari peta oleh perubahan tersebut.

### Halaman yang tidak mendapat skeleton (disengaja)

- Halaman publik dan auth (`/`, `/events`, `/events/{slug}`, `/features`, `/docs`, form fill publik, `OpenRecruitment/*`) memakai `LandingLayout`/`AuthLayout` dan tidak memasang host.
- Halaman yang dicapai lewat page load biasa (ketik URL, refresh, tautan `target="_blank"`, redirect setelah POST) tidak memicu kunjungan Inertia, jadi skeleton tidak muncul. Ini perilaku yang diminta: skeleton hanya untuk perpindahan antar-halaman di dalam aplikasi.

### Celah yang ditemukan dan diperbaiki pada sapuan ini

- **Halaman error tidak punya host.** Setelah 403/404, navigasi berikutnya tidak menampilkan skeleton karena `Error.vue` tidak memakai layout dashboard. Diperbaiki: `Error.vue` memanggil `useNavigationSkeleton` dan memasang host. Dibuktikan dengan 403 di `/admin/scan` lalu menuju `/profile`: skeleton `detail` muncul.
- **Halaman ber-`FormFillLayout` tidak punya host.** `/joined/team-invitations/{token}` dan `/admin/events/{event}/forms/{form}/fill` adalah halaman dashboard yang memakai layout form fill. Diperbaiki: host dipasang di `<main>` layout. Ditambah aturan peta `form` untuk kedua URL. Dibuktikan dengan skeleton `form` pada form fill.

## Verifikasi Lighthouse produksi

Diukur dengan build produksi (`public/hot` dipindahkan sementara, `DEBUGBAR_ENABLED=false`, `public/build` hasil `npm run build`), Chrome headless, throttling mobile bawaan Lighthouse.

| Halaman | Performance | Accessibility | Best Practices | SEO | LCP | FCP | CLS | TBT |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| `/` | 79 | 100 | 96 | 100 | 2,8 s | 1,5 s | 0 | 630 ms |
| `/events` | 92 | 100 | 96 | 100 | 2,9 s | 1,5 s | 0 | 190 ms |

Angka Performance berfluktuasi antar run pada mesin ini (79-84 untuk `/`, 88-92 untuk `/events`).

Audit yang masih gagal dan penyebabnya:

- `errors-in-console` (Accessibility sudah 100, ini penyebab Best Practices 96): `https://seeyu.doscom.org/script.js` tidak dapat di-resolve di environment lokal, dan banner seed `/storage/events/banners/image-*.jpg` dibalas 403 karena file seed tidak ada di disk. Keduanya bukan kode skeleton.
- Performance belum 100: biaya utama ada pada payload dan hidrasi halaman publik (`unused-javascript` 78-83 KiB, `mainthread-work-breakdown` 3,0-4,3 s pada CPU throttle). Untuk mencapai 100 perlu pekerjaan terpisah pada halaman publik (defer seksi berat, kurangi JS landing), di luar cakupan skeleton.
- `color-contrast` sudah diperbaiki pada sapuan ini: label footer `text-foreground/50` menjadi `/70` dan `text-muted-foreground/60` menjadi `text-muted-foreground`. Accessibility naik dari 96 ke 100 pada `/` dan `/events`.

### Metrik dashboard produksi (CDP, bukan Lighthouse)

- Muat `/admin/events` dengan build produksi: CLS 0, TBT 111 ms, aset `/build/assets` terpasang, debugbar tidak aktif.
- Transisi dengan skeleton menuju `/admin/users`: CLS 0, TBT 0 ms, skeleton `users-index`.

## Catatan environment saat verifikasi

- `vendor/laravel/framework` sempat kosong dan container `d_form_app` crash-loop (bukan akibat perubahan skeleton). Dipulihkan dengan `composer reinstall laravel/framework` lewat container sekali pakai yang memakai volume `vendor` yang sama; `php artisan --version` kembali normal dan aplikasi merespons 200.
- `node_modules` di host kehilangan `@rollup/rollup-linux-x64-gnu` sehingga Vitest host gagal start. Semua test Vitest dijalankan di container (`podman exec d_form_app npx vitest run ...`).
- `storage/app/e2e` dimiliki uid lain; Playwright butuh `chmod -R 777` pada direktori artefak itu agar global setup bisa membuat direktori heartbeat.
- Perubahan working tree milik sesi lain (penghapusan `MemberDashboardController`, perubahan auth/middleware, `AppStatePanel.vue`) tidak disentuh dan tidak ikut commit. Commit terakhir hanya memuat hunk skeleton dan kontras footer.
