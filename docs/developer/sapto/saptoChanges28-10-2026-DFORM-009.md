# Sapto Changes — 28 Oktober 2026 — Harness e2e: project, auth, fixture, dan helper observasi (DFORM-009)

Nomor tiket: **DFORM-009** (`TASK-009`)
Status: implementasi selesai di branch `task-009-extend-the-e2e-harness-with-projects-auth-fixtur`, **belum di-push dan belum di-merge**.
Scope: harness test e2e saja. Satu-satunya perubahan produksi adalah `data-testid="authenticated-shell"` di `DashboardLayout.vue`.

---

## Ringkasan (TL;DR)

| Kebutuhan | Hasil | Bukti |
|---|---|---|
| Project `setup` + `chromium-desktop` + `chromium-mobile` dengan pemetaan spec | `playwright.config.ts` memakai `dependencies: ['setup']`, `testIgnore` dua arah, `storageState` admin | `npx playwright test --list` → **94 test / 12 file**, tiga project |
| Login admin sekali, state atomik | `tests/e2e/auth.setup.ts` (temp + `rename`, cookie sesi + URL `/dashboard` diperiksa) | `H-auth-setup` hijau; dua run berturut-turut menyisakan tepat satu `admin.json` |
| Fixture ber-marker + teardown idempoten | 7 perintah `e2e:*` + `check:row-ownership` | `php artisan list` memuat 8 perintah; `H-fixture-ownership`, `H-teardown-scope`, `H-marker-format` hijau |
| Heartbeat & sweep run terlantar | `e2e:sweep` + aturan heartbeat ≤ 30 s dan pid hidup | `H-heartbeat-live`, `H-heartbeat-stale`, `H-sweep-abandoned` hijau |
| Observasi request/baris/header | `support/network.ts`, `support/tabs.ts`, `e2e:count-rows`, `e2e:inspect`, `e2e:http`, `e2e:derive-key` | `H-observe` hijau |
| Gate gaya kode | `pint --test` | **PASS, 11 file** |

**Tujuh perbaikan harness ditemukan dan diverifikasi saat verifikasi** (bukan sekadar memindahkan kode): empat bug fungsional — nama cookie sesi yang di-hardcode, identifier `SESSION_COOKIE` yang tidak pernah didefinisikan, path CLI Playwright yang tidak tahan worktree, anotasi `Dirent` di `heartbeat.ts` — dan tiga lubang keamanan data (§3.1). Di atas itu, **dua regresi suite** baru terlihat saat full suite pertama kali dijalankan (§4.5). Detail di §3, §3.1, dan §4.5.

---

## 1. Yang berubah

### 1.1 File diubah (9)

| File | Perubahan |
|---|---|
| `playwright.config.ts` | `baseURL`/artisan/kredensial dari environment, satu `runId` per invokasi, tiga project, `fullyParallel`, `retries: 1`, `trace: on-first-retry`, `screenshot: only-on-failure`, `workers: 1` (lihat §4.5) |
| `package.json` | `test:e2e`, `test:e2e:desktop`, `test:e2e:mobile`, `test:e2e:setup`, `test:e2e:types`; `@types/node` sebagai devDependency (§7) |
| `package-lock.json` | entri `@types/node` 24.19.2 + `undici-types` 7.24.6, tanpa churn lain (§7) |
| `tsconfig.e2e.json` | project tipe khusus `tests/e2e` + `playwright.config.ts`; root `tsconfig.json` tetap tidak meng-include test |
| `.gitignore` | `/tests/e2e/.auth/` |
| `resources/js/layouts/DashboardLayout.vue` | `data-testid="authenticated-shell"` pada `SidebarProvider` (satu-satunya perubahan produksi) |
| `tests/e2e/support/artisan.ts` | `E2E_ARTISAN` → token perintah, dipakai semua helper |
| `tests/e2e/auth.spec.ts`, `otp-verification.spec.ts`, `email-availability.spec.ts` | `test.use({ storageState: { cookies: [], origins: [] } })` — spec ini menguji halaman guest (lihat §4.5) |
| `docs/developer/sapto/README.md` | satu baris ringkasan tiket |

### 1.2 File baru — PHP (11)

`app/Console/Commands/E2e/`: `FixtureCommand`, `SweepCommand`, `CleanupCommand`, `CountRowsCommand`, `CheckRowOwnershipCommand`, `DeriveKeyCommand`, `HttpCommand`, `InspectCommand`, `Concerns/ResolvesFixtureIdentity`.
`app/Support/E2e/`: `FixtureIdentity`, `FixtureScope`.

### 1.3 File baru — TypeScript (17)

`tests/e2e/`: `auth.setup.ts`, `global.setup.ts`, `global.teardown.ts`, `auth-required.spec.ts`, `auth-guard.spec.ts`, `auth-guest-redirect.guest.spec.ts`, `harness-projects.spec.ts`, `harness-projects.mobile.spec.ts`, `harness.spec.ts`.
`tests/e2e/support/`: `authGuard.ts`, `environment.ts`, `fixtures.ts`, `heartbeat.ts`, `marker.ts`, `network.ts`, `sweep.ts`, `tabs.ts`.
Ditambah `tests/fixtures/e2e-no-marker-tables.json` (tabel tanpa kolom marker: `form_answers`, `event_attendances`, kepemilikan diturunkan dari induknya).

---

## 2. Kontrak yang dipegang

### 2.1 Marker kanonik

```
marker   = e2e+<runId>+<ownerKey>          (40 karakter)
runId    = YYYYMMDDThhmmss-<6 hex>         (22 karakter)
ownerKey = "o" + 12 hex pertama sha256(ownerId)
slug     = marker huruf kecil, "+" → "-"   (kolom slug event)
email    = marker + "@e2e.invalid"
```

`FixtureIdentity` adalah satu-satunya tempat marker dibentuk; fixture, teardown, sweep, dan pemeriksaan kepemilikan memakai nilai yang sama. Perintah menolak `--marker` yang tidak cocok `^e2e\+[0-9A-Za-z-]{1,22}\+o[0-9a-f]{12}$` (runId dibatasi 22 karakter, sama dengan panjang yang dibangkitkan `createRunId`).

### 2.2 Project dan pemetaan spec

| Project | `testMatch`/`testIgnore` | Dependensi | `storageState` |
|---|---|---|---|
| `setup` | `/auth\.setup\.ts$/`, 1 worker, `retries: 0` | — | tidak ada |
| `chromium-desktop` | abaikan `*.mobile.spec.ts`, viewport 1440×900 | `setup` | `tests/e2e/.auth/admin.json` |
| `chromium-mobile` | abaikan `*.desktop.spec.ts`, device Pixel 7 | `setup` | `tests/e2e/.auth/admin.json` |

### 2.3 Environment

| Variabel | Arti | Default |
|---|---|---|
| `E2E_BASE_URL` | host aplikasi yang diuji | `http://localhost:8000` |
| `E2E_ARTISAN` | token perintah artisan, mis. `podman exec -i -w /app d_form_app php artisan` | perintah podman ke `d_form_app` |
| `E2E_ADMIN_EMAIL` / `E2E_ADMIN_PASSWORD` | kredensial admin seeder | wajib, gagal menyebut keduanya bila kosong |
| `E2E_SESSION_COOKIE` | nama cookie sesi Laravel | `dform-session` |

`assertEnvironment()` berhenti pada kegagalan pertama dan tiap pesan menyebut satu variabel saja, supaya base URL tak terjangkau tidak menghasilkan cascade timeout. Pengecualian yang disengaja: bila email **dan** password sama-sama kosong, pesan menyebut keduanya sekaligus agar setup tidak menebak mana yang harus diisi.

### 2.4 Siklus heartbeat

Run dianggap **hidup** hanya bila `now - mtime <= 30 s`, pid masih hidup, dan command line proses memuat test runner. Selain itu basi. `e2e:sweep` menghapus run tanpa heartbeat yang lebih tua dari 24 jam; pembatasan konkurensi (`flock`) ada di wrapper TS `tests/e2e/support/sweep.ts`, dan command-nya sendiri membatasi **waktu** proses per invokasi, bukan jumlah run.

---

## 3. Bug yang ditemukan saat verifikasi dan diperbaiki

Ketiganya membuat harness **tidak pernah hijau**, jadi tidak bisa dilewati sebagai "sekadar rapikan".

| # | Gejala | Akar | Perbaikan |
|---|---|---|---|
| 1 | `assertAdminState()` melempar `ReferenceError: SESSION_COOKIE is not defined` pada setiap state yang kehilangan cookie | `support/authGuard.ts` memakai identifier `SESSION_COOKIE` yang tidak pernah didefinisikan; `sessionCookieName()` sudah ada tapi belum dipakai | `hasSessionCookie(state, cookieName = sessionCookieName())` dan pesan error memakai `sessionCookieName()` |
| 2 | `H-auth-setup` selalu merah di langkah terakhir meski login berhasil dan `/dashboard` terbuka | cookie sesi di-hardcode `laravel_session`, sedangkan aplikasi menamainya `dform-session` (`config('session.cookie')`) | `auth.setup.ts` memakai `sessionCookieName()`; test `harness.spec.ts` menguji lewat `E2E_SESSION_COOKIE` sehingga tidak bergantung nama produksi |
| 3 | `H-projects` merah dengan output CLI kosong walau exit code 0 | `harness-projects.spec.ts` menjalankan `join(cwd, 'node_modules', '@playwright', 'test', 'cli.js')`; path itu tidak ada saat `node_modules` berupa symlink/worktree | resolusi lewat `createRequire(join(cwd,'package.json')).resolve('@playwright/test/cli')` |
| 4 | Gate tipe tidak pernah dijalankan, sehingga `heartbeat.ts` lolos dengan `TS2345`/`TS2339` | `listHeartbeatRunIds()` memberi anotasi `(entry: string)` pada hasil `readdirSync(root, { withFileTypes: true })` yang sebenarnya `Dirent[]` | `filter`/`map` dianotasi `(entry: Dirent)` dan `type { Dirent }` diimpor; `tsc` exit 0 (lihat §4.4) |

Konsekuensi praktis: **nama cookie sesi kini satu sumber** (`sessionCookieName`) dan tidak lagi tersebar sebagai string di dua file.

### 3.1 Perbaikan keamanan data saat review (29 Oktober 2026)

Review menemukan tiga jalur yang bisa menghapus atau menyapu baris di luar milik run e2e. Ketiganya diperbaiki:

| # | Risiko | Perbaikan |
|---|---|---|
| S1 | `e2e:cleanup --marker-prefix=A --slug-prefix=B` lolos validasi format dan menghapus event run B | `FixtureScope::prefixIdentity()` mewajibkan `slugPrefix === markerToSlug(markerPrefix)`; tidak ada caller lain yang mengirim pasangan bebas (`global.teardown.ts` menurunkan keduanya dari `runId` yang sama) |
| S2 | `e2e:sweep` mencocokkan email `e2e+…+o…@<domain apa pun>`, sehingga user nyata berawalan `e2e+` bisa tersapu | regex diperketat ke `@e2e\.invalid$`, satu-satunya domain yang dibangkitkan `FixtureIdentity` |
| S3 | `SweepCommand` memakai `@unlink`/`@rmdir` (supresi error, dilarang aturan repo) | diganti `is_file()` + `unlink()` dan `is_dir()` + `rmdir()` |

---

## 4. Bukti terukur

Semua perintah dijalankan dari worktree, PHP lewat container (`podman exec`), 28 Oktober 2026.

| # | Perintah | Hasil |
|---|---|---|
| 1 | `php artisan list \| grep -E 'e2e:\|check:row-ownership'` | 8 perintah: `e2e:cleanup`, `e2e:count-rows`, `e2e:derive-key`, `e2e:fixture`, `e2e:http`, `e2e:inspect`, `e2e:sweep`, `check:row-ownership` |
| 2 | `npx playwright test --list` | **94 test / 12 file**, tiga project; `harness-projects.mobile.spec.ts` hanya di `chromium-mobile` |
| 3 | `npx playwright test --project=chromium-mobile --list` | memuat `H-projects mobile placeholder`; tidak ada `*.desktop.spec.ts` |
| 4 | `./vendor/bin/pint --test app/Console/Commands/E2e app/Support/E2e` | **PASS 11 file (PSR-12)** |
| 5 | `npx playwright test harness.spec.ts --project=chromium-desktop` | **17 passed (57,1 s)** — termasuk `H-auth-setup`, `H-fixture-ownership`, `H-teardown-scope`, `H-marker-format`, `H-fixture-partial-failure`, `H-heartbeat-live/stale`, `H-sweep-abandoned`, `H-observe`, `H-harness-isolation` |
| 6 | `npx playwright test harness-projects.spec.ts harness-projects.mobile.spec.ts` | **4 passed (14,1 s)** |
| 7 | `npx playwright test auth-required.spec.ts auth-guard.spec.ts auth-guest-redirect.guest.spec.ts --project=chromium-desktop` | **3 passed, 1 failed** — `H-auth-required` merah karena `data-testid` hanya ada di branch (lihat §5) |
| 8 | Setelah `data-testid` branch diterapkan sementara pada checkout yang disajikan `:3000`, `H-auth-required` diulang | **2 passed (15,6 s)**; perubahan sementara langsung di-`git checkout --` (terverifikasi 0 kemunculan) |

Environment saat pengukuran: `E2E_BASE_URL=http://localhost:3000`, `E2E_ARTISAN` menunjuk container dengan workdir worktree, `E2E_SESSION_COOKIE=dform-session`, admin `admin@gmail.com` dari `UserSeeder`.

### 4.1 Verifikasi ulang independen (29 Oktober 2026)

Seluruh angka di §4 diukur ulang dari nol pada worktree yang sama (`6aa5587`), dan hasilnya cocok:

| # | Perintah | Hasil |
|---|---|---|
| 1 | `php artisan test --testsuite=Feature,Unit` (container, workdir worktree) | **804 passed, 36 skipped, 0 failed (5.097 assertion)** — tidak ada regresi PHP |
| 2 | `playwright test --project=setup` | **1 passed (14,8 s)** |
| 3 | `playwright test harness.spec.ts --project=chromium-desktop` | **17 passed (59,7 s)** |
| 4 | `playwright test harness-projects.spec.ts harness-projects.mobile.spec.ts` | **4 passed** (desktop + mobile + placeholder) |
| 5 | `playwright test auth-required… auth-guard… auth-guest-redirect…` | **3 passed, 1 failed** — `H-auth-required` merah, persis seperti §4 baris 7 |
| 6 | `H-auth-required` diulang setelah `data-testid` diterapkan sementara di root | **2 passed (12,7 s)**; root langsung di-`git checkout --`, `git status` bersih dan 0 kemunculan `authenticated-shell` |
| 7 | `pint --test app/Console/Commands/E2e app/Support/E2e` | **PASS 11 file** |
| 8 | `eslint "tests/e2e/**/*.ts" playwright.config.ts` | **tanpa temuan** |

### 4.2 Jebakan environment: `public/hot` basi membuat setiap halaman 10 detik

Gejala: `GET /` konsisten **10,0 s** (bukan 100 ms), `HEAD` juga, sementara `/up` 9 ms. Debugbar menunjukkan `Preparing Response` 10,01 s dengan view 0 μs, dan `X-Inertia` JSON hanya 43 ms — jadi bukan query, bukan Vite (`Vite::__invoke` 2 ms), melainkan **Inertia SSR**.

Akar: `public/hot` berisi `http://172.20.10.8:5173` (IP host lama). Bundle SSR dev mengambil modul dari URL itu, tidak terjangkau, menunggu timeout 10 s, lalu Inertia **diam-diam fallback** ke render klien. Efek sampingnya bukan cuma lambat: `@vite` juga menyuntikkan URL mati itu ke browser, jadi aset tidak pernah dimuat dan spec berbasis halaman ikut merah. Probe `assertBaseUrl` (2 s) lebih dulu menyerah, sehingga pesan yang muncul "E2E_BASE_URL tidak menjawab HEAD" — bukan akar masalahnya.

Perbaikan: tulis ulang `public/hot` dengan IP host saat ini (`git check-ignore` menandakan berkas ini memang generated dan tidak di-commit), lalu sentuh satu berkas `app/` agar Octane `--watch` me-reload worker. Setelah itu `GET /` = 12–130 ms dan seluruh spec hijau. Kalau lambatnya kembali: periksa `public/hot` sebelum menaikkan timeout apa pun.

### 4.3 Data dev perlu admin seeder

Database dev berisi 1.000.148 user tanpa `admin@gmail.com`, sehingga `H-auth-setup` tidak bisa login. Menjalankan `php artisan db:seed --class=UserSeeder` (idempoten, `firstOrCreate` + `syncRoles`) menambahkan 3 admin dan 5 member tanpa menghapus data. Setelah itu baris 5–7 di atas bisa dijalankan.

### 4.4 Verifikasi ketiga: server yang benar-benar menyajikan worktree (29 Oktober 2026)

Brief tiket menyebut branch masih **0 commit**; faktanya HEAD sudah `6a63ff6` dengan 2 commit dan working tree bersih. Yang belum pernah ada adalah bukti runtime terhadap **kode worktree**: `:3000` menyajikan root checkout. Container kedua dijalankan supaya batas itu hilang.

```bash
podman run -d --name d_form_e2e_wt --network d-form-v2_d-form-network \
  -p 3001:8000 \
  -v /home/zappto/Serius/Udinus/DOSCOM/d-form-v2:/app \
  -v <volume node_modules>:/app/node_modules -v <volume vendor>:/app/vendor \
  -w /app/.sokudo/worktrees/task-009-... --entrypoint sh \
  localhost/d-form-v2_app:latest -c 'php artisan octane:frankenphp --host=0.0.0.0 --port=8000'
```

`public/build` worktree sudah memuat `authenticated-shell`, `public/hot` tidak ada, jadi tidak perlu build ulang. `GET /` = 0,018–0,258 s.

| # | Perintah | Hasil |
|---|---|---|
| 1 | `GET/HEAD http://localhost:3001/` | **200**, 0,018–0,258 s |
| 2 | `playwright test --project=setup` (BASE_URL `:3001`) | **1 passed (20,5 s)** |
| 3 | `playwright test auth-required auth-guard auth-guest-redirect --project=chromium-desktop` | **4 passed** — `H-auth-required`, `H-auth-guard`, `H-auth-guest-redirect`, plus `H-auth-setup` |
| 4 | `playwright test harness.spec.ts harness-projects*.spec.ts --project=chromium-desktop` | **18 passed (1,1 m)** |
| 5 | `playwright test --project=setup` dua kali berturut-turut | **1 passed** keduanya; `tests/e2e/.auth` tetap **1 file** (`admin.json`, cookie `XSRF-TOKEN` + `dform-session`, 1 origin) |
| 6 | `admin.json` dipindah, `playwright test auth-guard.spec.ts` | **2 passed** — guard memakai path temp, tetap melempar pesan terkontrol tanpa navigasi |
| 7 | `tsc -p <throwaway config>` atas `tests/e2e/**/*.ts` | sempat **2 error** di `heartbeat.ts` (§3 baris 4), setelah perbaikan **exit 0** |
| 8 | `eslint "tests/e2e/**/*.ts" playwright.config.ts` | **exit 0**, tanpa temuan |
| 9 | `pint --test app/Support/E2e app/Console/Commands/E2e` | **PASS 11 file (PSR-12)** |

Dengan begitu `H-auth-required` hijau **tanpa** menambal root checkout seperti di §4 baris 8: testid-nya berasal dari build worktree sendiri.

### 4.5 Full suite pertama: dua regresi nyata dari config baru, lalu hijau

Sebelum sesi ini, **full suite tidak pernah dijalankan** (lihat batas lama di §5.1). Saat dijalankan, config baru membuat suite lama merah. Dua penyebab, keduanya diverifikasi, bukan dugaan:

| Gejala | Bukti | Akar | Perbaikan |
|---|---|---|---|
| `auth.spec.ts`, `otp-verification.spec.ts`, `email-availability.spec.ts` gagal di locator pertama | `curl` dengan cookie `admin.json`: `GET /auth/login → 302 /`, `GET /auth/register → 302 /`; tanpa cookie `→ 200` | `storageState` project berlaku untuk **semua** spec, padahal ketiganya menguji halaman di middleware `guest` | `test.use({ storageState: { cookies: [], origins: [] } })` di ketiga file, pola yang sudah dipakai `auth-guest-redirect.guest.spec.ts` |
| Assert `429` di `auth.spec.ts` dan `event-search.spec.ts` gagal saat suite paralel | `--workers=1` pada file yang sama: **11 passed**; paralel: 3 gagal | empat spec lama memanggil `Cache::flush()` **global** di `beforeEach`, sehingga worker lain menghapus hitungan rate limit di tengah assert | `workers: 1` di config; `fullyParallel: true` tetap dipertahankan sesuai brief tiket |

Seluruh baris di §4.5 dijalankan dengan `E2E_BASE_URL=http://localhost:3001` (container yang menyajikan worktree), `E2E_ARTISAN` menunjuk workdir worktree, dan admin `admin@gmail.com`.

| # | Perintah | Hasil |
|---|---|---|
| 1 | `playwright test --project=chromium-desktop` (sebelum perbaikan) | **merah** — 3 gagal (2 throttle, 1 event-search 429) |
| 2 | `playwright test auth.spec.ts event-search.spec.ts --workers=1` | **11 passed (50,8 s)** — membuktikan penyebabnya race, bukan logika |
| 3 | `playwright test --project=chromium-desktop` (sesudah perbaikan) | **46 passed, 1 flaky (6,5 m)** |
| 4 | `playwright test auth.spec.ts email-availability.spec.ts auth-required auth-guard --project=chromium-mobile` | **14 passed, 1 flaky** |
| 5 | `playwright test harness.spec.ts --project=chromium-desktop` (sesudah perbaikan PHP §3.1) | **17 passed (3,5 m)** |

**Sisa yang harus dinyatakan:** satu test masih kadang butuh retry — `throttle › kuota forgot-password` (desktop) dan `throttle › login melewati limit` (mobile). Dengan `retries: 1` run tetap hijau, tetapi **penyebab pastinya belum teridentifikasi**. Hipotesis awal yang sempat ditulis di sini — "limiter login per-IP sehingga login `H-auth-setup` memakan kuota" — **salah**: `auth-login` di-key `strtolower($email).'|'.$ip` (`app/Providers/AppServiceProvider.php:90-96`) sedangkan test memakai email unik `e2e-throttle-<timestamp>@example.test`. Yang memang per-IP adalah `auth-password-email` (`:104-106`), jadi hanya flake forgot-password yang cocok dengan hipotesis itu. Langkah berikutnya bila flake kembali: rekam status tiap attempt (bukan menebak), lalu isolasi limiter yang terbukti bocor. Jangan menaikkan `retries`.

---



## 5. Batas yang harus dinyatakan

1. **Server `:3000` menyajikan root checkout, bukan worktree.** Run di §4 karena itu menguji **kode aplikasi root** dengan **kode test dari branch**; hanya `H-auth-required` yang terpengaruh dan di sana ia merah. Batas itu ditutup di §4.4 lewat container kedua di `:3001` yang menyajikan worktree, §4.5 menjalankan seluruh suite `chromium-desktop`, dan §8 menutup **seluruh suite `chromium-mobile`** di server yang sama. Yang belum: run dari root checkout setelah merge.
2. **Test e2e tidak ikut `tsc` proyek lewat root `tsconfig.json`** — itu tetap disengaja (root hanya meng-include `resources/js/**`). Gate tipe sekarang punya project sendiri: `npm run test:e2e:types` → `tsc -p tsconfig.e2e.json`, dengan `@types/node` sebagai devDependency. Sebelum §7, gate ini dijalankan dari `tsconfig` sekali-pakai + `@types/node` di `/tmp`, sehingga error `heartbeat.ts` sempat lolos dan error tipe di `playwright.config.ts`/spec tidak pernah terdeteksi editor.
3. **Empat perintah artisan tidak dipanggil TS**: `check:row-ownership`, `e2e:derive-key`, `e2e:http`, `e2e:inspect`. Disediakan untuk observasi manual; sengaja tidak dihapus.
4. **Probe base URL memakai timeout tetap 2 s** (`assertBaseUrl`). Nilainya sengaja ketat supaya host mati gagal cepat, dan tidak dibuat configurable karena belum ada environment yang butuh angka lain. Konsekuensinya: kalau halaman terasa lambat, jangan naikkan timeout ini — cari dulu penyebabnya (§4.2).
5. **`tests/fixtures/e2e-no-marker-tables.json` belum dibaca kode mana pun.** Isinya daftar tabel tanpa kolom marker (`form_answers`, `event_attendances`) dan kepemilikan diturunkan dari induk — itu memang cara kerja `FixtureScope`, tetapi daftarnya masih hardcoded, bukan dibaca dari JSON ini. Dibiarkan sebagai dokumentasi data, bukan wiring.
6. **`workers: 1` membatasi manfaat `fullyParallel: true`.** Nilai brief (`fullyParallel: true`) tetap dipakai, tetapi sampai empat spec lama berhenti memanggil `Cache::flush()` global, menjalankan dua file sekaligus membuat assert `429` merah (§4.5). Menaikkan worker hanya boleh dilakukan setelah spec itu diisolasi. Perbaikan permanen yang lebih murah — mempersempit flush ke key limiter, atau memberi empat spec lama cache store sendiri — tidak diambil karena mengubah spec lama di luar scope; dicatat sebagai follow-up, bukan keputusan diam-diam.
7. **Dua guard baru kini punya test penolakan** (§8.2), sehingga batas ini ditutup: `H-cleanup-slug-prefix` menahan `--slug-prefix` yang sah secara format tetapi bukan turunan `markerToSlug(marker-prefix)`, dan `H-sweep-domain` memastikan sweep tidak menyentuh run yang email pemiliknya bukan `@e2e.invalid`.
8. **`CleanupCommand` tidak lagi mengulang `InvalidArgumentException`.** Validasi argumen bersifat permanen, jadi langsung diteruskan tanpa retry 250 ms; kegagalan DB tetap diulang sekali seperti sebelumnya.

---

## 6. Perintah harian

```bash
npm run test:e2e:setup            # login admin, tulis tests/e2e/.auth/admin.json
npm run test:e2e:desktop          # seluruh spec desktop
npm run test:e2e:mobile           # seluruh spec mobile
npm run test:e2e -- --list        # cek pemetaan project tanpa menjalankan
npm run test:e2e:types            # type-check tests/e2e + playwright.config.ts
```

Wajib diset sebelum run: `E2E_BASE_URL`, `E2E_ADMIN_EMAIL`, `E2E_ADMIN_PASSWORD`, dan `E2E_ARTISAN` bila artisan tidak dijalankan dari container default. Untuk worktree, `E2E_ARTISAN` harus menunjuk workdir worktree — kalau tidak, perintah artisan menyentuh root checkout dan fixture akan salah sasaran.

---

## 7. Round 3 — gate tipe dijadikan permanen

### 7.1 Masalah

Setelah commit `9166a7f`, editor masih melaporkan 6 file merah: `playwright.config.ts`, `auth.setup.ts`, `auth-guard.spec.ts`, `global.setup.ts`, `global.teardown.ts`, `harness-projects.spec.ts`, `harness.spec.ts`. Semuanya satu akar: `@types/node` tidak ada di `node_modules`, sehingga `process` dan `node:fs`/`node:path`/`node:child_process` tidak bertipe. Gate `tsc` sekali-pakai di §4 tidak menangkapnya karena `@types/node` disuntikkan dari `/tmp`.

### 7.2 Perbaikan

- `@types/node@^24.19.2` masuk `devDependencies` (versi 24 dipilih karena `@playwright/test` menerima `^20.19.0 || >=22.12.0` dan TS proyek 5.9.3).
- `tsconfig.e2e.json` berdiri sendiri (tidak `extends` root, karena root memuat `ignoreDeprecations: "6.0"` yang ditolak TS 5.9 dengan `TS5103`), `types: ["node"]`, `noEmit`, `strict`.
- `package-lock.json` disunting aditif: hanya 3 entri baru (`@types/node`, `undici-types`, satu baris devDependencies). Regenerasi penuh oleh npm 12 di host menambah churn `@tailwindcss/oxide-wasm32-wasi/nodes_modules/*` dan mengubah `name`, jadi tidak dipakai.
- Root `tsconfig.json` tidak disentuh: `resources/js` tetap hanya memakai `types: ["vite/client"]`, jadi global Node tidak bocor ke kode frontend.

### 7.2.1 Paket benar-benar dipasang (koreksi round 4)

Round 3 mengubah `package.json` + `package-lock.json` tetapi **tidak** memasang paketnya: registry tidak terjangkau dari dalam container, dan `node_modules` worktree adalah symlink ke `/app/node_modules` yang tidak ada di host. Akibatnya `@types/node` tetap tidak ada di disk dan editor tetap merah — `exit 0` saat itu berasal dari `tsconfig` sekali-pakai + `@types/node` di `/tmp`, bukan dari dependency proyek.

Yang dilakukan sekarang:

- Tarball diambil dari cache npm host secara offline (`npm pack @types/node@24.19.2 --offline` di `/tmp/typesnode`, lalu `npm install --offline`), karena registry hanya terjangkau dari host.
- `node_modules/@types/node` (24.19.2) dan `node_modules/undici-types` (7.24.6) disalin ke `node_modules` root checkout. Versi + integrity keduanya identik dengan entri `package-lock.json` (§7.2), jadi tidak ada drift lockfile.
- Symlink `node_modules` worktree diubah dari `-> /app/node_modules` (hanya valid di dalam container) menjadi relatif `-> ../../../node_modules`, sehingga resolusi modul sama di host dan di container. Symlink ini gitignored, tidak masuk commit.

### 7.3 Bukti

| Perintah | Hasil |
|---|---|
| `podman exec -w <worktree> d_form_app npm run test:e2e:types` | exit 0, tanpa output (sebelum fix: 25 error `TS2307`/`TS2580` di 7 file) |
| `./node_modules/.bin/tsc -p tsconfig.e2e.json --listFiles \| grep -c 'types/node/'` (host) | `66` — `@types/node` benar-benar ter-resolve dari `node_modules`, bukan dari `/tmp` |
| `podman exec -w <worktree> d_form_app npx eslint "tests/e2e/**/*.ts" playwright.config.ts` | exit 0 |
| `podman exec -w <worktree> d_form_app npx playwright test --list` | `Total: 94 tests in 12 files` (pemetaan project tidak berubah) |
| `npx tsc -p tsconfig.e2e.json` di host dan di container | exit 0 di keduanya |
| `lsp_diagnostics` pada 6 file | tidak bisa dipakai sebagai bukti: server TS terus menjawab "still starting" walau dipanggil berulang; bukti pengganti = dua `tsc` independen di atas |

### 7.4 Batas

- Instalasi `@types/node` dilakukan **tanpa** persetujuan paket eksplisit di sesi ini karena diminta memperbaiki error tipe yang dilaporkan; paketnya dev-only, tanpa runtime dependency baru. Kalau tidak diinginkan, cukup hapus dari `devDependencies` + `tsconfig.e2e.json` dan gate tipe kembali manual.
- `tsconfig.e2e.json` hanya dipakai `npm run test:e2e:types`; editor memakai project inferred-nya sendiri (yang kini menemukan `@types/node`). Tidak ada tsconfig `references` yang ditambahkan.
- Paket dipasang ke `node_modules` root checkout (bukan ke worktree) karena `node_modules` worktree adalah symlink dan container hanya bind-mount root checkout. Baris `@types/node`/`undici-types` jadi ikut terpasang di root checkout sampai `npm ci` berikutnya; `package.json` root tidak berubah.

---

## 8. Round 5 — kedua project penuh hijau + test penolakan guard

### 8.1 Bukti ukur (29 Oktober 2026)

Semua baris dijalankan dari worktree, terhadap container `e2e009` yang menyajikan worktree di `:3001` (`E2E_ARTISAN="podman exec -i -w /app e2e009 php artisan"`, admin `admin@gmail.com`), jadi kode aplikasi **dan** kode test sama-sama dari branch.

| # | Perintah | Hasil |
|---|---|---|
| 1 | `npm run test:e2e:types` (`tsc -p tsconfig.e2e.json`, host) | exit 0 |
| 2 | `eslint tests/e2e/**/*.ts playwright.config.ts` | exit 0 |
| 3 | `playwright test --project=chromium-desktop` | **48 passed, 1 flaky (6,0 m)** |
| 4 | `playwright test --project=chromium-mobile` | **49 passed, 1 flaky (6,1 m)** |
| 5 | `playwright test harness.spec.ts -g "H-cleanup-slug-prefix|H-sweep-domain"` | **keduanya hijau** |

Flaky yang tersisa sama dengan yang sudah dicatat di §4.5 dan bukan dari perubahan ini: `event-search.spec.ts › pesan "terlalu umum"` (desktop) dan `auth.spec.ts › throttle › login melewati limit` (mobile). Keduanya lulus pada retry pertama; penyebabnya tetap belum teridentifikasi, jadi `retries: 1` tidak dinaikkan.

### 8.2 Test penolakan untuk dua guard

| Test | Yang dibuktikan |
|---|---|
| `H-cleanup-slug-prefix` | `e2e:cleanup --marker-prefix=… --slug-prefix=<run lain>` gagal dengan `slug prefix must equal markerToSlug(marker prefix)`, dan baris event run tersebut **tetap ada** sesudahnya. Prefix uji sengaja sah secara format (22 karakter, huruf kecil) supaya satu-satunya penahan adalah pemeriksaan kesetaraan, bukan validasi bentuk. |
| `H-sweep-domain` | Run yang email pemiliknya diganti ke `@example.com` — marker dan slug tetap sah, baris berumur 30 jam — **tidak** disapu `e2e:sweep`; user dan event-nya masih ada. Ini yang mencegah `e2e+…@gmail.com` milik user nyata ikut terhapus. |

### 8.3 Perbaikan diagnosa kegagalan artisan

`runArtisanCommand()` sekarang membungkus `execFileSync` dan menyusun ulang pesan gagal dari `stderr`/`stdout`. Sebelumnya pesan hanya `Command failed: …` tanpa sebab, sehingga assertion `toThrow(/slug prefix must equal/)` tidak mungkin ditulis dan kegagalan artisan apa pun kehilangan konteksnya. `cause` asli tetap dilekatkan.

### 8.4 Batas

- `workers: 1` tetap dipakai (§5.6): dua flake di §8.1 adalah gejala yang sama, dan menaikkan worker akan mengubahnya jadi gagal.
- Run `chromium-desktop` + `chromium-mobile` memakan ~12 menit; tidak ada CI yang menjalankannya, jadi gate ini masih manual.

---

## 9. Round 6 — temuan review: pencocokan berkas reporter

### 9.1 Temuan dan apa yang terukur

Review menyatakan `projectsForSpecFile()` (`tests/e2e/harness-projects.spec.ts:99`) tidak pernah cocok karena membandingkan `spec.file` dengan `basename(file)`, sedangkan reporter mengirim path relatif terhadap `config.rootDir`. Diukur langsung pada versi yang terpasang:

```
$ node -e "console.log(require('@playwright/test/package.json').version)"
1.64.1
$ playwright test --list --reporter=json | <dump file field>
suite.file= "auth-guard.spec.ts" spec.file= "auth-guard.spec.ts"
suite.file= "harness-projects.spec.ts" spec.file= "harness-projects.spec.ts"
```

Jadi untuk konfigurasi ini `rootDir` project **sama dengan** `testDir` (`./tests/e2e`), dan karena semua spec berada langsung di `testDir`, path relatifnya memang nama dasar. Klaim "tidak pernah cocok" tidak terbukti pada tree ini — `H-projects` hijau sejak awal bukan kebetulan.

### 9.2 Celah yang nyata dan diperbaiki

Premisnya salah, tetapi akibat yang ditunjuknya nyata begitu ada berkas di sub-folder: reporter akan mengirim `sub/a.spec.ts`, sementara helper membandingkan dengan `a.spec.ts` → helper diam-diam mengembalikan `[]` dan assertion `toEqual([...])` gagal tanpa sebab yang jelas. Perbaikannya:

| Sebelum | Sesudah |
|---|---|
| `spec.file !== basename(file)` | `isSameSpecFile(spec.file, file)` — normalisasi `./`, `\` → `/`, lalu cocok bila sama **atau** salah satu akhiran path dari yang lain |

Suffix matching dua arah dipakai supaya kedua bentuk masukan (nama dasar maupun path penuh) bekerja, dan tidak ada asumsi tentang `testDir` yang bisa berubah. Baris `spec.file === undefined` kini dilewati eksplisit.

### 9.3 Bukti

| # | Perintah | Hasil |
|---|---|---|
| 1 | `tsc -p tsconfig.e2e.json` | exit 0 |
| 2 | `eslint tests/e2e/harness-projects.spec.ts` | exit 0 |
| 3 | `playwright test harness-projects.spec.ts harness-projects.mobile.spec.ts` (`:3001`, e2e009) | **6 passed** (1 setup + 2 test × 2 project + mobile placeholder) |

Test baru `H-projects-path` mengunci keenam bentuk yang dipermasalahkan: nama dasar, path `rootDir`-relative, path bersarang, awalan `./`, serta dua kasus yang **harus** tidak cocok (`harness-projects.mobile` vs `harness-projects`, dan dua direktori berbeda dengan nama berkas sama).

## 10. Round 7 — bukti fault-injection 3.16–3.25

Sub-tiket `TASK-025`, `TASK-026`, `TASK-027`, `TASK-033`, `TASK-034` menuntut bukti **merah yang direkam**: satu fault satu baris, diff-nya diarsipkan, assertion yang gagal dicatat, lalu fault di-revert. Bagian ini yang mengisi kekosongan itu.

### 10.1 Dua baris baru

| Baris | Isi assertion |
|---|---|
| `H-cleanup-failure` | Event di luar scope menahan `DELETE users` lewat FK `restrictOnDelete`; `e2e:cleanup` harus gagal dengan `e2e cleanup failed on table users`, lalu `check:row-ownership` melaporkan `"leftoverTotal":2` |
| `H-harness-parallel` | Fixture milik test lain di run yang sama tetap utuh setelah teardown test ini |

### 10.2 Matriks fault (satu fault → satu baris merah → revert)

Semua dijalankan terhadap container `e2e009` di `:3001` dengan `--retries=0` agar merahnya bukan hasil retry.

| Sub-tiket | Fault yang disuntikkan | Baris yang dijalankan | Assertion yang gagal |
|---|---|---|---|
| 3.16 (`TASK-025`) | `array_reverse($this->journal)` → `$this->journal` di `FixtureCommand::rollbackJournal()` | `H-fixture-partial-failure` | `Expected 0 / Received 3` — tiga baris induk tertinggal |
| 3.17 (`TASK-026`) | `catch (\Throwable)` melempar → `continue` di `FixtureScope::deleteIds()` | `H-cleanup-failure` | `Expected pattern: /e2e cleanup failed on table users/` — kegagalan ditelan, exit 0 |
| 3.18 (`TASK-027`) | `$this->identity->slug()` → `->marker` pada predikat `events` | `H-marker-format` (cleanup via slug) | cleanup gagal `on table users` karena event tidak pernah cocok |
| 3.24a (`TASK-033`) | predikat `events` memakai marker mentah | `H-fixture-ownership` | `Expected 1 / Received 0` pada `counts.events` |
| 3.24b (`TASK-033`) | `createOwnerId()` membuang `testSlug` | `H-harness-isolation` | `Expected: not "e2e+…"` — dua test berbagi satu marker |
| 3.24c (`TASK-033`) | `e2e:count-rows` mengabaikan `--marker` | `H-fixture-ownership` | `Expected 1 / Received 0` — hitungan tidak lagi milik marker |
| 3.24d (`TASK-033`) | `createOwnerId()` membuang sufiks `.r<retry>` | `H-teardown-scope` | `Expected 1 / Received 0` — teardown r0 menghapus baris r1 |
| 3.24e (`TASK-033`) | `createOwnerId()` membuang `testSlug` (dua worker, `--workers=2`) | `H-harness-parallel` | `Expected 1 / Received 0` — teardown satu test menghapus baris test lain |

Seluruh diff yang disuntikkan dan log merahnya diarsipkan di luar repo (`/tmp/opencode/e2e-eviden/`) karena bersifat sementara; tabel di atas adalah ringkasan yang bisa diverifikasi ulang kapan saja dengan mengulang fault yang sama.

### 10.3 Pemetaan baris 3.24 — dinyatakan apa adanya

Sub-tiket 3.24 meminta lima baris bernama `H-harness-fixtures`, `H-harness-isolation`, `H-harness-observe`, `H-harness-retry`, `H-harness-parallel`. Empat di antaranya **sudah ada** di `harness.spec.ts` dengan nama yang dipakai sejak 3D/3E (`H-fixture-ownership`, `H-harness-isolation`, `H-observe`, `H-teardown-scope`). Menambah baris kembar hanya demi nama akan menduplikasi test yang sama, jadi yang dilakukan:

| Nama di sub-tiket | Baris nyata yang dipakai |
|---|---|
| `H-harness-fixtures` | `H-fixture-ownership` |
| `H-harness-isolation` | `H-harness-isolation` (sudah ada) |
| `H-harness-observe` | `H-observe` |
| `H-harness-retry` | `H-teardown-scope` |
| `H-harness-parallel` | **baru** — tidak ada padanannya |

**Koreksi bukti fault 3.24 (`TASK-216`).** Brief `TASK-033` meminta fault yang berbeda dari 3.24b/3.24e di atas: predikat teardown memakai **prefix run** alih-alih marker exact, lalu dibuktikan teardown satu test ikut menghapus baris test lain. Fault itulah yang benar-benar menguji `FixtureScope::delete()` + `prefixIdentity()` — satu-satunya penahan hapus lintas-pemilik — jadi bukti di bawah ini yang mengikat untuk 3.24; baris 3.24b/3.24e tetap sah sebagai bukti tambahan untuk `createOwnerId()`.

Fault (sementara, tidak di-commit) pada `FixtureScope::delete()`:

```diff
-        return $this->deleteIds($this->ids());
+        return $this->deleteIds($this->resolve(
+            email: $this->identity->markerPrefix(),
+            slug: $this->identity->slugPrefix(),
+            markerLike: '% ['.$this->identity->markerPrefix().'%',
+            metaMarker: $this->identity->markerPrefix(),
+            isPrefix: true,
+        ));
```

Perintah (worktree TASK-215, container `e2e215` di `:3003`, `--retries=0`):

```bash
npx playwright test --project=chromium-desktop --workers=1 --retries=0 harness.spec.ts \
  -g "H-teardown-scope|H-harness-parallel"
```

Output merah (apa adanya):

```
  ✓  1 [setup] › tests/e2e/auth.setup.ts:11:1 › H-auth-setup: login admin dan simpan storageState (2.6s)
  ✘  2 [chromium-desktop] › tests/e2e/harness.spec.ts:186:5 › fixture lifecycle › H-teardown-scope: teardown r0 tidak menghapus baris retry r1 (45.8s)
  ✘  3 [chromium-desktop] › tests/e2e/harness.spec.ts:519:5 › harness isolation › H-harness-parallel: teardown satu test tidak menghapus baris test lain di run yang sama (45.4s)
    Error: expect(received).toBe(expected) // Object.is equality
    Expected: 1
    Received: 0
    Error: expect(received).toBe(expected) // Object.is equality
    Expected: 1
    Received: 0
  2 failed
  1 passed (2.0m)
```

Dua baris yang menangkapnya: `H-teardown-scope` (retry r1 hilang bersama teardown r0) dan `H-harness-parallel` (sibling di run yang sama ikut terhapus). `H-harness-isolation` **tidak** menangkap fault ini karena barisnya membandingkan marker dua test yang belum pernah dibuat, bukan baris yang sudah ada — dicatat supaya tidak diklaim lebih luas dari kenyataan.

Setelah `git checkout -- app/Support/E2e/FixtureScope.php`, perintah yang sama: **3 passed (2.0m)**. `git diff` final bersih dari fault (`git status --porcelain` kosong saat itu).


### 10.4 Green dua worker (3.25 / `TASK-034`)

| # | Perintah | Hasil |
|---|---|---|
| 1 | `playwright test harness.spec.ts --project=chromium-desktop --workers=2` | **21 passed (3,1 m)** |
| 2 | `npm run test:e2e:types` | exit 0 |
| 3 | `eslint tests/e2e/harness.spec.ts` | exit 0 |

`workers: 1` di `playwright.config.ts` **tidak diubah**: alasan aslinya tetap berlaku (`Cache::flush()` global di empat spec lama membuat assert 429 gagal saat paralel). Bukti dua worker diperoleh lewat override CLI yang dibatasi ke `harness.spec.ts`, karena baris-baris itulah yang memang didesain mandiri — bukan dengan melonggarkan batas untuk seluruh suite.

### 10.5 Batas

- `check:row-ownership` melaporkan `illegal` untuk enam event `e2e-pratinjau-live*` yang dibuat **27 Oktober 2026**, sebelum harness ini ada. Itu bukan sisa run ini (semua run di atas meninggalkan `users`/`forms`/`blocker` = 0) dan tidak dihapus karena berada di luar scope tiket.
- Fault disuntikkan pada kode PHP/TS branch lalu di-revert dengan `git checkout`; tidak ada satu pun yang ikut ter-commit.
