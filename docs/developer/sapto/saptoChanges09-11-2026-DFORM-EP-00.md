# Sapto Changes — 9 November 2026 (EP-00 CI Bootstrap)

## Ringkasan

CI GitHub Actions merah di **bootstrap**, bukan di test: job PHPUnit dan Bundle
budget gagal saat `composer install` karena `php artisan package:discover`
mengevaluasi signature `stress:seed`, yang mereferensikan
`Database\Seeders\Stress\StressDatasetSeeder::DEFAULT_SCALE` — class yang
sengaja di-gitignore (`/database/seeders/Stress/*`).

Perbaikan: konstanta `DEFAULT_SCALE` dipindah ke class committed
`App\Support\Stress\StressDatasetScale`; signature command membaca class itu.
Seeder stress lokal tetap ada dan meng-alias konstanta yang sama sebagai sumber
tunggal. Command tidak disembunyikan dari discovery, dan dataset stress tetap
tidak di-commit.

| Issue | Commit |
| ----- | ------ |
| EP-00 (`PRG-00-03`) | `3b622fc` — `fix: decouple stress seed signature from gitignored seeder` |

Lane map working tree: `docs/big-changes/program/EP-00-lane-map.md` (lokal,
`docs/big-changes/` di-gitignore).

## Akar masalah (bukti)

- Run `42458244386` (8 November 2026): job **PHPUnit Unit** dan **Bundle budget**
  berhenti di langkah `composer install` dengan
  `Class "Database\Seeders\Stress\StressDatasetSeeder" not found`.
- `composer.json` menjalankan `@php artisan package:discover --ansi` pada
  `post-autoload-dump`, sehingga kegagalan terjadi sebelum test dimulai.
- `app/Console/Commands/Stress/SeedStressDatasetCommand.php` (sebelum):
  `{--scale='.StressDatasetSeeder::DEFAULT_SCALE.' : ...}`. Signature dievaluasi
  saat command di-instansiasi oleh console kernel, jadi class yang tidak ada di
  checkout CI memblokir seluruh aplikasi.
- `.gitignore` mengabaikan `/database/seeders/Stress/*`; seeder 1 juta baris
  memang tidak boleh masuk repo.

## Perubahan

| File | Perubahan |
| --- | --- |
| `app/Support/Stress/StressDatasetScale.php` (baru) | `public const DEFAULT_SCALE = 1_000_000;` + dokumentasi singkat mengapa class ini committed. |
| `app/Console/Commands/Stress/SeedStressDatasetCommand.php` | Signature `--scale` membaca `StressDatasetScale::DEFAULT_SCALE`; import class baru ditambahkan. Referensi `StressDatasetSeeder` lain tetap di method body (baru dievaluasi saat command dijalankan, bukan saat discovery). |
| `database/seeders/Stress/StressDatasetSeeder.php` (lokal, tidak di-commit) | `DEFAULT_SCALE` menjadi alias `StressDatasetScale::DEFAULT_SCALE` agar tidak ada dua angka default. File ini tetap gitignored. |

Tidak ada perubahan route, policy, schema, migration, atau perilaku `stress:seed`.

## Keputusan desain

| Keputusan | Alasan / alternatif yang ditolak |
| --- | --- |
| Class committed `App\Support\Stress\StressDatasetScale` | Alternatif "commit seeder stress" melanggar aturan dataset besar tidak di-commit. Alternatif "hapus command dari discovery" menyembunyikan kerusakan dan menghilangkan command di CI. Alternatif "hardcode 1_000_000 di signature" menduplikasi magic number dan melanggar aturan softcode. |
| Seeder lokal meng-alias class baru | Menjaga satu sumber angka default; kalau nilai diubah, command dan seeder tidak bisa divergen. |
| Command tetap terdaftar | `stress:seed` masih bisa dipakai lokal dan tetap terlihat di `php artisan list`; masalah bootstrap diperbaiki di akar, bukan disembunyikan. |
| Tanpa job `bootstrap` terpisah di workflow | Langkah pertama job PHPUnit sudah `composer install`, yang menjalankan `package:discover`; kegagalan bootstrap tetap terlihat di job yang sama. Penambahan job tetap dibahas di `PRG-00-04`. |

## Verifikasi

- `composer install --prefer-dist --no-progress --no-interaction` (container
  `d_form_app`) dengan folder `database/seeders/Stress` **dipindah sementara** ke
  `/tmp/opencode/stress-seeders-backup-*` → **exit 0**, `package:discover` sukses
  (101 packages).
- `php artisan package:discover --ansi` eksplisit tanpa folder Stress → **exit 0**.
- `php artisan list` tanpa folder Stress → `stress:seed` tetap terdaftar
  (signature parse sukses).
- Folder `database/seeders/Stress` dipulihkan setelah pengujian; `git status`
  hanya memuat class baru dan command yang berubah.
- `php -l` kedua file → tidak ada syntax error.
- Pint (2 file) → **PASS, PSR 12**.

## Risiko & rollback

- Rollback: revert dua file dan hapus class baru; tidak ada perubahan schema,
  data, atau konfigurasi runtime.
- Nilai default tetap 1.000.000; tidak ada perubahan perilaku seed.
- Class baru berada di `app/Support/`, namespace `App\Support\Stress`, sehingga
  ikut PSR-4 `App\` dan tidak memerlukan registrasi service provider.

## Workflow 7 job (`PRG-00-04`)

`.github/workflows/quality.yml` sekarang menjalankan:

| Job | Blocking | Isi |
| --- | --- | --- |
| `lint` | ya | ESLint |
| `test` | ya | PHPUnit Unit |
| `frontend-unit` | ya | generate Wayfinder → Vitest → `tsc -p tsconfig.vitest.json` |
| `formatting` | tidak | Pint + Prettier (report-only sampai baseline bersih) |
| `bundle-budget` | ya | build + `check-bundle-budget.mjs` |
| `e2e-types` | ya | `tsc -p tsconfig.e2e.json` |
| `e2e-runtime` | ya | SQLite + server + subset Playwright desktop/mobile |

`frontend-unit` men-generate Wayfinder lebih dulu karena `lib/routes.ts`
mengimpor output generated yang gitignored. `e2e-runtime` memakai subset fokus:
autosave/builder, QR scan, tracking OpRec, email idempotency, broadcast gate-off,
dan dua flow submission; suite penuh menunggu `FE-AUDIT-004-F4/F5` dan
`INFRA-P3-023`.

**Status validasi:** `Needs verification` — `gh run list` memerlukan push ke
GitHub, dan push belum diotorisasi. Validasi lokal yang sudah dilakukan: YAML
parse sukses (7 job), semua spec yang dirujuk ada, dan gate non-E2E hijau di
worktree.
