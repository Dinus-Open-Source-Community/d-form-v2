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
| Fixture ber-marker + teardown idempoten | 8 perintah artisan `e2e:*` + `check:row-ownership` | `php artisan list` memuat 8 perintah; `H-fixture-ownership`, `H-teardown-scope`, `H-marker-format` hijau |
| Heartbeat & sweep run terlantar | `e2e:sweep` + aturan heartbeat ≤ 30 s dan pid hidup | `H-heartbeat-live`, `H-heartbeat-stale`, `H-sweep-abandoned` hijau |
| Observasi request/baris/header | `support/network.ts`, `support/tabs.ts`, `e2e:count-rows`, `e2e:inspect`, `e2e:http`, `e2e:derive-key` | `H-observe` hijau |
| Gate gaya kode | `pint --test` | **PASS, 11 file** |

**Tiga bug harness ditemukan dan diperbaiki saat verifikasi** (bukan sekadar memindahkan kode): nama cookie sesi yang di-hardcode, identifier `SESSION_COOKIE` yang tidak pernah didefinisikan, dan path CLI Playwright yang tidak tahan worktree. Detail di §3.

---

## 1. Yang berubah

### 1.1 File diubah (5)

| File | Perubahan |
|---|---|
| `playwright.config.ts` | `baseURL`/artisan/kredensial dari environment, satu `runId` per invokasi, tiga project, `fullyParallel`, `retries: 1`, `trace: on-first-retry`, `screenshot: only-on-failure` |
| `package.json` | `test:e2e`, `test:e2e:desktop`, `test:e2e:mobile`, `test:e2e:setup` |
| `.gitignore` | `/tests/e2e/.auth/` |
| `resources/js/layouts/DashboardLayout.vue` | `data-testid="authenticated-shell"` pada `SidebarProvider` (satu-satunya perubahan produksi) |
| `tests/e2e/support/artisan.ts` | `E2E_ARTISAN` → token perintah, dipakai semua helper |
| `tests/e2e/support/heartbeat.ts` | `listHeartbeatRunIds()` memakai anotasi `string` padahal `readdirSync(..., { withFileTypes: true })` mengembalikan `Dirent[]` (lihat §3 baris 4) |

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

`FixtureIdentity` adalah satu-satunya tempat marker dibentuk; fixture, teardown, sweep, dan pemeriksaan kepemilikan memakai nilai yang sama. Perintah menolak `--marker` yang tidak cocok `^e2e\+[0-9A-Za-z-]+\+o[0-9a-f]{12}$`.

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

`assertEnvironment()` berhenti pada kegagalan pertama dan hanya menyebut satu variabel, supaya base URL tak terjangkau tidak menghasilkan cascade timeout.

### 2.4 Siklus heartbeat

Run dianggap **hidup** hanya bila `now - mtime <= 30 s`, pid masih hidup, dan command line proses memuat test runner. Selain itu basi; `e2e:sweep` menghapus run tanpa heartbeat yang lebih tua dari 24 jam, memakai `flock`, dan membatasi jumlah run yang diproses per invokasi.

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

---

## 5. Batas yang harus dinyatakan

1. **Server `:3000` menyajikan root checkout, bukan worktree.** Run di §4 karena itu menguji **kode aplikasi root** dengan **kode test dari branch**; hanya `H-auth-required` yang terpengaruh dan di sana ia merah. Batas itu ditutup di §4.4 lewat container kedua di `:3001` yang menyajikan worktree. Yang masih tersisa: **full suite dua project** baru wajib dijalankan dari root **setelah merge**, karena container sementara itu tidak dijalankan untuk seluruh spec.
2. **Test e2e tidak ikut `tsc` proyek.** `tsconfig.json` hanya meng-`include` `resources/js/**`, dan `@types/node` tidak ada di `node_modules`. Pemeriksaan tipe dilakukan dengan `tsconfig` sekali-pakai di luar commit + `@types/node` yang dipasang ke `/tmp` (bukan dependency proyek): hasilnya 2 error nyata di `heartbeat.ts` yang langsung diperbaiki (§3 baris 4), lalu exit 0. Agar gate ini permanen, `@types/node` harus jadi devDependency + script `test:e2e:types`; itu instalasi paket dan **belum dilakukan tanpa persetujuan**.
3. **Empat perintah artisan tidak dipanggil TS**: `check:row-ownership`, `e2e:derive-key`, `e2e:http`, `e2e:inspect`. Disediakan untuk observasi manual; sengaja tidak dihapus.
4. **Probe base URL memakai timeout tetap 2 s** (`assertBaseUrl`). Nilainya sengaja ketat supaya host mati gagal cepat, dan tidak dibuat configurable karena belum ada environment yang butuh angka lain. Konsekuensinya: kalau halaman terasa lambat, jangan naikkan timeout ini — cari dulu penyebabnya (§4.2).

---

## 6. Perintah harian

```bash
npm run test:e2e:setup            # login admin, tulis tests/e2e/.auth/admin.json
npm run test:e2e:desktop          # seluruh spec desktop
npm run test:e2e:mobile           # seluruh spec mobile
npm run test:e2e -- --list        # cek pemetaan project tanpa menjalankan
```

Wajib diset sebelum run: `E2E_BASE_URL`, `E2E_ADMIN_EMAIL`, `E2E_ADMIN_PASSWORD`, dan `E2E_ARTISAN` bila artisan tidak dijalankan dari container default. Untuk worktree, `E2E_ARTISAN` harus menunjuk workdir worktree — kalau tidak, perintah artisan menyentuh root checkout dan fixture akan salah sasaran.
