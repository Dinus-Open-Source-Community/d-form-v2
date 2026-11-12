# Sapto Changes — 30 Oktober 2026 (DFORM-CI-EXTENSIONS)

## Ringkasan

Memperbaiki workflow GitHub Actions yang gagal pada job Composer/PHP karena runner tidak memasang seluruh ekstensi PHP yang diminta oleh `composer.lock` dan test upload. Runtime CI juga diselaraskan dengan baseline proyek yang memakai Node.js 24.

| Issue | Commit |
| ----- | ------ |
| GitHub Actions quality gate merah | `fix(ci): align GitHub Actions runtimes and PHP extensions` |

## Akar masalah

- Job `test` hanya memasang `mbstring`, `pdo_sqlite`, dan `sqlite3`, sedangkan dependency terkunci memerlukan setidaknya `bcmath`, `dom`, `fileinfo`, `xml`, dan `zip`; test upload juga menggunakan GD.
- Job `formatting` memiliki kekurangan ekstensi yang sama sehingga gagal pada `composer install` sebelum Pint/Prettier dijalankan.
- Workflow memakai Node.js 20, sementara baseline build dan dokumentasi proyek memakai Node.js 24.

## Perubahan

- Menyamakan PHP extension set pada job `test` dan `formatting`: BCMath, cURL, DOM, fileinfo, GD, Intl, mbstring, SQLite, XML, dan ZIP.
- Mengubah `node-version` dari 20 ke 24 pada seluruh job Node.
- Memperbarui komentar baseline jumlah unit test dari 52 menjadi 61.

## Verifikasi

- `podman exec -i -w /app d_form_app php artisan test --testsuite=Unit`: PASS (61 tests, 335 assertions).
- `podman exec -i -w /app d_form_app composer check-platform-reqs`: PASS untuk seluruh requirement Composer.
- `npm run lint`: PASS.
- `npm ci --dry-run --ignore-scripts`: PASS.
- YAML workflow berhasil diparse dan seluruh job tetap terdefinisi.

GitHub-hosted runner belum dapat dijalankan ulang dari environment lokal karena tidak tersedia akses `gh`/API GitHub; validasi lokal menggunakan command dan requirement yang sama dengan workflow.
