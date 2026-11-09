# Sapto Changes — 9 November 2026 (EP-01)

## Ringkasan

EP-01 menutup celah **fondasi kualitas**: CI yang hanya memblokir tiga gate,
baseline format yang kotor sehingga Pint/Prettier tidak bisa dijadikan gate,
harness E2E yang saling merusak saat dua run berjalan bersamaan, plus utang
hygiene/tipe/dokumentasi yang membuat review manual berulang.

Hasil: **11 job CI** (9 blocking), Pint + Prettier bersih dan blocking, E2E
state per-run, probe base URL tahan timeout, `e2e:cleanup` tidak lagi gagal FK,
82 tipe konsisten prefix, 61 exported function terdokumentasi, dan Feature suite
hijau penuh (**1014 passed, 8 skipped, 0 failed**).

| Issue | Commit |
| ----- | ------ |
| EP-01 (`AUDIT-P1-016`, `AUDIT-P2-001`, `AUDIT-P2-002`, `AUDIT-P3-001`, `AUDIT-P3-002`, `AUDIT-P3-008`, `FE-AUDIT-004-F4`, `FE-AUDIT-004-F5`, `FE-AUDIT-015`, `BE-P2-024`, `HYG-003`, `HYG-006`, `HYG-007`, `INFRA-P3-023`, `BE-P2-018`) | `cbef4a9` |

Laporan lengkap: `docs/big-changes/patching-ticket/EP-01/patching-ticket.md`.

## Baseline sebelum (terukur)

| Item | Sebelum | Sesudah |
| --- | --- | --- |
| `pint --test` | 18 issue | 0 |
| `prettier --check resources/js` | 444 file | 0 |
| CI job blocking | 3 (ESLint, Unit, budget) | 9 |
| `require.php` / `config.platform.php` | `^8.2` / tidak ada | `^8.4` / `8.4.27` |
| Feature suite | 1012 passed, **1 failed** (BE-P2-018), 8 skipped | 1014 passed, 0 failed, 8 skipped |
| Tipe **exported/cross-file** non-prefix (di luar `components/ui`/generated) | 75 | 0 (`InertiaConfig` = kontrak pihak ketiga; deklarasi lokal di dalam file tidak dihitung, sesuai acceptance "tipe lintas file") |
| Exported function tanpa dokumentasi (`lib`/`hooks`/`adapters`) | 51 | 0 |
| `components.json` alias composables | `@/lib/composables` (tidak ada) | `@/hooks` |
| E2E storageState | satu file global | `admin-<runId>.json` per run + prune >24 jam |
| Probe base URL E2E | HEAD 2000 ms tanpa fallback | HEAD 5 s → GET → retry |

## Akar masalah dan perbaikan

### `AUDIT-P1-016` — CI belum sesuai DoD

Gate lama hanya ESLint, Unit, dan bundle budget; tidak ada typecheck proyek,
Feature suite, audit dependency, maupun migration smoke. Workflow sekarang
memuat 11 job: `lint`, `test`, `feature-tests`, `composer-audit`,
`migration-smoke`, `frontend-unit`, `typecheck`, `formatting`, `bundle-budget`,
`e2e-types`, `e2e-runtime`. Sembilan blocking; `e2e-runtime` tetap
`continue-on-error` sampai run pertama di runner hijau (push belum diotorisasi).
Job `test` menambah guard runtime PHP terhadap `config.platform.php` dan
`composer check-platform-reqs`.

### `AUDIT-P2-002` — Platform PHP tidak dipin

`require.php` menjadi `^8.4` dan `config.platform.php=8.4.27`, sesuai image
produksi (FrankenPHP 8.4). Lock diregenerasi; sembilan paket symfony ikut
naik patch karena advisory GHSA (lihat di bawah).

### `AUDIT-P3-008` — Dependency abandoned

`composer audit` melaporkan tiga paket abandoned: `dragon-code/contracts`
(tanpa pengganti), `dragon-code/pretty-array` (→ `symfony/var-exporter`), dan
`dragon-code/support` (→ `illuminate/support`). Ketiganya **transitif dev-only**
dari rantai `laravel-lang`; `composer why` membuktikan tidak ada pemakaian
langsung. Audit gate memakai `--abandoned=report`: advisory memblokir, abandoned
tetap terlihat tetapi tidak memblokir. Penghapusan/penggantian paket menunggu
persetujuan user karena mengubah dependency runtime dev.

### `AUDIT-P3-001` — Prefix `I/G/T`

82 exported interface/type di-rename ke `I*`/`T*` di seluruh `resources/js`
(kecuali `components/ui/**` milik shadcn dan direktori generated). Acceptance
tiket dibatasi pada **tipe lintas file**; deklarasi lokal di dalam satu file
tidak di-rename pada tiket ini dan tidak dihitung sebagai sisa. Nama global
ambient `User`/`Props` dipindah ke `IUser`/`IProps` di `declare global` dengan
pola yang sama seperti `IEvent`. Satu exception tersisa: `InertiaConfig` —
nama itu milik kontrak module augmentation `@inertiajs/core`, bukan milik kita.

### `AUDIT-P3-002` — Dokumentasi exported function

61 exported function/hook di `lib/`, `hooks/`, dan `adapters/` mendapat satu
komentar singkat: efek samping (toast, storage, global state), precondition,
atau alasan untuk logika non-obvious. Tidak ada komentar dekoratif dan tidak ada
perubahan perilaku.

### `FE-AUDIT-004-F4` + `INFRA-P3-023` — Isolasi harness E2E

`playwright.config.ts`, `auth.setup.ts`, dan `support/authGuard.ts` sekarang
memakai satu resolver `adminStatePath()`: default
`tests/e2e/.auth/admin-<E2E_RUN_ID>.json`, `E2E_ADMIN_STATE` tetap override.
Global setup memangkas state run lain yang lebih tua dari 24 jam. Probe base URL
memakai `E2E_BASE_URL_TIMEOUT_MS` (default 5000), fallback GET saat timeout/405,
dan satu retry. Regression spec `session-isolation.desktop.spec.ts` membuktikan
`Cache::flush()` tidak memutus sesi admin.

### `FE-AUDIT-004-F5` — `e2e:cleanup` gagal FK

`FixtureScope::DELETE_ORDER` menempatkan `email_logs` paling awal dengan id yang
diselesaikan exact (lewat `event_id`/`form_answer_id` milik marker). Review
submission membuat log email yang mereferensikan event/user dengan
`restrictOnDelete`, sehingga tanpa urutan ini delete `events` gagal. Test
`E2eFixtureCleanupTest` menutup alur submit → review → cleanup dan memastikan
nol baris tersisa.

### `FE-AUDIT-015` — ESLint belum menegakkan arsitektur

Ditambah blok `no-restricted-imports` per folder sesuai tabel `AGENTS.md`:
`lib/` bebas layer UI/state/adapter, `types/` bebas layer runtime (dan `vue`
hanya type-only), `hooks/` bebas page/layout/`components/ui` dengan pengecualian
tiga modul builder yang memang diizinkan co-locate, `components/core/` bebas
module/page/layout/hook. Larangan `axios` dari FE-AUDIT-004 dipertahankan di
setiap override. Probe negatif via `eslint --stdin` membuktikan rule menolak
import terlarang.

### `BE-P2-024` — Fixture interview terduplikasi

`RecruitmentInterviewSessionFactory` dan `RecruitmentInterviewFactory` (+ state
`secondary()`) ditambahkan; empat test Recruitment memakainya tanpa mengubah
assertion.

### `HYG-003`, `HYG-006`, `HYG-007`

- `HYG-003`: password interviewer dibuat di dalam script (`Str::password()`),
  script dikirim lewat stdin, hanya plaintext acak yang keluar via stdout untuk
  test 403. Kredensial admin tidak lagi muncul di `ps`.
- `HYG-006`: alias `composables` di `components.json` menunjuk `@/hooks`.
- `HYG-007`: `domQuery` memakai kontrak struktural `IFocusableElement` /
  `IScrollableElement`; double assertion `as unknown as Element` dihapus.

### `BE-P2-018` — blocker di luar daftar awal EP-01

Gate Feature tidak bisa blocking selama tiket ini merah, jadi diperbaiki di sini
(1 konteks: EP-01 = CI hijau). `hasReplayableNotification()` sebelumnya hanya
membaca log email **terbaru**; setelah resend `confirmation`, log non-resendable
menjadi terbaru sehingga hasilnya `false`. Sekarang query mencari **salah satu**
log `Sent` yang tipenya memetakan ke template resendable; aturan interview tetap
mensyaratkan interview ada, dan allowlist template tidak berubah.

## Perubahan dependency keamanan (dipisah dari EP-01)

Gate `composer audit` tidak bisa blocking selama 7 advisory symfony terbuka.
Sembilan paket symfony naik patch (0 install, 0 remove): `http-foundation`,
`http-kernel`, `routing` 7.4.21 → 7.4.23; `yaml` 8.1.9 → 8.1.11; ditambah
`error-handler`/`var-dumper`/contracts. Dampak yang disengaja: `IpUtils`
memperlakukan IPv4-mapped IPv6 sebagai IPv4 tertanam, sehingga peer
`::ffff:<private>` kini dibandingkan terhadap CIDR IPv4 di `trusted_proxies`.
CIDR tidak berubah; peer mapped-public tetap tidak dipercaya (test
`test_ipv4_mapped_ipv6_peer_outside_trusted_cidr_is_ignored`). Perubahan ini
disarankan masuk commit `security:` terpisah dari commit EP-01 agar risiko
dependency tidak tersembunyi di commit formatting/CI.

## Verifikasi

| Perintah | Hasil |
| --- | --- |
| `pint --test` | exit 0 |
| `prettier --check "resources/js/**/*.{ts,vue,js}"` | exit 0 |
| `npm run typecheck` | exit 0 |
| `npm run typecheck:unit` | exit 0 |
| `npm run lint` | exit 0 |
| `npm run test:e2e:types` | exit 0 |
| `npx vitest run` | 30 file / **306 test** passed |
| `php artisan test --testsuite=Unit` | **175 passed** (576 assertions) |
| `php artisan test --testsuite=Feature` | **1014 passed, 8 skipped, 0 failed** (8334 assertions) |
| `npm run build` (container) | exit 0; budget entry ≤ 350 kB gzip, tidak ada chunk > 200 kB gzip |
| `composer validate --strict` | exit 0 |
| `composer audit --locked --abandoned=report` | exit 0 (0 advisory, 3 abandoned dilaporkan) |
| `composer check-platform-reqs` | exit 0 (php 8.4.27) |
| Playwright `session-isolation` | 3 passed (setup + 2) |
| Playwright `environment-probe` + `auth-guard` | 10 passed |
| Playwright `recruitment-interview-export` | 7 passed (retries=0) |

## Dampak & risiko

- **Perubahan dependency**: sembilan paket symfony naik patch (0 install,
  0 remove) karena advisory GHSA. `TrustedProxyTest` diperbarui ke perilaku baru
  yang disengaja: peer IPv4-mapped dibandingkan sebagai IPv4 tertanam; peer
  mapped-public di luar CIDR tetap tidak dipercaya.
- **Formatting repo-wide** menyentuh banyak file tanpa perubahan perilaku; gate
  `formatting` kini blocking sehingga commit berikutnya wajib terformat.
- **Gate baru bisa merah pada push pertama** jika env runner berbeda; bukti
  lokal dicatat di atas. `e2e-runtime` sengaja report-only sampai hijau sekali.
- Rollback: revert commit EP-01; tidak ada migration atau perubahan schema.

## Temuan di luar scope

- 3 paket `dragon-code/*` abandoned: rekomendasi penggantian sudah ditulis,
  eksekusi menunggu persetujuan user (mengubah dependency).
- `components/ui/**` masih memakai nama tipe lokal tanpa prefix (shadcn/wrapper
  internal) — di luar aturan karena folder itu tidak boleh diedit langsung.
- `IBuilderImageUploadResult` (hook) identik dengan tipe adapter bernama sama —
  kandidat dedup, bukan bagian rename-only.
