# Sapto Changes — 11 November 2026 (EP-06)

## Ringkasan

EP-06 menegakkan batas frontend: `lib/` murni (tanpa DOM/efek UI/paket runtime),
`hooks/` untuk state, `types/` untuk kontrak, `adapters/` untuk seam
browser/HTTP, `components/core/` hanya untuk komponen lintas modul, dan tidak ada
import silang modul. Guard baru berjalan di job ESLint **blocking** dan di
Vitest, sehingga pelanggaran menjadi error, bukan temuan audit.

| Issue | Commit |
| ----- | ------ |
| EP-06 (`AUDIT-P1-012` + F1–F2, `AUDIT-P3-006/007/009`, `FE-AUDIT-002-F1…F6`, `FE-AUDIT-003` + F2, `FE-AUDIT-004` + F1–F3, `FE-AUDIT-005-F2/F3`, `FE-AUDIT-009` + F1–F3, `FE-AUDIT-010/011/012/013`, `FLOW-P2-004/007`) | `c517245a` (refactor) + `3e214112` (chore) |

Laporan lengkap: `docs/big-changes/patching-ticket/EP-06/patching-ticket.md`.

## Perubahan

**Boundary yang ditegakkan mesin**

- `scripts/libRuntimePackageRule.js` menjadi satu-satunya implementasi aturan
  "`lib/` tidak boleh mengimpor paket runtime"; dipakai `eslint.config.js`
  (job blocking) **dan** Vitest (menjalankan config proyek lewat `ESLint#lintFiles`).
  Tidak ada lagi salinan regex yang bisa berbeda verdict.
- Guard cross-module (`importBoundaries`) memakai resolver path, mencakup `.vue`
  dan `.ts`, dan memakai daftar modul builder yang sama dengan ESLint.
- Guard DOM (`domBoundary`) membekukan empat file legacy `components/ui/**` dan
  menolak penggunaan baru `document|window|localStorage|sessionStorage|navigator|fetch`.
- Guard baru menolak literal path aplikasi (`/admin`, `/dashboard`, `/recruitment`,
  `/joined`, `/browse`) di runtime; URL wajib dari Wayfinder/adapter.
- `FE-AUDIT-009-F3`: preflight Wayfinder idempoten (`pretypecheck`,
  `pretypecheck:unit`, `pretest:unit`, `pretest:unit:coverage`) sehingga clean
  checkout bisa menjalankan gate tanpa langkah manual; `WAYFINDER_COMMAND`
  tersedia untuk host tanpa PHP.

**Pemisahan tanggung jawab**

- `lib/error-message.ts` dipecah: fungsi murni di `lib/`, toast ke
  `hooks/useToastNotifications` (18 file dimigrasikan; `lib/autosaveFailureToast.ts`
  dihapus). `lib/qrScanUi.ts` bebas Vue/lucide/browser; efek suara pindah ke
  `adapters/browser/feedback.ts`.
- `AppPagination` menjadi presentational; navigasi pindah ke
  `hooks/usePaginationNavigation` + helper murni `lib/pagination.ts` (9 call site,
  kontrak URL identik; `includePerPage` ikut di payload emit).
- `components/core/`: `UserAvatarFallback`, `FormParagraphContent`,
  `EventBannerImage`, `lazyTipTapEditor`; `PageHeader` dikembalikan ke
  `modules/builder/` (hanya satu konsumen).
- `hooks/useFormSubmissionReview` menjadi satu implementasi alur review
  (guard pending, 409/422/403/404, dedupe in-flight, reload key) dengan 12 unit test.
- Metadata builder/Inertia memakai tipe eksplisit (`TFormFieldRuleSet`,
  `IFormFieldMetadata`) + guard runtime; seluruh `as` di jalur itu dihapus.
- `lib/routes.ts` dipangkas 27 key mati (121 → 95 leaf key) dan memakai lazy
  getter; snapshot kontrak 95 key.

**Keamanan URL & data**

- `normalizePublicStorageUrl` menolak network-path (`//host`, `\\host`) **dan**
  karakter C0 (`\t`/`\n`/`\r`) yang dihapus parser WHATWG; cabang path absolut
  memakai `isInternalPath` yang sama dengan seam navigasi.
- `EventBannerImage` memakai normalizer itu (bukan aturan sendiri yang lebih
  lemah); fallback mentah `event.banner`/`banner_url` dihapus.
- `lib/dummyData.ts` (fixture) dihapus; dashboard memakai event nyata.

**Dependency & kode mati**

- `daisyui` dihapus dari `package.json` + `package-lock.json` (lockfile
  diregenerasi offline, `npm ls daisyui` kosong, `npm ci --offline --dry-run` lulus).
- Dihapus: `pages/Dashboard/Events/Forms/Submissions.vue`,
  `hooks/useFormSubmissionsPage.ts` (+test, barrel export), `utils/theme.js`,
  `lib/dummyData.ts`, `lib/icons.ts`, `components/core/lazyRichTextEditors.ts`,
  `hooks/index.ts` (barrel tanpa importer), dan **11 komponen Blade** tanpa
  referensi (5 + 6 dari review).

## Review dan perbaikan

`oracle` (arsitektur) dan `paladin` (keamanan) meninjau seluruh diff. Tidak ada
Critical/High keamanan. Temuan yang ditutup sebelum merge:

- **H1** `dashboardFixtures.ts` (389 baris) ternyata mati → dihapus, bukan dipindah.
- **H2** `EventBannerImage` menduplikasi URL policy dalam versi lebih lemah →
  delegasi ke `normalizePublicStorageUrl` (+ hapus fallback mentah).
- **H3** dua implementasi kebijakan import `lib/` yang sudah berbeda verdict →
  satu rule dipakai ESLint + Vitest.
- **M1** heading kosong tidak lagi kembali ke "Section Heading" → fallback dipulihkan + test.
- **M2** `includePerPage` manual → masuk payload emit.
- **M4** `PageHeader` satu konsumen di `core/` → kembali ke `builder/`, API mati dihapus.
- **M5/M6** `lib → @/constants` dilarang padahal didokumentasikan legal → diizinkan;
  DOM guard diperluas.
- **M7** hook review tanpa unit test → 12 test.
- **Paladin L1** `/\t/evil.example` masih lolos sebagai network-path → C0 ditolak + E2E.
- **L2/L3/L4/L5/L9/L10** alias deprecated, barrel mati, detail guard, docblock drift, tipe props.

## Residual

- `FE-AUDIT-013` CSP production **Needs verification** — production tidak disentuh;
  `eval` di `lottie-web` sudah diblokir `script-src` tanpa `unsafe-eval`, jadi
  kegagalan bersifat fail-closed.
- Empat file legacy `components/ui/**` tetap memakai DOM global dan dibekukan
  lewat allowlist (bukan legacy-only murni: `SimpleSelect`/`SidebarProvider`
  adalah komponen shell aktif) — keputusan didokumentasikan.
- Ada 1 flake PHP yang tidak reproducible: `RecruitmentFinalSelectionTest`
  (mails) gagal sekali pada full suite, lulus isolasi (23 test) dan pada full
  suite ulang (1.497 passed). Bukan area EP-06, dicatat `Needs verification`.
- `import()` non-literal tidak dapat diperiksa statis (didokumentasikan di rule).

## Verifikasi

| Perintah | Hasil |
| --- | --- |
| `npm run typecheck` / `typecheck:unit` | 0 / 0 |
| `npm run lint` (termasuk semua guard) | 0 |
| `npm run test:unit` | **619 passed** (57 file) |
| `npx prettier --check` | 0 |
| `npm run test:e2e:types` | 0 |
| `npm run build` + `check-bundle-budget.mjs` | PASS; entry ≤350 kB gzip, 236 chunk, max non-entry 143,8 kB |
| E2E desktop (banner, pagination, review, scan, auth, broadcast, Lottie, route source, adapter boundary, form boundary, builder) | **83 passed** (`--retries=0`) |
| `php artisan test` | **1.497 passed, 12 skipped** (11.619 assertions) |
| Pint | PASS, **772 file** |
| Bundle delta `lib/routes.ts` | chunk adapter 87.013 → 83.754 B raw / −442 B gzip; entry −15 B gzip |

Status index: seluruh 26 tiket EP-06 (termasuk `FE-AUDIT-009-F1`–`F3`) →
`In Review`. Tidak ada perubahan production/docker/CI.
