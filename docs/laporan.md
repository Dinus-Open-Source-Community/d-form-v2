# Laporan Audit Teknis — D-Form v2

> **Status:** temuan saja. Tidak ada satu pun file kode yang diubah, dihapus, atau dipindahkan.
> **Tanggal audit:** 16 September 2026
> **Ruang lingkup:** seluruh repo (frontend, backend, repo hygiene, CI/CD, dokumentasi)
> **Sifat:** audit statis berbasis pembacaan kode + perhitungan. Tidak ada runtime/benchmark.

---

## 1. Ringkasan Eksekutif

Proyek ini **tidak berantakan secara total** — ada disiplin nyata di beberapa tempat yang akan saya sebut jujur di Bagian 7. Tapi klaim "banyak yang tidak kepakai dan tidak konsisten" **terbukti benar**, dan yang lebih mengganggu: masalah terbesarnya bukan kode mati, melainkan **tidak adanya penjaga (guard)**. Repo ini punya 404 test method, `strict: true` di tsconfig, ESLint, Prettier, dan Pint — **dan tidak satupun dari itu pernah dijalankan otomatis di pipeline deploy**. Semua gerbang mutu ada di atas kertas.

Tiga hal yang paling merusak, berurutan:

1. **Password database produksi ter-commit ke git** di `docker-compose.prod.yml.bak`.
2. **Deploy ke produksi tidak menjalankan test, lint, build, atau typecheck.** Jenkins hanya build image → deploy → cek health.
3. **Onboarding tidak bisa direproduksi.** 193 env key dipakai `config/` tapi tidak ada di `.env.example`.

Sisanya adalah pola: **dua sistem untuk satu masalah** (dua lockfile, dua CI, dua registry route, dua library komponen, lima formatter tanggal, tiga varian komponen select). Itu bukan kebetulan — itu gejala tidak adanya konvensi tertulis yang ditegakkan.

### Skor per area

| Area | Skor | Alasan singkat |
|---|---|---|
| Test suite | **B** | 404 test, 0 skipped — bagus, tapi tidak pernah dijalankan CI |
| Keamanan & hygiene repo | **D** | Secret di git, file `.bak` ter-commit, `.gitignore` copy-paste 4× |
| CI/CD | **F** | 2 sistem CI, keduanya tidak menguji apa pun; GH Actions cuma trigger `dev`/`temp` |
| Type safety & lint | **D** | `strict: true` tapi zero enforcement; tidak ada script typecheck |
| Backend arsitektur | **C+** | Service layer rapi di sebagian tempat, tapi business logic besar tinggal di closure route |
| Frontend arsitektur | **C** | Komponen modular, tapi dua registry route + dua library komponen + lib/ terfragmentasi |
| Reusability | **D** | `lib/dummyData.ts` jadi dependency produksi; 4 composable 1 halaman; 5 formatter tanggal |
| Dokumentasi | **C−** | 67 file, terorganisir per orang, dan 26 dokumen desain justru di-ignore |
| Konsistensi tooling | **D** | 4 file konfigurasi formatting, tidak satu pun ditegakkan |

---

## 2. Metodologi & Batasan

**Yang dilakukan:**
- Pembacaan langsung file konfigurasi, route, komponen, service, dan pipeline.
- Penghitungan LOC, jumlah file, jumlah pemakai (import site) per modul via `grep`/`rg`.
- Deteksi kode mati: pencarian nama file/kelas di **seluruh repo** (kecuali file itu sendiri).
- Verifikasi silang: `git ls-files` untuk status tracked/ignored.

**Yang TIDAK diverifikasi (jangan diperlakukan sebagai fakta):**
- Semua isi `docs/**` **tidak** diverifikasi terhadap kode, kecuali yang disebut eksplisit. Klaim di dokumen bisa jadi sudah basi; itu belum saya buktikan.
- Komponen UI dianggap "mati" hanya berdasarkan **tidak adanya import dari luar `components/ui/`**. Komponen yang dipakai secara transitif oleh komponen UI lain (contoh: `calendar` dipakai `date-picker`) belum ditelusuri — lihat P2-7.
- Container/Dockerfile tidak dibedah; saya tidak memverifikasi apakah `npm run build` dijalankan di dalam image.
- Tidak ada eksekusi test, build, atau lint. Semua pernyataan tentang "gagal/hijau" tidak dibuat.

---

## 3. P0 — Kritis (hentikan pekerjaan fitur, kerjakan ini dulu)

### P0-1. Password database produksi ter-commit ke git

**Bukti:** `docker-compose.prod.yml.bak` (tracked: `git ls-files` → 1) berisi:

```
MARIADB_ROOT_PASSWORD: dform_db_secret_2024
MARIADB_DATABASE: d_form
```

Sementara `docker-compose.prod.yml` versi baru sudah benar (pakai `${DB_PASSWORD:?}` dan `secrets: db_root_password`). Artinya: **seseorang memperbaiki masalah ini, tapi file lama yang berisi password justru tetap di-commit, bukan dihapus.**

Catatan penting: `Jenkinsfile:59` sudah memakai credential `d-form-db-root-password`, jadi perbaikan sudah setengah jalan. Tapi password lama masih ada di git history dan harus dianggap **sudah bocor**.

**Dampak:** siapa pun yang punya akses baca repo punya kredensial DB produksi. Null.

**Tindakan:**
1. Rotasi password DB produksi **hari ini**, sebelum membahas hal lain.
2. Hapus `docker-compose.prod.yml.bak` dari working tree **dan** dari history (`git filter-repo` atau BFG). Rotasi tetap wajib meski history dibersihkan.

### P0-2. Tidak ada pipeline yang menguji apa pun

**Bukti:**

| Pipeline | Isi | Masalah |
|---|---|---|
| `.github/workflows/code_formatting.yml` | Pint `--test` + Prettier `--check` | Trigger hanya branch `dev` & `temp` (baris 8–14) → **`main` tidak pernah dicheck**. Kedua step `continue-on-error: true` (baris 54, 70) → **pipeline tidak bisa gagal**. Node 20 (baris 61) padahal README minta Node 24+. Tidak ada test/lint/build/typecheck. |
| `Jenkinsfile` | Checkout → Preflight → Validation → Build → Deploy → Verify | Deploy ke produksi pada branch `main` (baris 80–140). **Nol tahap test, lint, typecheck, atau frontend lint.** |

**Akibat nyata:** 404 test method di `tests/` tidak punya satu pun kesempatan untuk menahan regresi di alur deploy. `strict: true` di `tsconfig.json:14` tidak pernah dievaluasi. ESLint tidak pernah dijalankan otomatis — `package.json` punya script `lint` (baris 8) tapi tidak ada yang memanggilnya.

**Tindakan:** satu pipeline, satu kebenaran. Lihat Fase 1 di Bagian 8.

### P0-3. Onboarding tidak bisa direproduksi

**Bukti:** `config/` mereferensikan **244** env key; `.env.example` hanya berisi **54**. **193 key tidak terdokumentasi**, termasuk yang jelas dibutuhkan aplikasi:

- OAuth: `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, `GOOGLE_REDIRECT_URI`, `GITHUB_CLIENT_ID`, `GITHUB_CLIENT_SECRET`, `GITHUB_REDIRECT_URI` — dan ini **bukan opsional**: `OAuthController.php` hidup dan punya route.
- Email/registrasi: `RECRUITMENT_STAFF_EMAIL`, `REGISTRATION_EMAIL_SEND_DELAY_SECONDS`, `REGISTRATION_EMAIL_JITTER_MIN_SECONDS/MAX_SECONDS`, `REGISTRATION_INVITATION_TTL_DAYS`.
- SEO: `SEO_SITE_NAME`, `SEO_DEFAULT_OG_IMAGE`, `SEO_GOOGLE_SITE_VERIFICATION`, `SEO_BING_SITE_VERIFICATION`, `SEO_LOCALE`, `SEO_THEME_COLOR`.
- Inertia SSR: `INERTIA_SSR_ENABLED`, `INERTIA_SSR_URL`, `INERTIA_SSR_ENSURE_BUNDLE_EXISTS`.

**Dampak:** developer baru (atau kamu sendiri 3 bulan lagi) akan menjalankan app, lalu menemukan fitur yang gagal diam-diam tanpa pesan error yang jelas. Ini biaya onboarding yang dibayar berulang.

### P0-4. `strict: true` adalah dekorasi

**Bukti:** `tsconfig.json:14` → `"strict": true`. Tapi:
- Tidak ada script `typecheck` di `package.json`.
- Tidak ada `vue-tsc`/`tsc` di CI mana pun (dicari di `package.json`, `Makefile`, `Jenkinsfile`, `.github/` → **NONE FOUND**).
- `eslint.config.js` tidak mengaktifkan type-aware rules (`parserOptions.project`/`projectService` tidak ada).

**Catatan penting dan positif:** secara statistik kodenya **disiplin** — `: any` = 0, `as any` = 0, `@ts-expect-error` = 1, `eslint-disable` = 2, di 39.700 LOC frontend. Jadi bukan kodenya yang buruk; yang hilang adalah **penjaga** supaya disiplin itu tidak rusak. Tanpa typecheck, satu commit malas cukup untuk membalikkan keadaan ini, dan tidak ada yang akan menangkapnya.

---

## 4. P1 — Serius

### P1-1. Business logic besar hidup di dalam closure route

**Bukti:** `routes/web/admin/index.php` = **259 LOC** (file route terbesar di repo; bandingkan `routes/web/admin/recruitment.php` = 100 LOC). Di dalamnya:

- Route `/joined` berisi ~60 baris query + transformasi payload Inertia, ditulis langsung di closure (blok `Route::middleware(['auth','member_portal'])->prefix('/joined')`).
- Route `/{event_segment}` berisi ~90 baris logika: resolve event, query `FormAnswer`, hitung `isPortalRegistered`, generate QR PNG, cek `FormAccessGuard` per form — semuanya inline.
- Di seluruh `routes/` ada **26 closure** dan **16 arrow function**.

**Kenapa ini masalah besar di proyek ini secara spesifik:** repo ini punya `app/Services/` berisi 44 class dengan `RegistrationQrPngGenerator`, `FormAccessGuard`, `EventService`, `UserPortalEventResolver` — dan semuanya dipanggil dari dalam route file. Pola service layer sudah ada dan dipahami tim, tapi jalur terpenting (portal member) justru melewatinya. Ini bukan "kurang rapi", ini **inkonsistensi arsitektur di jalur yang paling sering diubah**.

**Tindakan:** pindahkan kedua closure itu ke invokable controller/service. Route file harusnya cuma peta, bukan tempat tinggal query.

### P1-2. Dua sistem route — dan separuh yang digenerate dibuang setiap build

**Bukti (dihitung dari import site di luar direktori generated):**

| Sistem | Ukuran | Pemakai |
|---|---|---|
| `resources/js/lib/routes.ts` — manual, ditulis tangan | 253 LOC | **68 file** |
| Wayfinder `resources/js/actions/**` — generated | 75 file / 6.872 LOC | **16 file** |
| Wayfinder `resources/js/routes/**` — generated | 54 file / **7.308 LOC** | **0 file** |
| `resources/js/wayfinder/index.ts` — helper runtime | 1 file / 167 LOC | dipakai `actions/` |

`vite.config.js:16` menjalankan `wayfinder()` pada setiap build. Artinya **54 file / 7.308 LOC digenerate berulang kali, lalu tidak diimpor siapa pun.**

Ironi terbesarnya ada di komentar `lib/routes.ts:1-4`:

> *"Pusat definisi URL frontend — selaras dengan `routes/web/**`. Gunakan builder di sini; hindari string path hardcoded di komponen."*

Jadi tim **sengaja** membuat registry manual, sementara Wayfinder — yang menghasilkan registry yang sama dari sumber kebenaran sebenarnya (file route PHP) — sudah tersedia dan hanya dipakai separuh (untuk form actions, bukan untuk navigasi).

`DashboardSidebar.vue:36` mengonfirmasi mana yang menang: ia mengimpor `{ isSidebarNavActive, routes }` dari **`@/lib/routes`**, bukan dari `@/routes/`.

**Bukti bahwa duplikasi manual sudah mulai rusak:** dari 107 nama route unik di `routes/`, banyak yang tidak punya padanan di `lib/routes.ts`, misalnya seluruh `auth.*` (`auth.login`, `auth.logout`, `auth.password.*`), `applications.show`, `applications.screening.pass/revision/reject`, `applications.final.accept/reject`, `corrections.approve/reject`, `applications.evaluate.override`, `activity-logs.index`. Jadi `lib/routes.ts` bukan hanya duplikat — ia **duplikat yang sudah tidak lengkap**.

**Risiko konkret:** rename route di PHP → Wayfinder ikut berubah, `lib/routes.ts` tidak. Tidak ada typecheck yang menangkap. Bug 404 di produksi.

**Tindakan:** pilih satu, jangan dua-duanya.

- **Opsi A (rekomendasi):** migrasi navigasi ke `@/routes/**` yang sudah digenerate, sisakan `lib/routes.ts` hanya untuk helper non-route (`pathWithoutQuery`, `isSidebarNavActive`, `resolveNavbarFallbackBackHref`). Untung: hilang 253 LOC manual, route jadi type-safe, rename di PHP langsung terasa.
- **Opsi B:** kalau tim memutuskan tetap manual, matikan generate `routes/**` di `vite.config.js` supaya 7.308 LOC itu tidak digenerate tiap build.

Yang **tidak** boleh: membiarkan keduanya hidup, seperti sekarang.

### P1-3. `lib/dummyData.ts` adalah dependency produksi

**Bukti:** `resources/js/lib/dummyData.ts` (363 LOC) mengekspor **dua hal yang tidak berhubungan**:

- Fixture palsu: `dummyEvents`, `dummyForms`, `dummyFormFields`, `dummyRegistrants`, `dummyChartData`.
- Helper produksi: `formatDate` (:357), `formatDateTime` (:361), `statusColorMap` (:320), `categoryColorMap` (:334), `categoryLabelMap` (:350), `sessionLabelMap` (:341).

File ini diimpor oleh **minimal 10 file produksi**, termasuk `pages/EventDetail.vue:7`, `pages/Dashboard/User/Index.vue:11`, `pages/Dashboard/Events/Registrants.vue:14`, `pages/Dashboard/Events/Forms/Index.vue:13`, bahkan `utils/composables/useDashboardEventShowPage.ts:13` dan `lib/registrantsUi.ts:1`.

**Kenapa ini buruk:** nama file berbohong. Nama "dummyData" membuat reviewer mengira ini fixture test, padahal ini **modul utilitas dengan fan-out tertinggi di frontend**. Setiap orang yang mau berani menghapus "dummy data" akan mematahkan 10 halaman. Dan lambat laun fixture palsu itu akan tercampur makin dalam ke logika nyata.

Diperparah: `lib/dummyData.ts:357` (`formatDate`) adalah **implementasi formatter tanggal kelima** di repo — lihat P1-5.

### P1-4. Kode mati yang terverifikasi

Semua item di bawah **nol referensi** di seluruh repo (dicari berdasarkan nama file/kelas, mengecualikan file itu sendiri):

**Frontend — 7 file, 621 LOC:**

| File | LOC |
|---|---|
| `components/modules/dashboard/FormSubmissionsTableView.vue` | 159 |
| `components/modules/dashboard/DashboardNavbar.vue` | 132 |
| `components/modules/dashboard/recruitment/PeriodPhaseTimeline.vue` | 124 |
| `components/modules/dashboard/RegistrantDetailSheet.vue` | 115 |
| `components/modules/dashboard/recruitment/PeriodStatusHero.vue` | 71 |
| `components/core/input/AuthInput.vue` | 19 |
| `lib/eventValidationToast.ts` | 1 |

**Backend — 235 LOC:**
- `app/Services/BundleSubmissionGrouper.php` — **tidak direferensikan sama sekali**. Perhatikan: ini juga satu-satunya file yang duduk **datar di `app/Services/`** sementara 43 file lain dikelompokkan ke subfolder (`Auth/`, `Event/`, `Form/`, `Recruitment/`, …). Jadi ia dua kali salah tempat.

**Dependency mati:**
- `daisyui` (`package.json:18`) — **0 referensi** di `resources/js` maupun `resources/css`. Sementara proyek memakai shadcn-vue. Dua sistem desain dipasang, satu tidak dipakai.
- `laravel/mcp` (`composer.json:16`) — **0 referensi** di `app/`; `routes/ai.php:5` seluruhnya dikomentari. Paket PHP terpasang untuk fitur yang tidak ada.

**Tabel mati:**
- `recruitment_email_templates` — dibuat di `database/migrations/2026_09_07_000001_create_recruitment_foundation_tables.php`, **tidak punya model** (ada 16 model recruitment untuk 17 tabel), dan **tidak disebut di mana pun** di `app/` maupun `database/`. Tabel tanpa pembaca.

**Route mati:**
- `routes/ai.php` — 100% komentar.
- `routes/web.php` — ~40 baris route lama (auth, dashboard, resource event) yang seluruhnya dikomentari, plus deklarasi `use Symfony\Component\Finder`.

### P1-5. Lima implementasi formatter tanggal (dan lebih)

Ini contoh paling bersih dari masalah "tidak reusable":

| Implementasi | Lokasi |
|---|---|
| `formatDate` | `lib/dummyData.ts:357` |
| `formatDateTime` | `lib/dummyData.ts:361` |
| `formatSubmissionDate` | `lib/formSubmissionsUi.ts:1` |
| `formatIdDateLabel` | `lib/shadcnDateFormat.ts:9` |
| `formatIdDateTimeLabel` | `lib/shadcnDateFormat.ts:19` |
| `registrantRelativeTimeId` | `lib/registrantsUi.ts:57` |

**Enam fungsi**, tersebar di **empat file**, untuk satu domain masalah: memformat tanggal/waktu ke locale Indonesia. Ditambah `pad2` di `lib/shadcnDateFormat.ts:4` yang di-reimplementasi manual padahal `Intl`/`date-fns` ada.

Ini persis yang user maksud dengan "tidak reusable": setiap fitur baru punya peluang 1/4 untuk memilih file yang salah, dan tidak ada cara untuk tahu mana yang kanonik.

### P1-6. 4 composable yang terikat satu halaman

| Composable | LOC | Pemakai |
|---|---|---|
| `useGlobalQrScanPage` | **896** | 1 |
| `useFormSubmissionsPage` | 270 | 1 |
| `useFormBuilderDemoPage` | 235 | 1 |
| `useDashboardEventShowPage` | 108 | 1 |
| **Total** | **1.509** | — |

Sementara yang benar-benar reusable justru bagus: `useAuth` (7 LOC, 11 pemakai), `useDashboardTopbar` (31 LOC, 29 pemakai), `usePageFlashToast` (17 LOC, 4 pemakai).

**Pola masalahnya jelas:** begitu sebuah composable diberi nama `use<NamaHalaman>Page`, ia berhenti menjadi composable dan menjadi **file halaman yang kebetulan berekstensi `.ts`**. `useGlobalQrScanPage.ts` = 896 LOC dalam satu file adalah yang terburuk di repo, dan ia mengurus state + HTTP + parsing + navigasi + UI sekaligus (lihat `lib/qrScanUi.ts:38,121,151` yang dipanggil darinya, plus `utils/composables/useGlobalQrScanPage.ts` sebagai pemanggil tunggalnya).

Konsekuensinya: logika scan QR global tidak bisa dipakai ulang di halaman lain tanpa memindahkan seluruh file, dan tidak ada test unit yang mungkin dibuat untuk 896 LOC yang mencampur DOM + HTTP.

### P1-7. Fragmentasi `lib/`

`lib/` = 22 file / 2.424 LOC. **8 file di bawah 40 LOC:**

| File | LOC |
|---|---|
| `eventValidationToast.ts` | 1 (mati) |
| `eventShowUi.ts` | 5 |
| `utils.ts` | 7 |
| `formCheckboxAnswers.ts` | 12 |
| `eventCategories.ts` | 16 |
| `eventBannerAspect.ts` | 24 |
| `userAvatarFallback.ts` | 35 |
| `formFieldMetadata.ts` | 36 |

Ini bukan "modular", ini **atomisasi**. `lib/eventShowUi.ts` (5 LOC) berisi satu fungsi `parseEventCategories` yang secara konseptual milik `lib/eventCategories.ts` (16 LOC) — tapi keduanya file terpisah. Setiap file kecil berarti satu entri tambahan yang harus dicari, diimpor, dan di-review, tanpa manfaat isolasi nyata.

---

## 5. P2 — Kebersihan & Konsistensi

### P2-1. `.gitignore` copy-paste 4×
Baris **14–27** mengulang blok `/.cursor`, `/.cursorignore`, `/.cursorrules`, `/.cursorhistory` **empat kali**. Blok `frankenphp`/`caddy` juga muncul dua kali (baris **40–42** dan **51–53**). Ini file 59 baris, jadi sekitar 25% isinya duplikat.

### P2-2. Sinyal package manager bercabang tiga
- `package-lock.json` — tracked, update terakhir 14 Sep.
- `bunfig.toml` — tracked, dan isinya justru `lockfile = ["package-lock.json"]` (bun sengaja menulis ke lockfile npm).
- `bun.lock` — **stray di disk** (1 Mei), untracked, sudah masuk `.gitignore:44`.
- README baris 26–27 menyebut npm 11+; `composer.json:54-55` memakai `npm install` / `npm run build`; CI memakai `npm install`.

**Verdict:** bukan konflik fatal seperti dugaan awal — `bunfig.toml` justru membuat bun kompatibel dengan npm. Tapi `bun.lock` sisa di disk tetap membingungkan, dan tidak ada satu dokumen yang menyatakan "npm adalah kanonik". Putuskan, tulis di README, hapus stray.

### P2-3. Profanity di pipeline produksi
`Jenkinsfile:174`:
```
echo 'D-Form deployment successful. PUKIMAK KAU....'
```
Ini tercetak di log build produksi. Tidak ada nilai teknisnya, dan log build bisa dibaca orang luar tim.

### P2-4. Tooling formatting: 4 file konfigurasi, 0 penegakan
- `.editorconfig` → `indent_size = 4`, `indent_style = space`.
- `.prettierrc` → `tabWidth: 4`, `printWidth: 120`, `singleQuote: true`, `trailingComma: "es5"`.
- `pint.json` → `{"preset": "psr12"}` (bukan `laravel`, yang merupakan default konvensi Laravel).
- `.github/workflows/code_formatting.yml` → menjalankan `pint --test` dan `prettier --check .`, **keduanya `continue-on-error: true`**.
- `package.json` **tidak punya** script `format`. Hanya `lint` dan `lint:fix`.
- Tidak ada git hook (`ls .git/hooks` → tidak ada custom hook).

Konfigurasinya konsisten satu sama lain — itu bagus. Masalahnya: **tidak ada yang menegakkannya.** Menjalankan formatter sepenuhnya bergantung pada ingatan manusia.

### P2-5. `components/ui` vs `components/core` — dua library komponen
- `components/ui/`: 202 file, 4.928 LOC (vendored shadcn-vue, style "new-york").
- `components/core/`: 11 file, 324 LOC — overlay buatan sendiri (`AuthSubmitBtn`, `AuthInput`, `TextInput`, `PasswordInput`, `EmailInput`, `AuthField`, `ConfirmationModal`, `LocalLottie`) diimpor oleh **25 file** (modul auth, landing, builder demo, dashboard).

Duplikasi nyata: pemakaian import path `components/ui/button` = **107** vs `components/core/button` = **3**; `components/ui/input` = **31** vs `components/core/input` = **1**. Jadi `core/button` dan `core/input` praktis sisa sejarah — 4 pemakai total. `AuthInput.vue` (19 LOC) nol pemakai.

`components/core` **bukan** kode mati (25 file memakainya, dan `LocalLottie` itu legit). Tapi folder ini adalah lapisan kedua yang tidak punya batas tanggung jawab yang jelas terhadap `components/ui`.

Masalah terkait: `components.json` (konfigurasi shadcn CLI) baris 20 menunjuk alias `"composables": "@/lib/composables"` — **path itu tidak ada**. Composable sebenarnya di `resources/js/utils/composables/`. Jadi kalau ada yang pakai shadcn CLI untuk menambah composable, ia akan membuat folder baru yang salah.

### P2-6. Komponen UI tanpa pemakai eksternal
Delapan folder di `components/ui/` **tidak diimpor sama sekali dari luar `components/ui/`**: `breadcrumb`, `calendar`, `native-select`, `pagination`, `popover`, `scroll-area`, `select`, `skeleton`.
Hampir mati (1–2 pemakai): `styled-select` (1), `alert-dialog` (1), `avatar` (1), `switch` (1).

**Jangan hapus dulu tanpa verifikasi:** sebagian dipakai secara transitif oleh komponen UI lain — `calendar` jelas dipakai `date-picker` (8 pemakai eksternal). Yang perlu ditelusuri adalah pemakaian transitif. Yang relatif aman dicurigai: `native-select` (0 pemakai, sementara `SimpleSelect` 10 dan `select` 0 — tiga komponen select untuk satu kebutuhan).

### P2-7. `pages/Docs.vue` = 1.072 LOC
File tunggal terbesar di frontend (di atas `useGlobalQrScanPage.ts` 896 dan `EventDashboardForm.vue` 880). Halaman dokumentasi 1.072 LOC biasanya berarti konten ditulis inline di dalam template, bukan dari data. Tidak diverifikasi lebih jauh, tapi ukurannya sendiri adalah sinyal.

### P2-8. Dokumentasi terorganisir per orang
`docs/` = **41 file tracked**, **67 file di disk** (selisih 26 = `docs/superpowers/**`).

Dua masalah:

1. **26 dokumen desain justru di-ignore.** `.gitignore:55` mengabaikan `docs/superpowers/`, tapi folder itu berisi 13 plan dan 13 spec — `2026-09-15-oprec-period-detail-redesign-design.md`, `2026-09-16-oprec-detail-query-tabs-design.md`, dst. Artinya **setiap keputusan desain fitur hanya hidup di satu mesin**. Ini kehilangan pengetahuan yang sistematis: clone baru tidak akan pernah tahu *kenapa* sesuatu dirancang begitu.
2. **Struktur per orang:** `docs/developer/nafan/` (5 file change-log bertanggal) dan `docs/developer/sapto/` (6 file + `requires/`). Folder dinamai manusia, bukan topik. Begitu ada anggota ketiga, struktur ini berhenti berguna.

Kredit: **tidak ada link README yang mati** — `docs/rules/front-end.md`, `docs/rules/back-end.md`, `docs/rules/general.md`, `docs/prd.md`, `docs/milestone.md`, `docs/01-installation.md`, `docs/02-directory-structure.md` semuanya ada. Itu lebih baik dari rata-rata.

Kekurangan lain: `docs/module/PRD — DOSCOM OpenRecruitment (OpRec).md` memakai em-dash dan spasi di nama file — rapuh untuk link, script, dan tooling.

### P2-9. Tiga toolchain container
- `Makefile:6,9-11` → `podman compose`.
- `Jenkinsfile:14` + `docker-compose.prod.yml` → `docker compose`.
- `docker-compose.yml` (dev) dan `docker-compose.prod.yml` (prod) duplikat sebagian.

`make run` juga menjalankan `migrate:fresh --force` + `db:seed --force` — **menghapus seluruh DB** setiap kali dijalankan. Sebagai default goal (`Makefile:1`) itu jebakan untuk orang yang baru clone.

---

## 6. Metrik Repo

| Wilayah | File | LOC |
|---|---|---|
| `resources/js` (non-generated) | 426 | 39.700 |
| `resources/js/actions` (generated, **16 pemakai**) | 75 | 6.872 |
| `resources/js/routes` (generated, **0 pemakai**) | 54 | 7.308 |
| `resources/js/wayfinder` (generated, helper runtime) | 1 | 167 |
| `app/` (PHP) | 252 | 20.794 |
| `tests/` (PHP) | 52 | 11.591 |
| `routes/` (PHP) | 10 | 632 |
| Dokumen (tracked / di disk) | 41 / 67 | — |

| Metrik | Nilai |
|---|---|
| Test method | 404 |
| Test di-skip | 0 |
| Route bernama | 114 |
| Definisi route | 115 |
| Tabel database | 32 |
| Model | 23 (7 root + 16 recruitment) |
| FormRequest class | 41 |
| Controller memakai FormRequest | 25 dari 55 |
| Controller dengan `->validate()` inline | 3 |
| `: any` / `as any` | **0 / 0** |
| `@ts-expect-error` / `eslint-disable` | 1 / 2 |
| Env key dipakai config tapi tak ada di `.env.example` | 193 |
| Ukuran lokal (`storage/` / `node_modules/` / `graphify-out/` / `.playwright-mcp/`) | 364M / 533M / 66M / 1,6M |

---

## 7. Yang Sudah Benar (jangan dirusak)

Supaya laporan ini kredibel, ini yang **tidak** perlu diperbaiki:

- **Test suite nyata dan luas.** 404 test method, 0 skipped, terkonsentrasi di modul paling berisiko (Recruitment 16 file, Forms 6, Scan 5, plus 4 Unit + 2 Unit/Support). Ini aset terbesar proyek, dan ironisnya yang paling tidak dimanfaatkan.
- **Disiplin TypeScript benar-benar ada.** Nol `any` di 39.700 LOC. Itu pencapaian, bukan kebetulan.
- **Tidak ada controller yang tidak dirutekan.** Semua 55 controller terpakai. Tidak ada service mati kecuali satu. Tidak ada enum mati. Tidak ada job mati.
- **Validasi tidak tersebar.** 41 FormRequest, hanya 3 controller pakai `->validate()` inline — pola konsisten.
- **Tidak ada URL hardcoded bertebaran.** 68 file memakai builder dari `lib/routes.ts` alih-alih menulis string path mentah. Niatnya benar dan konsisten; yang salah hanya pilihan sumbernya (lihat P1-2).
- **Direktori generated sudah benar di-gitignore** (`.gitignore:46-48`) — `actions`, `routes`, `wayfinder` semuanya `git ls-files` = 0. Keputusan yang tepat.
- **`.env` tidak ter-commit.** `.gitignore:3` benar.
- **File generated tidak masuk git.** `actions/`, `routes/`, `wayfinder/` → tracked = 0. Sudah bersih.
- **Semua link README hidup.** Jarang.
- **`.editorconfig`, `.prettierrc`, dan praktik kode saling konsisten** (indent 4, single quote). Konfigurasinya tidak saling bertentangan — hanya tidak ditegakkan.
- **OpRec adalah modul hidup, bukan bangkai.** 21 halaman, 21 file test, 20 controller, 17 model, terpasang di sidebar dengan 6 permission dinamis. Jangan sentuh sebagai "kandidat hapus".

---

## 8. Rencana Remediasi Berfase

Prinsip: **penjaga dulu, bersih-bersih kemudian.** Menghapus kode mati sebelum ada CI yang bisa memverifikasi adalah cara tercepat menciptakan insiden produksi. Dan sebaliknya, menambah CI setelah bersih-bersih berarti kita tidak pernah tahu apakah bersih-bersihnya merusak sesuatu.

### Fase 0 — Penghentian pendarahan (≤ 1 hari, tanpa menyentuh fitur)

| # | Aksi | Kriteria selesai |
|---|---|---|
| 0.1 | Rotasi password DB produksi | Password baru terpasang; yang lama tidak bisa login |
| 0.2 | Purge `docker-compose.prod.yml.bak` dari history | `git log --all -- docker-compose.prod.yml.bak` kosong |
| 0.3 | Tambahkan 193 key yang hilang ke `.env.example`, tandai mana wajib vs opsional | `php artisan config:cache` jalan di clone bersih tanpa key tak terdefinisi |
| 0.4 | Hapus `Jenkinsfile:174` | Log build bersih |
| 0.5 | Hapus stray `bun.lock`, tulis "npm adalah kanonik" di README | `bun.lock` tidak ada di disk |

**Risiko:** hampir nol. Tidak ada satu baris kode aplikasi yang berubah.

### Fase 1 — Pasang penjaga (2–4 hari) ⟵ **paling penting**

| # | Aksi | Kriteria selesai |
|---|---|---|
| 1.1 | Tambah script `typecheck` (`vue-tsc --noEmit`) dan `format` di `package.json` | `npm run typecheck` lulus |
| 1.2 | Satu pipeline CI tunggal yang menjalankan: `npm run lint`, `npm run typecheck`, `npm test`, `npm run build` | Pipeline **gagal** kalau salah satu gagal |
| 1.3 | Hapus semua `continue-on-error: true` di `.github/workflows/code_formatting.yml` | Pipeline formatting bisa merah |
| 1.4 | Trigger CI di `main`, bukan hanya `dev`/`temp` | PR ke `main` menjalankan check |
| 1.5 | Tambah tahap test + lint di `Jenkinsfile` **sebelum** `Build` | Deploy dibatalkan kalau test merah |
| 1.6 | Selaraskan Node 20 → 24 (atau turunkan klaim README) | Versi di CI = versi di README |
| 1.7 | Aktifkan type-aware lint rules di `eslint.config.js` | Rule `@typescript-eslint/no-unused-vars` naik dari `warn` → `error` |

**Kriteria fase:** ada satu commit sengaja yang merusak test → pipeline merah → kamu tidak bisa deploy. Kalau itu belum terbukti, fase ini belum selesai.

**Risiko:** typecheck pertama kemungkinan besar akan **menemukan error** yang selama ini tersembunyi. Sediakan waktu untuk memperbaikinya; jangan matikan rulenya.

### Fase 2 — Buang yang mati (2–3 hari, setelah Fase 1 hijau)

Batch kecil, satu PR per kelompok, verifikasi test + build sebelum dan sesudah:

| # | Aksi | Ukuran |
|---|---|---|
| 2.1 | Hapus 7 file frontend mati | 621 LOC |
| 2.2 | Hapus `app/Services/BundleSubmissionGrouper.php` | 235 LOC |
| 2.3 | Hapus dependency `daisyui` | 1 package |
| 2.4 | Hapus dependency `laravel/mcp` + file `routes/ai.php` | 1 package |
| 2.5 | Putuskan `recruitment_email_templates`: hapus tabel atau buat modelnya | 1 tabel / 1 model |
| 2.6 | Bersihkan `routes/web.php` (40 baris route terkomentar) dan dedup `.gitignore` | 2 file |
| 2.7 | Telusuri pemakaian transitif 8 folder `components/ui` tanpa pemakai → hapus yang benar-benar mati | perlu analisis, bukan tebak-tebakan |

**Catatan:** jangan gabung 2.1 dan 2.7 dalam satu PR. Yang pertama terverifikasi, yang kedua butuh penelusuran.

### Fase 3 — Konsolidasi arsitektur (1–3 minggu)

Ini fase yang benar-benar membayar "reusability", dan harus menunggu Fase 1:

| # | Aksi | Dampak |
|---|---|---|
| 3.1 | Pindahkan closure di `routes/web/admin/index.php` ke invokable controller + service | 259 LOC route → peta saja |
| 3.2 | Pilih satu sistem route (P1-2): migrasi navigasi ke `@/routes/**` generated, **atau** hentikan generasi `routes/**`; `lib/routes.ts` menyisakan helper navigasi saja | hilangkan 253 LOC manual **atau** 7.308 LOC build waste |
| 3.3 | Pecah `lib/dummyData.ts`: fixture → `tests/fixtures/` atau `dev/`, helper format → `lib/format/date.ts` + `lib/format/labels.ts` | hilangkan kebohongan nama + pindahkan dependency produksi |
| 3.4 | Satukan 6 formatter tanggal jadi satu modul | 4 file → 1 |
| 3.5 | Pecah `useGlobalQrScanPage.ts` (896 LOC) jadi state + HTTP + parsing terpisah | bisa dites, bisa dipakai ulang |
| 3.6 | Turunkan `useFormSubmissionsPage` / `useFormBuilderDemoPage` / `useDashboardEventShowPage` ke pola composable (bukan file halaman berkedok `.ts`) | 613 LOC |
| 3.7 | Gabung 8 file `lib/` di bawah 40 LOC ke modul tematik | 22 file → ~10 |
| 3.8 | Satukan 3 komponen select (`select`/`styled-select`/`native-select`) jadi satu | hapus 2 varian |
| 3.9 | Tetapkan batas `components/core` vs `components/ui`, atau lebur `core` ke `ui` | hilangkan lapisan kedua |
| 3.10 | Perbaiki alias `components.json:20` → `@/utils/composables` | shadcn CLI bekerja benar |

**Kriteria fase:** setiap perubahan punya bukti test/build hijau **sebelum dan sesudah**, dan tidak ada PR yang menyentuh lebih dari satu baris tabel di atas.

### Fase 4 — Dokumentasi & konvensi (paralel, berkelanjutan)

| # | Aksi |
|---|---|
| 4.1 | Pindahkan `docs/superpowers/**` keluar dari `.gitignore` — 26 dokumen desain harus masuk repo, minimal ke `docs/decisions/` |
| 4.2 | Ganti struktur `docs/developer/<nama>/` jadi `docs/decisions/YYYY-MM-DD-<topik>.md` |
| 4.3 | Rename `docs/module/PRD — DOSCOM OpenRecruitment (OpRec).md` (tanpa em-dash/spasi) |
| 4.4 | Tulis satu `docs/CONVENTIONS.md`: npm kanonik, satu sistem route, satu formatter tanggal, satu pipeline CI, "tidak ada business logic di route file" |
| 4.5 | Tulis satu `docs/adr/` untuk keputusan yang sudah diambil di atas (Wayfinder, shadcn, struktur service) supaya tidak diperdebatkan ulang |

---

## 9. Ringkasan Prioritas

**Kalau hanya boleh mengerjakan tiga hal:**
1. Rotasi password + purge `.bak` (P0-1).
2. Satu CI yang benar-benar bisa gagal dan menjalankan test + typecheck (P0-2, P0-4).
3. Lengkapi `.env.example` (P0-3).

**Kalau punya satu minggu lagi:**
4. Pindahkan business logic dari `routes/web/admin/index.php` ke controller.
5. Pilih satu sistem route; pensiunkan `lib/routes.ts`.
6. Buang 856 LOC kode mati + 2 dependency mati.

**Kalau punya satu bulan:**
7. Pecah `lib/dummyData.ts`, satukan formatter tanggal, pecah `useGlobalQrScanPage.ts`.
8. Selamatkan 26 dokumen desain dari `.gitignore`.

**Satu kalimat:** repo ini punya tulang yang layak — test nyata, TypeScript disiplin, service layer yang dipahami — tapi tidak punya satu pun penjaga yang menegakkannya, dan tambalan sementara (alias mati, duplikat manual, folder per orang, `continue-on-error`) sudah mengeras menjadi struktur. Perbaiki penjaganya dulu; berkas yang mati akan lebih mudah dibersihkan ketika mesinnya sudah bisa memberitahu kalau kamu salah.
