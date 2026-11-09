# Sapto Changes — 9 November 2026 (AUDIT-P1-012)

## Ringkasan

Static cycle dan pelanggaran arah dependensi ditutup di tiga titik:

1. `types/form.ts` tidak lagi menarik `components/.../fieldMapping`; `BackendField`
   dan `IFormBuilderSection` memakai kontrak `@/types/form-builder`.
2. `normalizeBannerSrc` pindah dari `components/modules/builder/formBanner.ts`
   ke `lib/publicStorageUrl.ts` sebagai `normalizePublicStorageUrl`, sehingga
   `lib/formFieldOptions.ts` tidak lagi mengimpor `components/`.
3. `lazyRichTextEditors` keluar dari `lib/` ke `components/core/`.

Guard arsitektur baru menutup cycle non-`components/ui`, arah `lib/`, `types/`,
`adapters/`, `components/core/`, dan `hooks/` lewat AST import graph.

| Issue | Commit |
| ----- | ------ |
| AUDIT-P1-012 | `9a66e8e` — `refactor: move http and browser seams behind adapters` |

Laporan lengkap: `docs/big-changes/patching-ticket/AUDIT-P1-012.md`.

## Akar masalah (bukti)

- Cycle `types/form.ts → components/modules/builder/fieldMapping →
  lib/formRegistrationMetadata → types/form`.
- `lib/recruitmentApplicantCapabilities.ts` mengimpor tipe dari component `.vue`.
- `lib/formFieldOptions.ts` mengimpor `formBanner` di `components/modules/builder/`.
- Tidak ada gate otomatis: pelanggaran hanya terlihat saat review manual.

## Perubahan

| File | Perubahan |
| --- | --- |
| `resources/js/types/form-builder.ts` | Menampung `BackendField` dan `IFormBuilderSection` (kontrak lintas layer). |
| `resources/js/types/form.ts` | Me-re-export kontrak dari `form-builder`; tidak mengimpor component. |
| `resources/js/lib/publicStorageUrl.ts` (baru) | `normalizePublicStorageUrl` menangani `data:`/`blob:`/`http(s)`/path absolut dan membuat skema asing inert. |
| `resources/js/components/modules/builder/formBanner.ts` | `normalizeBannerSrc` dihapus; pemakai memanggil helper `lib/`. |
| `resources/js/components/core/lazyRichTextEditors.ts` | Editor lazy pindah dari `lib/`. |
| `resources/js/types/recruitment.ts` (baru) | `IApplicationDetail` dan kontrak response recruitment pindah dari component. |
| `resources/js/__tests__/importBoundaries.test.ts` (baru) | Guard graph: cycle, arah `lib`/`types`/`adapters`/`core`/`hooks`, unresolved import (kecuali Wayfinder generated). |
| `resources/js/lib/__tests__/publicStorageUrl.test.ts` (baru) | Unit test normalisasi termasuk skema berbahaya dan input kosong. |
| `tests/e2e/audit-p1-012-asset-url.desktop.spec.ts` (baru) | 8 test browser: payload `javascript:`/`data:`/`vbscript:` inert, unggah SVG ditolak, traversal same-origin, editor lazy termuat. |

## Verifikasi

| Perintah | Hasil |
| --- | --- |
| `npx vitest run importBoundaries adapters lib` | **259 passed** (26 file), exit 0 |
| `npm run typecheck` | exit 0 |
| `npm run lint` | exit 0 untuk seluruh file lane; satu-satunya error tersisa ada di `hooks/useEventSearch.ts` milik FLOW-P1-002 (lane lain) |
| `npm run build` (container) | exit 0 |
| bundle budget | entry 15.3 kB gzip, chunk terbesar 142.2 kB gzip, 239 chunk ≤ 200 kB |
| Playwright `adapter-boundaries` + `fe-audit-005` + `fe-audit-006` + `audit-p1-012` | **20 passed, 1 flaky** (lolos retry), exit 0 |

## Dampak & risiko

- Tidak ada perubahan perilaku yang disengaja; normalisasi URL mempertahankan
  hasil untuk input valid.
- Guard baru bisa menangkap pelanggaran lama di luar scope; pelanggaran
  `components/ui/**` dikecualikan sesuai aturan AGENTS.
- Rollback: revert commit; guard dan helper baru tidak punya state.
