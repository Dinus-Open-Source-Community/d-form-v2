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
