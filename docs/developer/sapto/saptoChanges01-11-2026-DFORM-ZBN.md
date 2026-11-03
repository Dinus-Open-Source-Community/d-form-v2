# Sapto Changes — 1 November 2026 (DFORM-ZBN)

## Ringkasan

Menyelaraskan `CONTEXT.md` dengan kondisi aktual branch `dev-zapp`, terutama
inventaris composable frontend, lazy loading page, komponen SEO, baseline entry
bundle, dan jumlah test class.

| Issue                                                              | Commit    |
| ------------------------------------------------------------------ | --------- |
| dform-zbn — Sinkronkan CONTEXT.md dengan kondisi dev-zapp sekarang | `aae87b3` |

## Akar masalah

- `CONTEXT.md` menyatakan `resources/js/hooks/` belum ada dan hanya mencatat
  `utils/composables/`.
- Dokumentasi masih menggambarkan `app.js` sebagai eager monolit 3.354 kB,
  padahal resolver page sudah lazy dan entry aktual jauh lebih kecil.
- `components/seo/` belum tercatat.
- Jumlah test class PHPUnit sudah bertambah dari angka yang terdokumentasi.

## Perubahan

- Mencatat 10 hook pada `resources/js/hooks/`, penggunaannya oleh `app.js`, dan
  17 composable legacy yang masih berada di `utils/composables/`.
- Mencatat `components/seo/SeoHead.vue` sebagai komponen SEO yang digunakan
  oleh page.
- Mengubah penjelasan resolver page menjadi lazy `import.meta.glob` dan
  mencatat entry aktual sebesar 76.762 byte raw dan 26.615 byte gzip.
- Memperbarui kontrak frontend agar mencerminkan dua area composable selama
  migrasi bertahap, memperbarui PHPUnit menjadi 123 test class, dan mencatat
  13 Playwright E2E spec yang sudah ada.
- Merapikan sisa angka inventaris §4.2/§11: 58 file `pages/` (57 page route + 1
  komponen anak), 35 direktori `components/ui/` (27 shadcn + 8 wrapper), 37 file
  `lib/`, dan daftar `components/modules/` yang sebenarnya.

## Keputusan dan alternatif

Pendekatan ini merupakan pilihan paling aman untuk tiket dokumentasi: fakta
diturunkan dari source dan artefak build saat ini tanpa memindahkan atau
merename composable yang masih dipakai. Alternatif migrasi massal ke `hooks/`
ditolak karena mengubah behavior dan scope tiket, serta membutuhkan test dan
plan migrasi terpisah.

## Verifikasi

- `find resources/js/hooks -maxdepth 2 -type f | wc -l`: 10.
- `app.js` terverifikasi mengimpor dua hook global dan memakai lazy glob tanpa
  opsi `eager`.
- Entry build terukur 76.762 byte raw dan 26.615 byte gzip.
- Source test terukur 109 class Feature dan 14 class Unit, total 123 test class
  dari 125 file PHP; satu file tanpa deklarasi class adalah support helper.
- `find resources/js/pages -name '*.vue' | wc -l`: 58 (57 route page + 1
  komponen anak); `find resources/js/components/ui -mindepth 1 -maxdepth 1 -type d
  | wc -l`: 35; `find resources/js/lib -maxdepth 1 -type f | wc -l`: 37.
- `tests/e2e` terukur memiliki 13 file spec dan `playwright.config.ts` sudah
  tersedia.
- Perubahan lokal yang sudah ada pada `.gitignore` dan
  `docs/drawing.excalidraw` tidak disentuh.
