# Sapto Changes — 27 Oktober 2026 (DFORM-204)

Menegakkan gate otomatis pertama di CI: ESLint dan unit test menjadi blocking pada `dev`, `dev-zapp`, dan `temp`; formatting tetap terlihat tetapi belum memblokir; opsi `tsconfig.json` yang tidak valid diperbaiki.

> **Nomor tiket:** `TASK-204` (Sokudo). Dokumen Jira belum dibuat saat dikerjakan.

| Kolom  | Isi |
| ------ | --- |
| Issue  | TASK-204 |
| Commit | `chore(ci): tegakkan lint dan unit test di CI, perbaiki opsi tsconfig yang tidak valid` |
| Branch | `task-204-tegakkan-gate-lint-dan-unit-test-di-ci-perbaiki` |

---

## Ringkasan (TL;DR)

| Area | Sebelum | Sesudah |
| ---- | ------- | ------- |
| Trigger CI | `dev`, `temp` saja | `dev`, `dev-zapp`, `temp` (push + PR) |
| ESLint | tidak dijalankan | **blocking** (0 error hari ini) |
| Unit test PHP | tidak dijalankan | **blocking** (52 passed hari ini) |
| Pint + Prettier | report-only, di workflow terpisah | report-only, digeser ke `quality.yml` |
| `tsconfig.json` | `TS5103` sebelum typecheck | opsi tidak valid dihapus; `tsc` berjalan sampai error `.vue` yang memang diekspektasikan |
| Workflow files | `code_formatting.yml` | `quality.yml` (tunggal) |

## Baseline terukur (27-10-2026)

| Pemeriksaan | Hasil | Bisa blocking? |
| ----------- | ----- | -------------- |
| `npm run lint` (ESLint) | 0 error, 1 warning | **Ya** → gate |
| `php artisan test --testsuite=Unit` | 52 passed (325 assertions) | **Ya** → gate |
| `npx prettier --check "resources/js/**/*.{ts,vue}"` | 429 file belum terformat | Belum |
| `./vendor/bin/pint --test` | 24 file (tiket mencatat 19; baseline bertambah sebelum dikerjakan) | Belum |
| `npx tsc --noEmit` | 0 `TS5103`, lalu 164 `TS2307` untuk modul `*.vue` | Belum — butuh `vue-tsc` |
| `vue-tsc` | belum terpasang | Belum — instalasi paket butuh persetujuan user |

Alasan formatting dan typecheck **tidak** dijadikan blocking: keduanya sudah merah sejak hari pertama. Gate yang gagal sebelum ada regresi akan diabaikan orang, jadi lebih buruk daripada tidak ada gate. Formatting tetap dijalankan sebagai pelaporan (dan angkanya dicetak di log), lalu dijadikan blocking setelah baseline dibersihkan di tiket terpisah.

## Perubahan

| File | Perubahan |
| ---- | --------- |
| `tsconfig.json` | hapus `"ignoreDeprecations": "6.0"` (satu-satunya opsi tidak valid untuk TS 5.9.3) |
| `.github/workflows/quality.yml` | **baru** — job `lint` + `test` blocking, job `formatting` report-only, komentar baseline di dalam file |
| `.github/workflows/code_formatting.yml` | **dihapus**; isinya digeser ke `quality.yml` (alasan di bawah) |
| `docs/developer/sapto/README.md` | baris tabel untuk dokumen ini |

## Review keputusan (gate langkah 2)

- **Hapus `code_formatting.yml` atau biarkan?** Dihapus, isinya digeser ke `quality.yml`. Kalau dibiarkan, Pint + Prettier berjalan dua kali untuk setiap push/PR ke `dev`/`temp` dan tetap tidak terlihat di `dev-zapp`. Satu workflow = satu tempat untuk membaca gate. Riwayat file tetap ada di git.
- **PHP 8.4, bukan 8.3 seperti di tiket.** Ini temuan saat verifikasi: `composer.json` menulis `php: ^8.2`, tetapi `composer.lock` mengunci `endroid/qr-code ^8.4` dan `openspout ~8.4.0`. `composer install` di PHP 8.3 akan gagal platform requirement, artinya gate test merah sejak commit pertama. CI disamakan dengan runtime container (`PHP 8.4.27`).
- **Tanpa service MySQL.** `phpunit.xml` memaksa `DB_CONNECTION=sqlite` + `DB_DATABASE=:memory:`, jadi service database hanya menambah waktu start tanpa dipakai. Ekstensi `pdo_sqlite` + `sqlite3` sudah dipasang di job.
- **Tidak menambah `typecheck`/`vue-tsc`.** `tsc` polos menghasilkan 164 error palsu `TS2307` untuk impor `*.vue`, jadi itu bukan typecheck yang sah; memasang `vue-tsc` butuh persetujuan user dan di luar scope tiket. Dicatat sebagai follow-up.
- **Tidak memformat 429 file.** Itu akan membuat diff tiket ini tidak mungkin direview; pembersihan baseline adalah pekerjaan terpisah.

## Verifikasi lokal

Dijalankan di container `d_form_app` (checkout root; worktree tidak punya `node_modules`/`vendor`):

- `podman exec -i -w /app d_form_app npx eslint "resources/js/**/*.{ts,vue}"` → `0 errors, 1 warning` (warning lama: `ArrowLeft` tidak dipakai di `resources/js/pages/OpenRecruitment/Track/Edit.vue`).
- `podman exec -i -w /app d_form_app php artisan test --testsuite=Unit` → `Tests: 52 passed (325 assertions)`, durasi 1,13 s.
- `npx prettier --check "resources/js/**/*.{ts,vue}"` → `429 files` (angka baseline dikonfirmasi, bukan asumsi).
- `./vendor/bin/pint --test` → `24` file (bukan 19; selisih dicatat di komentar workflow dan tabel baseline).
- `npx tsc -p <worktree>/tsconfig.json --noEmit` → **0** `TS5103`; hanya `TS2307` untuk `*.vue` (bukti typecheck butuh `vue-tsc`).
- YAML workflow divalidasi dengan `python3 -c "import yaml; yaml.safe_load(...)"` → parse OK, jobs `lint`/`test`/`formatting`, trigger sesuai.
- `npm ci --dry-run` → `up to date`, tidak ada drift antara `package.json` dan `package-lock.json`.
- `actionlint` tidak tersedia di host maupun container, jadi validasi sintaks memakai parser YAML.

## Checklist

- [x] `tsconfig.json` tidak lagi memuat `ignoreDeprecations` yang tidak valid
- [x] `.github/workflows/quality.yml` valid dan menjalankan ESLint + unit test blocking pada `dev`, `dev-zapp`, `temp`
- [x] Tidak ada gate yang sudah merah sejak awal (ESLint 0 error, unit test 52 passed)
- [x] Formatting tetap terlihat di CI, tidak memblokir, baseline terdokumentasi di file
- [x] Bukti lokal ditempel di atas
- [x] Changelog + baris `README.md`

## Follow-up (di luar tiket ini)

1. Pasang `vue-tsc` (butuh persetujuan user) lalu tambahkan job typecheck blocking.
2. Bersihkan baseline formatting (429 Prettier + 24 Pint) di tiket terpisah, lalu ubah job `formatting` menjadi blocking.
3. Perbaiki `composer.json` `php: ^8.2` agar konsisten dengan `composer.lock` yang mensyaratkan 8.4.
