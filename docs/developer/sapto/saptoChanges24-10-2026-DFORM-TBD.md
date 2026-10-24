# Sapto Changes — 24 Oktober 2026 (DFORM-TBD)

Optimasi halaman auth berdasarkan audit `docs/big-changes/auth/report.md`: bundle per halaman, vendor berat keluar dari entry, lottie lazy, rate limit login/register, handler 429 di frontend, serta aset font/favicon.

> **Nomor tiket:** `DFORM-TBD` — belum ada tiket Jira saat dikerjakan. Ganti `<N>` pada nama file dan baris ini sebelum push/PR.

| Kolom   | Isi                                                                 |
| ------- | ------------------------------------------------------------------- |
| Issue   | DFORM-TBD (belum dibuat)                                            |
| Commit  | `258c421` — `perf(auth): pecah bundle per halaman dan batasi laju login/register` |
| Branch  | `dev-zapp`                                                          |
| Dasar   | `docs/big-changes/auth/report.md` + `plan-optimasi-auth.md` (ignored) |

---

## Ringkasan (TL;DR)

| Area                    | Sebelum                                  | Sesudah                                        | Δ              |
| ----------------------- | ---------------------------------------- | ---------------------------------------------- | -------------- |
| Entry JS (gzip)         | 886.150 B                                | **18.230 B**                                   | **−97,9%**     |
| Entry JS (raw)          | 3.437.979 B                              | 58.090 B                                       | −98,3%         |
| Chunk `Auth/Login`      | tidak ada (semua di entry)               | 536 B gzip                                     | baru           |
| Jumlah chunk JS         | 1                                        | 205 file, 59 dynamic entry                     | route-level    |
| Payload login (app)     | ±1.033 kB                                | **356 kB** desktop / 254 kB mobile             | **−65%**       |
| DCL login (median)      | 569 ms                                   | **244 ms**                                     | −57%           |
| Load login (median)     | 618 ms                                   | **251 ms**                                     | −59%           |
| FCP login (median)      | 236 ms                                   | 224 ms                                         | ≈0 (CSS-bound)  |
| Throttle login/register | tidak ada                                | 5/menit (email+IP) · 3/menit (IP)              | baru           |
| 429 Inertia             | header `Retry-After` hilang              | header disalin + toast hitung mundur           | fix            |
| Favicon                 | 370.070 B                                | 7.406 B (ICO 16/32/48) + PNG 192 + apple-touch | −98%           |
| SEO & meta auth         | tanpa `<title>`, `description`, `noindex` | title + description + `noindex, nofollow` di 4 halaman auth | +UX & crawl |
| SSR Inertia             | HTML mentah tanpa title/meta/OG/JSON-LD | dirender server (`bootstrap/ssr`) di container app, fallback CSR tetap jalan | +SEO |
| Semantik HTML5          | logo 404, 2 `<main>` di docs, auth tanpa `<main>`, 15 heading skip | semua diperbaiki, skip-link + label pencarian ditambah | +a11y/SEO |
| Google Fonts            | `@import` di baris pertama CSS           | `<link>` + `preconnect` di root view           | non-blocking   |

Angka diukur di localhost tanpa throttle jaringan dan tanpa concurrency, dengan `APP_DEBUG=true` (Debugbar aktif ±490 kB, tidak dihitung di "payload app"). Jangan diekstrapolasi ke 3G/4G tanpa menyebutnya estimasi.

---

## 1. T1 — Code splitting level route

| File                    | Perubahan                                                                                                                        |
| ----------------------- | -------------------------------------------------------------------------------------------------------------------------------- |
| `resources/js/app.js`   | `import.meta.glob('./pages/**/*.vue')` dipindah ke module scope tanpa `eager`; resolver mengembalikan promise dan melempar error jelas bila page tidak ada |

**Keputusan:** dynamic import di resolver (pola resmi Inertia untuk Vue) dipilih daripada 57 wrapper `defineAsyncComponent`. Satu titik perubahan, tidak ada duplikasi, dan tidak mengubah kontrak `createInertiaApp`.

**Bukti:** `public/build/manifest.json` naik dari 3 entri menjadi 210 entri (59 `isDynamicEntry`); tiap page punya file sendiri.

## 2. T2 — Vendor berat hanya di chunk pemakainya

| Vendor              | Lokasi                                          | Tindakan                                                            |
| ------------------- | ----------------------------------------------- | ------------------------------------------------------------------- |
| `vue3-lottie`       | `components/core/LocalLottie.vue:3` (terlarang) | `defineAsyncComponent(() => import('vue3-lottie')...)`              |
| `html5-qrcode`      | `utils/composables/useGlobalQrScanPage.ts:4`    | `import type` + `loadHtml5Qrcode()` yang `await import()` saat start |
| Tiptap / ProseMirror | `modules/dashboard/**` (bukan lokasi terlarang) | tidak diubah — sudah terisolasi ke chunk page setelah T1            |
| `chart.js`          | `modules/dashboard/*Chart.vue`                  | tidak diubah — hanya dipakai `pages/Dashboard/Index.vue`            |
| `xlsx`              | tidak terpasang                                 | tidak ada pekerjaan; hanya string format ekspor                     |

**Keputusan:** aturan melarang impor statis dari `app.js`, `bootstrap.js`, layout, dan `components/core/`. Hanya lottie yang melanggar; Tiptap/chart berada di `components/modules/` sehingga async tambahan hanya menambah state loading tanpa manfaat untuk first paint auth. Alasan ini dicatat sebagai penolakan opsi "semua vendor async".

**Bukti:** `grep -c` pada entry final → `tiptap 0`, `prosemirror 0`, `html5-qrcode 0`, `vue3-lottie 0`, `chart.js 0`. Chunk terbesar yang tersisa: Tiptap 134,4 kB gzip, html5-qrcode 99,8 kB gzip, lottie 80,8 kB gzip — semuanya lazy.

## 3. T3 — manualChunks vendor bersama

`vite.config.js`: `build.rollupOptions.output.manualChunks` = `vendor-vue` (34,6 kB gzip), `vendor-inertia` (61,1 kB), `vendor-ui` (54,8 kB). Tidak ada pemecahan per page.

**Keputusan:** bentuk objek dipilih (deterministik, mudah diaudit) daripada fungsi `id =>`. Usulan reviewer menggabungkan `vue` + `@inertiajs/vue3` menjadi satu chunk **ditolak** karena instruksi tiket menetapkan tiga chunk tersebut; penggabungan dicatat sebagai follow-up.

## 4. T4 — Lottie lazy di halaman auth

- `AuthIllustration.vue:23`: `:lazy="false"` dihapus, animasi dimuat lewat IntersectionObserver **setelah** form tampil.
- Placeholder: root `LocalLottie` sudah memakai `sizeStyle` (260 px); wrapper `height: 260px` menjaga ruang.
- **Bukti no-CLS & laziness:** viewport mobile 390×844 → `lottie: false` (tidak ada request `auth-secure.json`/`vue3-lottie`), payload 254 kB; desktop 1280×900 → lottie dimuat setelah mount, FCP tetap 224–260 ms.

## 5. T5 — Rate limit login & register

| File                            | Perubahan                                                                                                                                                                                 |
| ------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `config/auth_throttle.php`      | baru — limit dari env: `AUTH_LOGIN_*`, `AUTH_REGISTER_*`, `AUTH_PASSWORD_{EMAIL,LINK,RESET}_*` (default 5/1, 3/1, 5/1)                                                                    |
| `app/Providers/AppServiceProvider.php` | 5 named limiter: `auth-login` (email+IP), `auth-register` (IP), `auth-password-email`/`-link`/`-reset` (IP); helper `authThrottleLimit()`                                                                 |
| `routes/web/auth.php`           | login/register dapat throttle pertama kali; `forgot-password` & `password-reset-link` dipisah ke kunci berbeda                                                                             |
| `bootstrap/app.php`             | response Inertia 429 menyalin `Retry-After` + `X-RateLimit-*` dari response asli (sebelumnya dibuang karena render ulang halaman `Error`)                                                  |
| `tests/Feature/Auth/AuthThrottleTest.php` | 6 test: limit login, isolasi per email, register, bucket password terpisah, header pada 429 HTML, `email[]=` bukan 500                                                                            |

**Keputusan:** memakai `RateLimiter` + alias `throttle` yang sudah menjadi layer lintas-pakai. Middleware limiter baru ditolak karena menduplikasi mekanisme framework. Angka di config/env, bukan magic number di route.

**Perbaikan dari security review:** `(string) $request->input('email')` pada array memicu `E_WARNING` → 500 dan tidak tercatat limiter. Sekarang `is_string()` guard; request `email[]=` menjadi 302 validasi, bukan 500 (ada test regresi).

**Tidak dikerjakan (keputusan user):** limit agregat per-IP untuk login (anti password-spraying). Dicatat sebagai rekomendasi §9.

## 6. T6 — Handler 429 di frontend

| File                                            | Perubahan                                                                                     |
| ----------------------------------------------- | --------------------------------------------------------------------------------------------- |
| `resources/js/lib/http-retry-after.ts`          | baru — `parseRetryAfterSeconds()` + `formatRetryAfterMessage()` (murni)                        |
| `resources/js/hooks/useGlobalHttpStatusToast.ts` | baru — satu listener `router.on('invalid')` untuk 419/429/≥500, `preventDefault()`, toast 429 dengan hitung mundur |
| `resources/js/app.js`                           | handler inline dihapus, hook dipanggil sekali sebelum `createInertiaApp`                       |

Pesan 429: `Terlalu banyak percobaan. Coba lagi dalam 58 detik.`; tanpa header → `Terlalu banyak percobaan. Coba lagi sebentar lagi.`; fallback tanpa detik → `showHttpErrorToast(429)`.

**Keputusan:** parsing header = fungsi murni di `lib/`, registrasi listener = hook di `hooks/` (folder target `AGENTS.md`, sebelumnya belum ada). Pembuatan folder `hooks/` dicatat sebagai folder baru resmi.

## 7. T7 — Fonts & favicon

- `resources/css/app.css`: `@import` Google Fonts dihapus (baris 1).
- `resources/views/app.blade.php`: `preconnect` ×2 + stylesheet Poppins (query string identik) + `rel="icon"` (ICO & PNG 192) + `apple-touch-icon`.
- `public/favicon.ico`: 370.070 B → **7.406 B** (ICO 16/32/48); baru `favicon-192.png` (28.181 B) dan `apple-touch-icon.png` (180×180, 14.002 B). `favicon.ico.bak` (0 byte) dihapus.
- Sumber: `public/DForm 1.png` (wordmark "DForm v2" 113×49). Wordmark utuh tidak terbaca di 16 px, jadi glyph mark di-crop dan ditaruh di kanvas persegi — tanpa artwork/ warna baru.

## 8. T8 — Debugbar (verifikasi saja)

- `config/debugbar.php:15` → `env('DEBUGBAR_ENABLED')` (null = ikut `APP_DEBUG`).
- `.env.example:4` → `APP_DEBUG=true` (contoh lokal), tidak ada `DEBUGBAR_ENABLED`.
- Dampak lokal terukur: Debugbar menambah ±490 kB per halaman dan pada load 30 rps menyebabkan `Allowed memory size of 268435456 bytes exhausted` (2 dari 600 request). **Tidak ada file `.env`/config yang diubah**; produksi memakai `APP_DEBUG=false` (entrypoint `Dockerfile.prod`) sehingga Debugbar mati.

---

## 9. Pengukuran before/after (build produksi, localhost)

Metode sama dengan audit §7.4: `public/hot` dipindah sementara agar Laravel menyajikan `public/build`, diukur dengan Chromium, lalu `public/hot` dipulihkan (trap `EXIT`, terverifikasi).

| Metrik                  | Sebelum (audit) | Sesudah run 1–3          |
| ----------------------- | --------------- | ------------------------ |
| Entry JS gzip           | 886.150 B       | 18.230 B                 |
| TTFB                    | 19–25 ms        | 15 / 15 / 15 ms          |
| FCP                     | 236–260 ms      | 224 / 260 / 224 ms       |
| DCL                     | 569–591 ms      | 244 / 277 / 241 ms       |
| Load                    | 618–635 ms      | 251 / 287 / 251 ms       |
| Payload app (tanpa Debugbar) | ±1.033 kB  | 356 kB                   |
| Request app             | 10              | 30 (chunk lebih kecil)   |
| Mobile 390×844          | —               | 254 kB, lottie tidak dimuat, FCP 228 / DCL 250 / load 258 ms |

**Budget:** entry 18,2 kB gzip ≤ 350 kB ✔; chunk page terbesar (`Dashboard/Index`) 63,9 kB gzip ≤ 200 kB ✔. Chunk lazy terbesar (Tiptap) 134,4 kB gzip, masih di bawah 200 kB.

Catatan jujur: FCP hampir tidak berubah karena ditentukan HTML+CSS (44 kB), bukan JS; perbaikan besar ada di DCL/load dan byte yang diunduh.

## 10. Stress test k6

Skrip baru `scripts/stress/k6/auth-throttle.js` (login flood + register flood, CSRF per iterasi, `redirects: 0`) — **tidak ikut commit** karena `scripts/stress` ada di `.gitignore`; ini artefak harness lokal seperti skrip k6 lain di folder yang sama. k6 v2.3.0 (dipasang di `~/.local/bin`, bukan paket sistem).

| Run                          | Hasil                                                                                                                                                                              |
| ---------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| 20 rps login + 10 rps register, 30 s, 150 VU | 600 iterasi / 1.200 request; **97,83% 429**, 0,33% 5xx (2× memory_limit 256 MB di worker lokal dengan Debugbar), p95 5,52 s, 301 dropped |
| 5 rps login + 3 rps register, 15 s           | median POST 33 ms (login) / 36 ms (register), p95 272 ms, tanpa 5xx                                                                  |
| CPU container                                | 18,3–19,3% saat flood vs **19,3% idle** — tidak ada kenaikan terukur; baseline didominasi Vite `usePolling`, jadi angka ini bukan batas CPU aplikasi |

Kesimpulan jujur: limiter bekerja (429 dominan, tanpa lonjakan CPU), tetapi kapasitas lokal ±30 rps dengan Debugbar aktif; angka produksi harus diukur ulang tanpa Debugbar. 5xx berasal dari `memory_limit`, bukan dari logika throttle.

## 11. Verifikasi

| Cek                                                       | Hasil                                                                    |
| --------------------------------------------------------- | ------------------------------------------------------------------------ |
| E2E Playwright (`tests/e2e/auth.spec.ts`)                 | **8/8 lulus** (sebelum implementasi: 3 lulus, 5 merah)                    |
| Test backend `AuthThrottleTest`                           | 6 lulus, 44 assertion                                                    |
| Test backend `tests/Feature/Auth`                         | 22 lulus, 120 assertion                                                  |
| Suite penuh (`php artisan test`)                          | 665 lulus, 36 skipped, 11 gagal — semuanya `EventListPaginationTest` (WIP lain: `EventService::paginateForPublicList()`/`SEARCH_MAX_PAGE` belum ada), bukan area auth |
| `php artisan test --filter='^(?!.*EventListPagination)'`  | 665 lulus, 36 skipped, 0 gagal                                           |
| Pint (`--test`, file yang disentuh)                       | PASS                                                                     |
| ESLint (`npm run lint`)                                   | 0 error, 5 warning lama di file yang tidak disentuh                      |
| `npm run build`                                           | sukses, 205 chunk, entry 58.090 B raw / 18.230 B gzip                    |
| `BCRYPT_ROUNDS`, Timebox, query, index, migrasi            | tidak berubah (diff hanya config/limit/route/exception/asset/frontend)     |

Perbaikan state test: bucket limiter di-reset deterministik lewat `RateLimiter::clear(md5('<limiter>'.'127.0.0.1'))` di `tests/e2e/support/artisan.ts`, jadi tidak ada lagi `test.skip` karena bucket "hangat".

## 12. Ditunda / butuh keputusan

| #   | Item                                                                                                    | Alasan                                                                 |
| --- | ------------------------------------------------------------------------------------------------------- | ---------------------------------------------------------------------- |
| 1   | Limit agregat per-IP untuk login (anti password spraying)                                               | Di luar spec tiket; user memilih tetap `email+IP` 5/menit               |
| 2   | `trustProxies` / IP asli di belakang LB/CDN                                                             | Deployment-dependent; tanpa itu semua user berbagi satu bucket IP       |
| 3   | Rotasi IPv6 /64 melewati limiter per-IP                                                                 | Butuh normalisasi prefix; belum ada bukti trafik IPv6                   |
| 4   | `STRESS_BYPASS_THROTTLE` memakai `!App::isProduction()` (staging bisa lolos)                             | Pre-existing; disarankan allow-list `local`/`testing`                   |
| 5   | Konsolidasi `useGlobalHttpStatusToast` + `useHttpErrorToast`                                            | Event source berbeda (invalid vs Error page POST); butuh perubahan layout dashboard |
| 6   | Pesan `email.unique` register membocorkan status akun                                                   | Perubahan perilaku/produk — hanya dicatat, tidak dikerjakan             |
| 7   | E2E login sukses bergantung pada user stress `stress-user-1@stress.test` (dataset tidak di-commit)      | Butuh seeder fixture untuk CI                                           |
| 8   | Debugbar `memory_limit` pada load lokal                                                                  | Lingkungan; ukur ulang dengan `APP_DEBUG=false`                          |

## 13. File yang berubah

**Backend:** `config/auth_throttle.php` (baru), `app/Providers/AppServiceProvider.php`, `routes/web/auth.php`, `bootstrap/app.php`, `tests/Feature/Auth/AuthThrottleTest.php` (baru).

**Frontend:** `resources/js/app.js`, `resources/js/hooks/useGlobalHttpStatusToast.ts` (baru), `resources/js/lib/http-retry-after.ts` (baru), `resources/js/components/core/LocalLottie.vue`, `resources/js/components/modules/auth/AuthIllustration.vue`, `resources/js/utils/composables/useGlobalQrScanPage.ts`.

**Build & aset:** `vite.config.js` (blok `build`), `resources/css/app.css`, `resources/views/app.blade.php`, `public/favicon.ico`, `public/favicon-192.png` (baru), `public/apple-touch-icon.png` (baru), `public/favicon.ico.bak` (dihapus, 0 byte).

**SEO:** `resources/js/components/seo/SeoHead.vue`, `resources/js/pages/Auth/{Login,Register,ForgotPassword,ResetPassword}.vue`.

**SSR:** `resources/js/ssr.js` (baru), `vite.config.js`, `package.json`, `docker/Dockerfile.prod`, `docker-compose.prod.yml`, `.gitignore`.

**Semantik:** `resources/js/layouts/{LandingLayout,AuthLayout}.vue`, `resources/js/pages/Docs.vue`, `resources/js/components/modules/landing/{Navbar,Footer}.vue`, `resources/js/components/modules/landing/home/HomeShowcase.vue`, `resources/js/components/modules/landing/features/FeaturesIntegrations.vue`, `resources/js/components/modules/landing/events/EventList.vue`.

**Test & harness:** `playwright.config.ts` (baru), `tests/e2e/auth.spec.ts` (baru), `tests/e2e/support/artisan.ts` (baru), `package.json`/`package-lock.json` (devDependency `@playwright/test` + script `test:e2e` yang sudah ada di working tree sebelum tiket ini), `scripts/stress/k6/auth-throttle.js` (baru, ignored).

## 14. Follow-up: SEO & meta halaman auth

Halaman auth sebelumnya tidak punya `<title>`, `description`, maupun penanda robots — tab browser hanya menampilkan URL dan crawler mengandalkan header `X-Robots-Tag` saja.

| File                                        | Perubahan                                                                 |
| ------------------------------------------- | ------------------------------------------------------------------------- |
| `resources/js/components/seo/SeoHead.vue`   | prop opsional `noindex` (default `false`): render `<meta name="robots" content="noindex, nofollow">` dan **tidak** merender canonical (canonical pada halaman noindex saling bertentangan) |
| `resources/js/pages/Auth/Login.vue`         | `SeoHead title="Masuk"` + description + `/auth/login` + `noindex`          |
| `resources/js/pages/Auth/Register.vue`      | `SeoHead title="Daftar"` + description + `/auth/register` + `noindex`      |
| `resources/js/pages/Auth/ForgotPassword.vue`| `SeoHead title="Lupa kata sandi"` + description + `noindex`                |
| `resources/js/pages/Auth/ResetPassword.vue` | `SeoHead title="Atur ulang kata sandi"` + description + `noindex`          |

**Bukti runtime (Chromium):**

| URL                        | `document.title`                     | robots                 | canonical |
| -------------------------- | ------------------------------------ | ---------------------- | --------- |
| `/auth/login`              | `Masuk · DForm`                      | `noindex, nofollow`    | —         |
| `/auth/register`           | `Daftar · DForm`                     | `noindex, nofollow`    | —         |
| `/auth/forgot-password`    | `Lupa kata sandi · DForm`            | `noindex, nofollow`    | —         |
| `/auth/reset-password/{t}` | `Atur ulang kata sandi · DForm`      | `noindex, nofollow`    | —         |
| `/` (regresi)              | `Kelola acara & formulir… · DForm`   | —                      | tetap ada |

**Tidak diubah:** `SitemapController` sudah hanya memuat `/`, `/events`, `/features`, `/docs`, dan detail event published — halaman auth tidak pernah masuk sitemap. Header `X-Robots-Tag` dari `AddRobotsTagForPrivateAreas` tetap berlaku sebagai lapisan kedua.

**Sisa peluang performa (perlu keputusan produk/desain, tidak dikerjakan):** animasi lottie auth ±99 kB (chunk 80,8 kB + JSON 18,7 kB) untuk elemen dekoratif; Google Fonts masih dari domain pihak ketiga (bisa self-host untuk menghapus 2 RTT). Sisanya sudah batas wajar: `vendor-inertia` 64,8 kB, `vendor-ui` 57 kB, CSS 43,8 kB.

## 15. Follow-up: SSR Inertia (server-rendered HTML)

Temuan audit: HTML dari server berisi **0 `<title>`, 0 meta description, 0 OG, 0 JSON-LD** — semua meta dibuat JS. Sekarang dirender di server.

| File                                   | Perubahan                                                                                                                                    |
| -------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------- |
| `resources/js/ssr.js` (baru)           | entry SSR: `createServer` + `createInertiaApp({ page, render: renderToString, resolve, setup })`; glob page lazy; directive `focus` versi `getSSRProps` (tanpa DOM) karena dipakai input inti |
| `vite.config.js`                       | `laravel({ ssr: 'resources/js/ssr.js' })` + `ssr.noExternal: true` (bundle self-contained; tanpa ini build SSR gagal karena `manualChunks`)  |
| `package.json`                         | script `build:ssr` (`vite build --ssr`)                                                                                                      |
| `docker/Dockerfile.prod`               | build client + SSR, lalu `rm -rf node_modules` (bundle terbukti jalan tanpa `node_modules`)                                                   |
| `docker/entrypoint.prod.sh`            | menjalankan `node bootstrap/ssr/ssr.js` di background dengan loop restart (2 dtk); log ke stdout container. Tidak menambah service/container baru |
| `.gitignore`                           | `/bootstrap/ssr`                                                                                                                              |

**Bukti HTML mentah (curl, proses SSR hidup):**

| Path                       | title | description | og:title | JSON-LD | canonical |
| -------------------------- | ----: | ----------: | -------: | ------: | --------: |
| `/`                        | 1     | 1           | 1        | 1       | 1         |
| `/features`                | 1     | 1           | 1        | 1       | 1         |
| `/events`                  | 1     | 1           | 1        | 1       | 1         |
| `/events/stress-event-839549` | 1  | 1           | 1        | 1 (`Event`) | 1     |
| `/auth/login`              | 1     | 1           | 1        | 0 (tidak ada schema) | 0 (noindex, by design) |

- Bundle SSR: `bootstrap/ssr/ssr.js` 84,43 kB + `assets/` (total 12 MB); dijalankan dari salinan tanpa `node_modules` → `{"status":"OK"}` pada `/health`.
- Fallback: proses node dimatikan → semua halaman tetap **200**, `title=0` di HTML mentah, client merender normal (`HttpGateway` mengembalikan null saat koneksi gagal).
- Supervisor: `kill` pada proses node → SSR hidup lagi ≤6 dtk (`title` kembali 1); setelah semua proses dimatikan, halaman tetap 200 dengan fallback CSR. RSS node terukur **79 MB** di container app.
- **Tanpa container baru:** SSR berjalan di dalam container `app` yang sama (localhost:13714), `docker-compose.prod.yml` tetap 3 service (`db`, `redis`, `app`).
- Dev: SSR tidak dipakai saat HMR (bundle statis vs chunk client baru = risiko mismatch). Perintah cek:
  `podman exec -d -w /app d_form_app sh -lc 'cd /app && npm run build:ssr && node bootstrap/ssr/ssr.js'`

**Keputusan: hydration TIDAK diaktifkan.** Percobaan mengganti `createApp` → `createSSRApp` di `app.js` menghasilkan hydration mismatch sistematis (class/node/style) di 4/4 halaman yang diuji (`/`, `/features`, `/auth/login`, `/docs`) karena komponen memakai state client-only (IntersectionObserver lottie, path aktif navbar). Dikembalikan ke `createApp`: SSR dipakai untuk HTML crawler, client merender ulang. CLS terukur **0** dengan SSR aktif, jadi tidak ada flash terukur. Hydration dicatat sebagai follow-up yang butuh audit per komponen.

**Test E2E disesuaikan:** atribut `novalidate` dipasang lewat `MutationObserver` (node form SSR diganti saat mount), dan reset bucket limiter memakai `Cache::flush()` karena aplikasi melihat IP NAT podman (`10.89.0.9`), bukan `127.0.0.1`.

## 16. Follow-up: semantik HTML5 & aksesibilitas

| Temuan (audit DOM)                                        | Sebelum              | Sesudah                                  |
| --------------------------------------------------------- | -------------------- | ---------------------------------------- |
| Logo navbar/footer `src="/public/DForm%201.png"`           | 404 (gambar rusak)   | 200, `width=113 height=49`                |
| Skip-link + `main#main` (publik)                           | tidak ada            | ada di 5 halaman publik                  |
| `<main>` di `/docs`                                        | 2 (nested, invalid)  | 1                                        |
| Landmark `<main>` di layout auth                           | 0                    | 1                                        |
| Input pencarian `/events`                                  | tanpa label          | `aria-label="Cari acara"`                |
| Heading skip (landing / features / docs)                   | 1 / 1 / 13           | 0 / 0 / 0                                |
| Dimensi intrinsik logo auth                                | 160×40 (salah)       | 113×49                                   |

Tidak diubah: `width`/`height` banner event — container sudah memakai `aspect-[4/3]` / `aspect-[16/10]` (`lib/eventBannerAspect.ts`), jadi CLS sudah dicegah tanpa menebak dimensi gambar upload.
