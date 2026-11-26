# DFORM-FORM-ANSWERS-EXPORT — Export CSV/ZIP jawaban form

| Issue                                                  | Commit                                                              |
| ------------------------------------------------------ | ------------------------------------------------------------------- |
| DFORM-FORM-ANSWERS-EXPORT (direct request PM; no Jira) | Uncommitted at authoring; final hash recorded in the session report |

Klasifikasi: `feature` (export jawaban per form) + `refactor` UI tab Jawaban.

## Ringkasan (TL;DR)

Tab **Jawaban** kini punya satu tombol **Export** dengan dropdown dua opsi:
**Download dengan file (ZIP)** dan **Download biasa (CSV)**. Tiga aksi form
(Jadikan draft / Pratinjau / Save All) tetap tampil di tab editor dan pengaturan,
tetapi disembunyikan di tab Jawaban. CSV berisi satu kolom per field dengan signed
URL lampiran; ZIP berisi satu `jawaban.csv` gabungan di root dan satu folder per
pengirim yang hanya berisi file jawabannya.

## Perilaku

- CSV: metadata submission + satu kolom per field, nilai array digabung `|`,
  file menjadi signed URL 7 hari, formula-injection dinetralkan `CsvWriter`.
- ZIP: satu `jawaban.csv` gabungan di root (sama dengan export CSV), lalu folder
  `001 - Nama - email` per pengirim yang hanya berisi semua file `fileUpload` miliknya.
  Folder kosong tetap dibuat bila pengirim tidak mengunggah file.
- Nama pengirim guest diambil dari field yang labelnya mengandung "nama"/"name",
  bukan nilai JSON pertama (MySQL mengurutkan key JSON).
- Limit ZIP: `FORMS_ANSWERS_ZIP_MAX_FILES` (default 2000) dan
  `FORMS_ANSWERS_ZIP_MAX_BYTES` (default 512 MB); melewati batas → 413 dengan pesan
  public-safe. File sementara dihapus setelah dikirim atau saat gagal.
- Otorisasi: policy `view` event + form harus milik event (404 bila tidak).
  Rate limit `form-answers-export` (default 10/menit per akun).

## Backend

| File                                                          | Perubahan                                                           |
| ------------------------------------------------------------- | ------------------------------------------------------------------- |
| `routes/web/admin/event.php`                                  | route CSV/ZIP `events.forms.answers-csv` / `answers-zip` + throttle |
| `app/Http/Controllers/.../FormAnswersCsvExportController.php` | baru; stream CSV + audit log                                        |
| `app/Http/Controllers/.../FormAnswersZipExportController.php` | baru; download ZIP + audit log + 413 limit                          |
| `app/Services/Registration/FormAnswersExportService.php`      | baru; fields, baris CSV, folder/nama, file entries, answers.csv     |
| `app/Services/Registration/FormAnswersArchiveBuilder.php`     | baru; ZipArchive ke file sementara, limit, nama unik                |
| `app/Exceptions/FormAnswersArchiveTooLargeException.php`      | baru; sinyal limit                                                  |
| `app/Http/Controllers/.../FormController.php`                 | kirim `exportUrls` ke halaman                                       |
| `app/Providers/AppServiceProvider.php`                        | limiter `form-answers-export`                                       |
| `config/events.php`, `lang/{en,id}/messages.php`              | batas ekspor + pesan 413                                            |

## Frontend

| File                                                 | Perubahan                                                                                  |
| ---------------------------------------------------- | ------------------------------------------------------------------------------------------ |
| `resources/js/pages/Dashboard/Events/Forms/Show.vue` | prop `exportUrls`; dropdown Export di tab Jawaban; tiga aksi form disembunyikan di tab itu |

## Pagination & select all

Tab Jawaban memakai pagination server-side: default 20 baris, opsi 10/20/50/100, halaman di luar rentang di-clamp. `selectableSubmissionIds` mengirim ID eligible dari semua halaman (maksimal 100, sama dengan batas bulk review); select-all memilih seluruh ID itu dan pilihan bertahan saat pindah halaman (`preserveState`). Export tidak terpengaruh pagination: CSV/ZIP selalu membaca seluruh submission.

## Test

| Test                                                             | Hasil                                                                     |
| ---------------------------------------------------------------- | ------------------------------------------------------------------------- |
| `tests/Feature/Dashboard/FormAnswersExportTest.php` (7 test)     | CSV, ZIP root jawaban.csv + folder/files, guest name, otorisasi, 404, 413 |
| `tests/Feature/Dashboard/FormAnswersPaginationTest.php` (3 test) | metadata halaman, clamp/allowlist per_page, select-all hanya eligible     |
| `tests/Feature/Export/HeavyExportRateLimitTest.php`              | dua route baru memakai limiter bernama                                    |
| `tests/e2e/form-answers-export.desktop.spec.ts`                  | dropdown, CSV download, ZIP download, tab editor tetap tiga aksi          |
| `tests/e2e/form-answers-pagination.desktop.spec.ts`              | 25 jawaban, pindah halaman, nomor 11+, select-all 25, CSV tetap 25 baris  |
| PHPUnit penuh                                                    | 1645 passed, 12 skipped, 1 gagal pre-existing recruitment EXPLAIN         |
| Vitest                                                           | 988 passed (90 file)                                                      |
| Build                                                            | entry 49,32 kB raw / 15,54 kB gzip                                        |

## Data uji lokal

Untuk pengujian manual, form `01a1d77f-6f7d-7330-92a4-c7b262b7637d` di dev diisi
100 jawaban guest (`peserta01..100@example.test`) dan 100 file PDF disalin dari
`storage/app` ke `storage/app/private/form-uploads/<formId>/cv-001..100.pdf`.
Data ini lokal, gitignored, dan tidak ikut commit.

## Checklist

- [x] Dropdown Export hanya di tab Jawaban; tab lain tidak berubah.
- [x] CSV per field + signed URL; formula injection aman.
- [x] ZIP folder per pengirim berisi file + answers.csv; nama disanitasi.
- [x] Limit 413, rate limit, otorisasi, audit log tanpa PII.
- [x] Feature test + E2E; lint/typecheck/build.
- [ ] Commit.
