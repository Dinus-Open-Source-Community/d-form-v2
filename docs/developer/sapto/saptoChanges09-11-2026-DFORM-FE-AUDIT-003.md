# Sapto Changes — 9 November 2026 (FE-AUDIT-003)

## Ringkasan

Seluruh **13 composable legacy** di `resources/js/utils/composables/` dipindah ke
`resources/js/hooks/` (nama domain dipertahankan), **69 import site di 54 file**
diarahkan ke `@/hooks/*`, `useAuth` diubah dari default menjadi named export, dan
barrel `resources/js/hooks/index.ts` dibuat. Folder legacy dihapus setelah nol
referensi. Tidak ada perubahan logika, route, response contract, schema, atau
dependency.

| Issue | Commit |
| ----- | ------ |
| FE-AUDIT-003 | _(diisi setelah commit)_ |

Laporan lengkap: `docs/big-changes/patching-ticket/FE-AUDIT-003/patching-ticket.md`.

## Akar masalah (bukti)

- `resources/js/utils/composables/` berisi 13 file (2.611 LOC).
- 69 import `@/utils/composables/*` tersebar di 54 file; `hooks/` sudah punya 10
  hook flat dan belum punya barrel.
- `useAuth.ts` satu-satunya default export; 12 file memakai `import useAuth from`.
- Satu cross-import internal: `useAutosaveSync.ts` → `useBuilderImageUpload.ts`.
- `rg -n "utils/composables" resources/js` menjadi bukti merah sebelum migrasi.

## Perubahan

| File | Perubahan |
| ---- | --------- |
| `resources/js/hooks/*` | 13 file di-`git mv` dari `utils/composables/` (rename 100%). |
| `resources/js/hooks/useAuth.ts` | `export default function useAuth` → `export function useAuth` (binding-only). |
| `resources/js/hooks/useAutosaveSync.ts` | Import internal diarahkan ke `@/hooks/useBuilderImageUpload`. |
| `resources/js/hooks/index.ts` (baru) | Barrel named export: 23 hook + helper/type publik yang dipakai caller. |
| 53 file `pages/`, `components/`, `layouts/` | Hanya baris import: `@/utils/composables/<name>` → `@/hooks/<name>`; 12 `useAuth` jadi named import. |
| `resources/js/utils/composables/` | Dihapus. |
| `tests/e2e/fe-audit-003-composable-migration.desktop.spec.ts` (baru) | E2E dashboard + anomali scan manual (`422`, feedback, tanpa page error/5xx). |

Tidak ada perubahan backend, migration, dependency, tampilan, atau
`AGENTS.md`/`SKILL.md`.

## Keputusan desain

| Keputusan | Alasan / alternatif yang ditolak |
| --- | --- |
| Pindah utuh ke `hooks/`, split `lib`/`adapters` ditunda | FE-AUDIT-003 hanya memindahkan logic stateful; split `useBuilderImageUpload` dicatat sebagai `FE-AUDIT-003-F1` agar satu tiket satu alasan berubah. |
| Flat `hooks/` | Konsisten dengan 10 hook yang sudah ada dan nama file sudah domain-specific. |
| Barrel eksplisit | Named export eksplisit menghindari ambigu; barrel belum diimpor sehingga tidak menambah bundle. |
| Hapus legacy setelah bukti | `rg` nol match + typecheck/lint hijau sebelum penghapusan. |

## Verifikasi

| Perintah | Hasil |
| --- | --- |
| `rg -n "utils/composables" resources/js` | 0 match |
| `rg -n "import useAuth from" resources/js` | 0 match |
| `npm run typecheck` | exit 0 |
| `npm run lint` | exit 0 |
| `npm run test:e2e:types` | exit 0 |
| `npm run build` (container) | exit 0; entry 76,76 kB raw / 26,79 kB gzip (identik baseline) |
| 3 chunk terbesar before/after | identik: `index` 462,34/145,58; `index` 334,65/99,40; `vue3-lottie` 314,47/80,99 (raw/gzip kB) |
| `npm run build:ssr` (container) | exit 0; manifest SSR meregenerasi path `hooks/` |
| E2E terisolasi (`:8020`, super-admin) | **14 passed, exit 0**: builder 3, dashboard+scan 2, recruitment applicant list 8, setup 1 |

Anomali: input manual scan tidak valid → `422`, UI “Tidak dapat diproses”,
tanpa `pageerror` dan tanpa 5xx. Spec baru lalu diperkuat (judul topbar dari
`useDashboardTopbar`) dan dijalankan ulang: 3 passed (setup + 2 test).

## Dampak & risiko

- Tidak ada perubahan behavior; hanya lokasi modul dan binding `useAuth`.
- Bundle tidak berubah (entry 26,79 kB gzip).
- Risiko sisa: `useBuilderImageUpload` masih memuat pure sanitizer + browser +
  HTTP; ditindaklanjuti di `FE-AUDIT-003-F1` / `FE-AUDIT-004`.
- Unit test hook belum ada karena runner Vitest adalah scope `FE-AUDIT-002`
  (Open) dan tidak dipasang pada tiket ini.
- Rollback: revert satu commit; tidak ada migration/data.

## Perbaikan lanjutan

Commit migrasi awal meninggalkan tiga import basi yang hanya terlihat saat
`vue-tsc` dijalankan pada tree hasil commit (worktree sudah memuat perbaikannya):
`FormSuccessMessageCard.vue`, `EventDashboardForm.vue`, dan
`pages/Dashboard/Broadcasts/Show.vue` masih mengimpor `@/lib/lazyRichTextEditors`.

- Dua file pertama diarahkan ke `@/components/core/lazyRichTextEditors`.
- `Show.vue` memakai `LazyBroadcastContentEditor`, export yang tidak lagi ada di
  modul core (modul core hanya mengekspor `LazyTipTapEditor`). Penggantinya
  `defineAsyncComponent(() => import('@/components/modules/dashboard/broadcasts/BroadcastContentEditor.vue'))`
  sehingga vendor Tiptap tetap dimuat sebagai chunk async.

## Temuan di luar scope

`FE-AUDIT-003-F1`, `FE-AUDIT-003-F2` (dead `Submissions.vue` +
`useFormSubmissionsPage`), `INFRA-P3-023` (state E2E global + probe HEAD),
`HYG-006` (`components.json` alias `composables` tidak ada). Detail di
`docs/big-changes/patching.md` §7.10.
