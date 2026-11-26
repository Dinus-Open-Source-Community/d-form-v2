# Sapto Changes — 26 November 2026 (DFORM-TOPBAR-SKELETON)

## Ringkasan

Topbar dashboard dirapikan dan seluruh skeleton navigasi non-landing diselaraskan dengan layout halaman aslinya: breadcrumb kini menampilkan nama entitas (bukan UUID), tombol kembali memakai induk hierarki halaman, input pencarian yang tidak berfungsi dihapus, dan 12 skeleton halaman baru menggantikan arketipe generik pada route yang salah peta.

| Issue | Commit |
| ----- | ------ |
| Topbar breadcrumb/back/search | `fix(topbar): perbaiki breadcrumb, tombol kembali, dan hapus pencarian pasif` |
| Pemetaan skeleton per route | `fix(skeleton): petakan route redirect dan route aktif ke skeleton tujuannya` |
| Skeleton halaman baru | `feat(skeleton): tambah 12 skeleton halaman sesuai layout asli` |

## Perubahan

### Topbar dan breadcrumb

- `buildBreadcrumbs()` menerima parameter ketiga opsional `{ segmentLabels, homeHref }`; halaman mengisi `segmentLabels` lewat `setTopbar` sehingga segmen ID menampilkan nama entitas, bukan UUID mentah. `ACTION_LABELS` diperluas untuk `periods`, `applications`, `interview-sessions`, `my-interviews`, `submissions`, `registration-status`, `activity-logs`, `reports`, dan `divisions`.
- Halaman profil memakai induk `Beranda` dengan `homeHref = resolveDashboardHomeHref(can_manage_events)`.
- Tombol kembali menjadi `<Link>` deterministik ke crumb induk terdekat (`parentBreadcrumbHref`); `history.back()` dan `resolveNavbarFallbackBackHref` dihapus. Halaman akar tidak menampilkan tombol.
- Input pencarian topbar dihapus beserta state, impor `Input`/`Search`, dan `BASE_PAGE_PATHS`/`goBack`/`navigateBack` karena tidak pernah aktif.
- Judul halaman kini tampil juga di layar sempit (`text-base`, `truncate`, `sm:text-lg`); sebelumnya blok judul memakai `hidden sm:flex` sehingga topbar mobile hanya berisi tombol kembali dan avatar. Breadcrumb tetap khusus layar lebar. Penanda stabil `data-testid="topbar-title"` ditambahkan untuk pengujian.

### Pemetaan skeleton

- `PAGE_SKELETON_RULES` ditulis ulang: route lama yang hanya mengalihkan di server (`/admin`, `/admin/dashboard`, `/admin/events/archived`, `/admin/events/create`, `/admin/events/{e}/forms/{f}/submissions|edit`, `/admin/recruitment/periods|reports|interview-sessions`, `/admin/events/{e}/scan`, `/admin/recruitment/attendance-scan`, `/joined/profile`) dipetakan ke skeleton tujuan, sehingga tidak ada kedipan arketipe generik saat redirect selesai.
- `/admin/events/create` memakai `event-form` (halaman create menampilkan form detail acara + stepper, bukan builder) dan `/admin/events/{e}/forms/create` tetap `form-builder`.
- Rule `registration-status` dihapus karena endpoint mengembalikan JSON, bukan halaman Inertia.
- `/admin/recruitment/divisions` tidak punya route `GET`; tombol "Divisi" membuka `DivisionListSheet`, jadi tidak ada halaman yang perlu di-skeleton-kan.

### Skeleton halaman

Kunci baru: `users-show`, `profile`, `scan-global`, `my-interviews`, `my-interview`, `interview-session`, `activity-logs`, `event-form`, `form-fill`, `form-success`, `event-registration`, `recruitment-period-create`.

- `events-index` dipakai juga untuk `events-archived` (grid kartu 4 kolom, bukan arketipe list).
- `recruitment-index` diselaraskan menjadi tiga seksi (perlu tindakan, daftar periode + toolbar + pagination, interview hari ini).
- `form-builder` menjadi dua panel (palette + kanvas); panel pengaturan kanan dihapus karena tidak ada di `FormBuilderWorkspace`.
- `recruitment-period` memakai baris tab selebar kontainer (5 tab), `activity-logs` tanpa pagination palsu, `profile` mengikuti header + tiga `ProfileSectionCard` bertumpuk, `event-registration` mengikuti urutan kartu asli (info, banner, jawaban, check-in, status, kode D-Kelas).
- `event-form` mengikuti `EventDashboardForm`: header aksi + grid 12 kolom dengan satu kartu tinggi di kiri dan tiga kartu di kanan.

## Verifikasi

Commit: `ca313f6e` (+ `4a9900fd` untuk menghapus dua baris markup nyasar di `AppStatePanel.vue` yang membuat gate Prettier gagal sejak commit sebelumnya). Diverifikasi pada worktree bersih di commit tersebut, dengan perintah yang sama seperti `.github/workflows/quality.yml`.

- Frontend job: `wayfinder:generate`, `prettier --check` (seluruh `resources/js`, `eslint.config.js`, `scripts`), `eslint`, `tsc -p tsconfig.vitest.json`, `vue-tsc --noEmit`, `vitest run` (125 file, 1288 test), `vite build`, `build:ssr`, `check-bundle-budget.mjs` — semua exit 0.
- PHP job: `php artisan test` — 1977 passed, 18 skipped, 0 failed (12467 assertion), 147,54 dtk.
- Lint job: `./vendor/bin/pint --test` — PASS 990 file.
- Security job: `composer validate --strict`, `composer audit --locked`, `npm audit --omit=dev --audit-level=critical` — semua exit 0.
- E2E `form-builder-topbar.desktop.spec.ts` + `navigation-skeleton.desktop.spec.ts`: 7 passed, 1 skipped (butuh lebih dari satu halaman acara untuk uji pagination).
- E2E `topbar-mobile-title.mobile.spec.ts`: 3 passed (judul tampil dan sesuai halaman, breadcrumb tersembunyi, tanpa overflow horizontal).
- Suite E2E penuh (540 test, 1 worker, dijalankan terhadap dev server yang memakai `SESSION_DRIVER=redis`): 282 passed, 244 failed, 9 skipped, 5 did not run. Akar masalahnya environment, bukan perubahan ini: spec seperti `auth.spec.ts`, `auth-tabs-request-count`, `otp-verification`, `event-search`, `pii-log-redaction`, dan `auth/logout` memanggil `Cache::flush()` atau logout, sehingga session store Redis (driver yang sama dengan cache) terhapus dan storage state admin bersama mati untuk spec berikutnya. Bukti: (a) spec penjaga `session-isolation.desktop.spec.ts` ikut gagal pada run itu; (b) kegagalan berikutnya berakhir di `/auth/login` (mis. `form-builder-topbar` create: "navigated to /auth/login"); (c) reproduksi deterministik `auth.spec.ts` + `form-builder-topbar.desktop.spec.ts` dalam satu invocation membuat spec kedua gagal; (d) ketiga spec tiket ini lulus saat dijalankan dengan global setup baru. README sudah mendokumentasikan batasan ini dan menyarankan server E2E memakai session driver `file` (spec `session-isolation` hijau dengan driver itu), atau set `E2E_SESSION_DRIVER=redis` untuk melewati penjaga tersebut.
- Screenshot mobile 390×844 (`/admin/events`, `/profile`): judul tampil, `scrollWidth - clientWidth = -10` (tidak ada overflow).
- Probe browser: 13 route diperiksa, `data-skeleton-key` sesuai harapan dan redirect berakhir di halaman tujuan. Screenshot di `/tmp/opencode/audit/out/skel/`.
- GitHub Actions terakhir untuk `dev-zapp` sukses pada 2026-11-21 (sebelum commit ini); commit ini belum di-push sehingga run CI untuk SHA ini belum ada. Semua job CI direproduksi lokal dengan hasil di atas.
- Assertion E2E `heading level 1 "Acara"` diganti assertion breadcrumb: topbar merender judul sebagai paragraf (`<p>`) dan halaman `/admin/events` tidak punya `h1`, sehingga assertion lama tidak pernah bisa lulus.

## Keputusan desain

- Redirect dipetakan ke skeleton tujuan, bukan dibiarkan jatuh ke `generic`: pengguna melihat struktur halaman final selama permintaan berjalan.
- Satu skeleton untuk dua halaman hanya bila strukturnya memang sama (`events-index` untuk arsip; `form-fill` untuk fill dan undangan tim).
- Arketipe `list`/`detail`/`form` dipertahankan untuk halaman jarang dibuka (detail lamaran, form undangan penerimaan) agar tidak menambah chunk tanpa manfaat.

## Catatan dan tindak lanjut

- Temuan audit sebelumnya soal halaman `Dashboard/Recruitment/Divisions/Index.vue` yang hilang tidak valid: tidak ada route `GET` dan UI memakai drawer. Tidak ada tiket lanjutan.
- Judul halaman di topbar masih `<p>`; halaman yang tidak punya `h1` sendiri (mis. `/admin/events`) tidak memiliki heading level 1. Di luar scope tiket ini dan belum diubah.
- `storage/app/e2e/**` sempat tidak bisa ditulis host (`EACCES` pada global setup); direktori uji di-set `2777` lewat container agar E2E bisa jalan. Direktori ini gitignored.
