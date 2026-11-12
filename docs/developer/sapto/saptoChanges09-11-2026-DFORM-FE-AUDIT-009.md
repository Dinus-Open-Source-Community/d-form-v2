# Sapto Changes — 9 November 2026 (FE-AUDIT-009)

## Ringkasan

`resources/js/lib/routes.ts` sekarang adapter tipis di atas Wayfinder generated.
Registry manual yang menyimpan literal path dihapus, tetapi API `routes.*`
dipertahankan sehingga 75 call site tidak berubah. Route `/admin` diberi nama
`admin.index` agar ikut di-generate. Satu-satunya literal path yang tersisa
adalah blok broadcast dorman (`FEATURE_BROADCAST=false`) yang dijaga test sumber.

| Issue | Commit |
| ----- | ------ |
| FE-AUDIT-009 | `9a99665` — implementasi; hardening guard/helper test di commit lanjutan |

Laporan lengkap: `docs/big-changes/patching-ticket/FE-AUDIT-009/patching-ticket.md`.

## Akar masalah (bukti)

- `resources/js/lib/routes.ts` (271 baris) adalah registry manual yang menulis
  path sendiri, paralel dengan Wayfinder (`resources/js/routes/**`).
- 75 file mengimpor `@/lib/routes`, 19 mengimpor `@/actions`, 0 mengimpor
  `@/routes`: dua sumber hidup bersamaan tanpa kontrak bersama.
- Route `/admin` adalah closure tanpa nama, sehingga Wayfinder tidak punya
  helper untuk path yang dipakai `routes.admin.index`.

## Perubahan

| File | Perubahan |
| --- | --- |
| `resources/js/lib/routes.ts` | Adapter Wayfinder; helper `pathWithoutQuery`, `isSidebarNavActive`, `resolveNavbarFallbackBackHref` dipertahankan; broadcast literal diisolasi. |
| `routes/web/admin/index.php` | `->name('admin.index')` pada route `/admin`. |
| `resources/js/lib/__tests__/routes.test.ts` | 13 test: mapping (slug, nested ID, query, scan export, form fill, `/admin`), snapshot pasangan action, dan helper navigasi. |
| `resources/js/lib/__tests__/routesSourceGuard.test.ts` | Guard import `@/routes`; larangan literal path manual di luar allowlist broadcast, termasuk template literal; komentar di-strip tanpa merusak string. |
| `tests/e2e/fe-audit-009-route-source.desktop.spec.ts` | Baru: 7 test navigasi guest/admin + anomali 404 dan bebas 5xx. |
| `.github/workflows/quality.yml` | `frontend-unit` men-generate Wayfinder sebelum Vitest/tsc karena adapter mengimpor output gitignored. |

## Verifikasi

| Perintah | Hasil |
| --- | --- |
| `php artisan route:list --name=admin.index` | `GET\|HEAD /admin` |
| `npx vitest run resources/js/lib/__tests__/routes*.test.ts` | 15 passed, exit 0 |
| `npm run test:unit` | 29 files / 283 tests passed, exit 0 |
| `npm run typecheck` | exit 0 |
| `npm run lint` | exit 0 |
| `npm run test:e2e:types` | exit 0 |
| `npm run build` (container) | exit 0 |
| bundle budget | entry 15.565 B gzip, chunk terbesar 145.58 kB gzip |
| Playwright spec baru | 8 passed (setup + 7), exit 0 |

Catatan koreksi rencana: navbar landing tidak memuat tautan recruitment, jadi
test guest menguji halaman `/recruitment` langsung sebagai ganti tautan navbar.

## Dampak & risiko

- URL akhir tidak berubah; hanya cara string dibangun.
- Tidak ada dependency baru dan tidak ada migration.
- Risiko sisa: 19 impor `@/actions` masih langsung; migrasi menunggu tiket
  boundary berikutnya dan tidak termasuk acceptance criteria tiket ini.
- Generated Wayfinder gitignored: `typecheck`/`test:unit` lokal memerlukan
  build/generate lebih dulu. CI `frontend-unit` sudah men-generate.
- `package-lock.json` (+388 baris tree Vitest) adalah byproduct lane
  FE-AUDIT-002 yang diperlukan `npm ci`; tidak di-revert dan tidak di-commit
  oleh tiket ini.
- Rollback: revert satu commit; tidak ada state atau data yang berubah.

## Temuan di luar scope

| Ticket | Prioritas | Ringkas |
| --- | --- | --- |
| `FE-AUDIT-009-F1` | P3 | Literal path di luar adapter: `useBuilderImageUpload.ts:34`, `Broadcasts/Show.vue:156`, `Scan/Global.vue:36-43`, `InterviewerCreateSheet.vue:56`; guard repo-wide/ESLint. |
| `FE-AUDIT-009-F2` | P3 | ~23 key adapter dead/test-only dan `.url()` eager saat import; bersihkan setelah `FE-AUDIT-003-F2`. |
| `FE-AUDIT-009-F3` | P3 | Typecheck/unit clean checkout belum men-generate Wayfinder; selaras `AUDIT-P3-011`. |
