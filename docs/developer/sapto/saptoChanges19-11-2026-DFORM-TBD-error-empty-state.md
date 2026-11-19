# DFORM-TBD: Error state dan empty state berbasis SVG

| Issue                                  | Commit                                                          |
| -------------------------------------- | --------------------------------------------------------------- |
| DFORM-TBD (direct request PM, no Jira) | Uncommitted at authoring; final hash recorded in session report |

Klasifikasi: `feature` (komponen UI reusable).

## Ringkasan (TL;DR)

Lottie pada halaman error dan empty state diganti dua ilustrasi SVG unDraw yang dioptimalkan SVGO. Tiga komponen inti: `AppStatePanel` (shell presentational), `ErrorState` (peta status HTTP ke copy), dan `EmptyState` (varian empty, search, filter, not-found). `pages/Error.vue` sekarang tipis, dan empat page dashboard memakai `EmptyState` baru. Tiga aset Lottie yang tidak dipakai lagi dihapus.

## Perubahan

| File                                                                        | Perubahan                                                                                                  |
| --------------------------------------------------------------------------- | ---------------------------------------------------------------------------------------------------------- |
| `public/illustrations/state-error.svg`                                      | baru; unDraw "Server Error", SVGO 1.834 B, aksen `#2a81dc`                                                 |
| `public/illustrations/state-empty.svg`                                      | baru; unDraw "No Data", SVGO 1.953 B, aksen `#2a81dc`                                                      |
| `resources/js/types/state.ts`                                               | baru; `TStateIllustration`, `TEmptyStateKind`, `TErrorStateStatus`, `IStatePanelProps`, `IEmptyStateProps` |
| `resources/js/lib/stateContent.ts`                                          | baru; `resolveErrorStateContent`, `resolveEmptyStateContent` (fungsi murni)                                |
| `resources/js/components/core/AppStatePanel.vue`                            | baru; varian `page`/`inline`, ilustrasi `eager`+`fetchpriority=high` atau `lazy`, gambar dekoratif         |
| `resources/js/components/core/ErrorState.vue`                               | baru; copy 403/404/409/429/500/502/503 + default, aksi Kembali dan tautan beranda                          |
| `resources/js/components/core/EmptyState.vue`                               | baru; copy default per kind, `title`/`description` pemakai lama menimpa default                            |
| `resources/js/pages/Error.vue`                                              | Lottie dan map copy lokal dihapus, memakai `ErrorState`                                                    |
| `resources/js/pages/Dashboard/Events/Index.vue`                             | `EmptyState` core, `kind="filter"`                                                                         |
| `resources/js/pages/Dashboard/Events/Archived.vue`                          | `EmptyState` core, `kind="filter"`                                                                         |
| `resources/js/pages/Dashboard/Recruitment/MyInterviews/Index.vue`           | `EmptyState` core, `kind="filter"`                                                                         |
| `resources/js/pages/Dashboard/User/Events.vue`                              | `EmptyState` core, `kind="empty"`                                                                          |
| `resources/js/components/modules/dashboard/EmptyState.vue`                  | dihapus; tidak ada pemakai tersisa                                                                         |
| `resources/js/lib/lotties.ts`, `resources/js/types/lottie.ts`               | entri `errorState`, `error404Page`, `emptyData` dihapus                                                    |
| `public/lotties/error-state.json`, `error-404-page.json`, `empty-data.json` | dihapus (668.972 B)                                                                                        |

Lottie landing, builder, dan CTA tidak berubah.

## Perilaku

- Status HTTP di luar peta (mis. 418) memakai copy default "Terjadi kesalahan".
- `EmptyState kind="search"` menyertakan query yang sudah di-trim di judul; query kosong jatuh ke "Tidak ada hasil".
- `title`/`description` kosong dari pemakai lama tidak menghasilkan heading kosong.
- Ilustrasi dekoratif (`alt=""`, `aria-hidden="true"`); halaman error memakai satu `h1`, empty state inline memakai `p`.
- Tidak ada dark mode khusus pada scope ini.

## Verifikasi

| Pemeriksaan                                                         | Hasil                                                                     |
| ------------------------------------------------------------------- | ------------------------------------------------------------------------- |
| `npx vitest run`                                                    | 113 file, 1152 test passed                                                |
| `npm run typecheck`                                                 | exit 0                                                                    |
| `npm run lint`                                                      | exit 0, 0 error (1 warning pre-existing di `formBuilderAutosave.test.ts`) |
| `npx playwright test tests/e2e/state-pages.spec.ts`                 | 5 passed (chromium-desktop + chromium-mobile)                             |
| `npx playwright test tests/e2e/fe-audit-013-lottie.desktop.spec.ts` | 1 passed, landing Lottie tetap jalan                                      |
| `podman exec -i -w /app d_form_app npm run build`                   | exit 0                                                                    |
| Ukuran aset                                                         | 1.834 B dan 1.953 B (batas 30 kB)                                         |
| Chunk halaman Error                                                 | 0,74 kB gzip / 1,31 kB raw                                                |
| Entry JS                                                            | 15,86 kB gzip / 50,30 kB raw (sebelum 15,87 / 50,26)                      |
| CLS halaman 404                                                     | 0,0169 mobile, 0,0003 desktop                                             |

Lighthouse belum bisa dijadikan bukti di environment ini. Penyebabnya `APP_DEBUG=true` (PHP Debugbar menyuntik aset render-blocking dan badge berkontras rendah) dan `public/hot` (Vite menyajikan modul dev, bukan `public/build`). Pengukuran dev mencatat performance 38, accessibility 96, best-practices 96, SEO 45; satu-satunya kegagalan a11y adalah kontras badge `phpdebugbar`, dan kegagalan best-practices adalah `ERR_NAME_NOT_RESOLVED`. Angka ini tidak mewakili produksi dan tidak diklaim sebagai 100. Pengukuran ulang harus dilakukan pada build produksi (`APP_DEBUG=false`, tanpa Vite dev).

## Catatan

- `public/illustrations/` mengikuti pola aset statis yang sudah ada di `public/file-types/`.
- Lisensi unDraw sudah dibaca di `undraw.co/license`: gratis komersial, tanpa atribusi, boleh dimodifikasi.
- Design doc: `docs/superpowers/specs/2026-11-19-error-and-empty-state-design.md`.
- Plan: `docs/superpowers/plans/2026-11-19-error-and-empty-state.md`.

## Revisi

- `/joined` (page `Dashboard/User/Events.vue`) memakai `EmptyState` dengan `:surface="false"`, sehingga tidak ada kartu putih di belakang ilustrasi. Prop `surface` default `true`, jadi tiga page dashboard lain tidak berubah.
