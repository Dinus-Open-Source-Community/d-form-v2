# Sapto Changes — 9 November 2026 (FE-AUDIT-002)

## Ringkasan

Frontend kini punya unit-test harness Vitest yang lengkap: konfigurasi
`vitest.config.ts`, `tsconfig.vitest.json`, script `test:unit` /
`test:unit:coverage` / `typecheck:unit`, coverage baseline, 10 file test baru
untuk `lib/` dan hook kritis, serta gate CI **blocking** `frontend-unit`.
Suite akhir **22 file / 229 test hijau**; baseline coverage **24,19% lines**
(adapters 83,38%, lib 29,18%, hooks 8,37%). Tidak ada source produksi yang
diubah.

| Issue | Commit |
| ----- | ------ |
| FE-AUDIT-002 | `b71e0ef` — `test: add vitest harness for frontend unit tests` |

Laporan lengkap: `docs/big-changes/patching-ticket/FE-AUDIT-002/patching-ticket.md`.

## Akar masalah (bukti)

- `package.json` (HEAD) tidak punya script unit test dan tidak punya
  Vitest/Jest.
- Tidak ada file `resources/js/**/*.test.ts` / `*.spec.ts` di HEAD.
- `.github/workflows/quality.yml` hanya menjalankan ESLint, PHPUnit Unit,
  formatting report-only, dan bundle budget.
- Pure function (parser, mapper, formatter, validasi upload) tidak punya test
  minimum, sehingga regresi hanya tertangkap E2E atau tidak sama sekali.

## Perubahan

| File | Perubahan |
| ---- | --------- |
| `vitest.config.ts` (baru) | env `node`, alias `@`, include `resources/js/**/*.test.ts`, coverage v8 untuk `lib/`/`adapters/`/`hooks/`. |
| `tsconfig.vitest.json` (baru) | Extends `tsconfig.json`; `types: ["node","vite/client"]`; include `types/**/*.d.ts`, `adapters/**`, `**/__tests__/**`. |
| `package.json` | `test:unit`, `test:unit:coverage`, `typecheck:unit`; devDependency `@vitest/coverage-v8@5.1.0`. |
| `package-lock.json` | Lock konsisten (`npm ci --dry-run` exit 0). |
| `.gitignore` | `/coverage`; entri `.sokudo/` dikembalikan. |
| `.github/workflows/quality.yml` | Job `frontend-unit` blocking: `npm ci` → `test:unit` → `typecheck:unit`. |
| `resources/js/lib/__tests__/` (9 file) | Test `pagination`, `qrScanUi`, `formRegistrationMetadata`, `http-retry-after`, `isHttpUrl`, `formFieldMetadata`, `error-message`, `uploadValidation`, `routes`. |
| `resources/js/hooks/__tests__/useOtpCountdown.test.ts` | Test hook hitung mundur OTP (offset server, expiry, resend, lock, interval). |

Tidak ada perubahan backend, route, schema, migration, tampilan, atau
`AGENTS.md`/`SKILL.md`.

## Keputusan desain

| Keputusan | Alasan / alternatif yang ditolak |
| --- | --- |
| Vitest, bukan Jest | Satu toolchain dengan Vite 7; alias `@` dan ESM/TS tanpa transform kedua. |
| Environment `node`, bukan jsdom | Test menyasar pure function dan hook non-render; global browser di-stub per test. jsdom menambah dependency tanpa manfaat. |
| Coverage `@vitest/coverage-v8` (dev-only) | Provider resmi; disetujui user; tidak masuk bundle produksi. Alternatif tanpa angka % ditolak karena acceptance meminta baseline. |
| Job CI terpisah | Membedakan kegagalan Node dari PHP dan mudah di-rollback; tidak mencampur runner. |
| `typecheck:unit` memakai `tsc` subset | `vue-tsc` sudah dijalankan terpisah; include `lib/`/`hooks/` root sengaja tidak dipakai karena `types: ["node"]` mengubah tipe timer global dan memunculkan error pada hook lain. |

## Verifikasi

| Perintah | Hasil |
| --- | --- |
| `npm run test:unit` | **exit 0** — 22 file / 229 test passed |
| Test milik tiket (10 file) | **exit 0** — 143 test passed |
| `npm run typecheck:unit` | **exit 0** |
| `npm run lint` | **exit 0** |
| `npm run typecheck` | **exit 0** |
| `npm run test:unit:coverage` | **exit 0** — lines 24,19% / statements 24,49% / functions 22,84% / branches 27,62% |
| `npm run build` (container) | **exit 0**; entry 34,8 kB gzip, 237 chunk, non-entry terbesar 142,2 kB gzip |
| `node scripts/build/check-bundle-budget.mjs` | **exit 0** — budget terpenuhi |
| E2E terisolasi (`:8020`, runId sendiri) | **9 passed, exit 0** — setup 1, adapter-boundaries 4 (termasuk 422 anomali), harness-projects 2, fe-audit-003 2 (scan manual invalid tanpa 5xx) |
| `recruitment-applicant-list` terisolasi | **9 passed**, termasuk `input anomali ditolak validasi tanpa response 5xx` |

Catatan: run gabungan pasca-build sempat gagal karena lane lain menjalankan
Playwright bersamaan pada `admin.json`/`test-results`/SQLite yang sama
(`INFRA-P3-023`). Run terisolasi bersih; bukan regresi perubahan ini.

## Dampak & risiko

- Tidak ada perubahan behavior produksi; hanya test, konfigurasi test, CI, dan
  devDependency.
- Bundle tidak terpengaruh: entry Vite tidak mengimpor test/coverage; manifest
  build tidak memuat chunk test.
- **Commit sequencing:** `resources/js/lib/error-message.ts` (milik lane lain)
  mengimpor untracked `@/lib/isRecord` dan `@/types/http`. FE-AUDIT-002 harus
  di-commit setelah lane tersebut, atau file itu ikut di-commit pemiliknya.
- **Shared tree:** `qrScanUi.ts` dipindah lane lain (parser feed → adapter)
  selama sesi; coverage parser feed berpindah ke `adapters/http/__tests__/scan.test.ts`.
- Rollback: hapus job `frontend-unit`, dua file config, folder `__tests__`,
  script, devDependency, dan entri `.gitignore`. Tidak ada data/migration.

## Temuan di luar scope

`FE-AUDIT-002-F1` … `F6` dan `HYG-007` di `docs/big-changes/patching.md` §7.11
(NaN metadata, compaction `1000k`, dokumentasi countdown, side effect toast di
`lib/`, duplikasi parser `Retry-After`, encoding builder export, test duplikat).
