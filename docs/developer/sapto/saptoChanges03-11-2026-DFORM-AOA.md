# Sapto Changes — 3 November 2026 (DFORM-AOA)

## Ringkasan

Budget bundle (entry ≤ 350 kB gzip, chunk ≤ 200 kB gzip) sebelumnya hanya dipenuhi manual. Sekarang ada guard yang gagal keras di CI: build dijalankan, lalu setiap file JS hasil build diukur ulang dengan gzip dan dibandingkan dengan budget.

| Issue       | Commit                                  |
| ----------- | --------------------------------------- |
| `dform-aoa` | `ci(build): enforce bundle size budget` |

## Baseline terukur

Diukur dari `npm run build` di container (`podman exec -i -w /app d_form_app npm run build`, Vite 7, 3 Nov 2026):

| Metrik                   | Nilai                                             | Budget | Status |
| ------------------------ | ------------------------------------------------- | ------ | ------ |
| Entry `assets/app-*.js`  | 26,5 kB gzip                                      | 350 kB | lolos  |
| Chunk non-entry terbesar | 143,8 kB gzip (`assets/index-*.js`, 451,5 kB raw) | 200 kB | lolos  |
| Jumlah chunk non-entry   | 231                                               | -      | -      |

Angka ini menggantikan catatan ticket (entry 26 kB, chunk 142 kB); selisih berasal dari build aktual pada working tree saat tiket dikerjakan.

## Perubahan

- `scripts/build/check-bundle-budget.mjs` (baru): membaca `public/build/manifest.json`, mengukur gzip setiap `public/build/assets/*.js`, lalu gagal (`exit 1`) bila entry > 350 kB gzip atau ada chunk non-entry > 200 kB gzip. Budget adalah konstanta di satu tempat, bukan angka tersebar.
- `.github/workflows/quality.yml`: job blocking `Bundle budget` — PHP 8.4 + Composer (plugin Wayfinder menjalankan `php artisan wayfinder:generate`), Node 24, `npm ci`, `.env` + `key:generate`, `npm run build`, lalu script guard.

## Keputusan

- **Guard mengukur semua chunk non-entry, bukan hanya "chunk page".** Setelah Rollup menggabungkan dynamic import, manifest tidak lagi memisahkan chunk page dari chunk vendor secara andal; menegakkan budget pada seluruh chunk non-entry lebih ketat dan tetap menangkap regresi eager-import yang menjadi alasan tiket ini dibuat. Alternatif yang ditolak: menghitung total gzip per page beserta import statisnya — angka itu melewati 200 kB untuk beberapa halaman karena menghitung ulang vendor bersama (mis. `vendor-vue`), sehingga tidak sebanding dengan budget yang dinyatakan per chunk.
- **Ukur ulang gzip di script, bukan mem-parse output Vite.** Output build berbahasa manusia dan mudah berubah antar versi; `manifest.json` adalah kontrak yang stabil dan sudah dipakai Laravel.
- **Job CI terpisah.** Build penuh butuh PHP + Composer, sedangkan job ESLint cukup Node; menggabungkannya akan memperlambat gate yang sudah ada.

## Verifikasi

```text
$ node scripts/build/check-bundle-budget.mjs
entry assets/app-CBEYgVm8.js: 26.5 kB gzip (budget 350.0 kB)
largest non-entry chunks:
   143.8 kB  assets/index-BURsd5MA.js
    98.5 kB  assets/index-DVGUZA9i.js
    79.6 kB  assets/vue3-lottie.es-BCbJ6M4V.js
bundle budget ok: entry <= 350.0 kB, 231 chunks <= 200.0 kB
```

Uji negatif (salinan build di `/tmp`, satu chunk digemukkan dengan 3 MB data acak):

```text
bundle budget failed:
  - assets/index-BURsd5MA.js is 3074.7 kB, over 200.0 kB
exit=1
```

`npx prettier --check .github/workflows/quality.yml scripts/build/check-bundle-budget.mjs`: PASS.

## Batasan

Workflow GitHub Actions tidak dijalankan dari environment lokal; validasi memakai perintah yang sama dengan job (build di container + script guard). Baseline di atas berasal dari build lokal pada `dev-zapp`, bukan dari runner CI.
