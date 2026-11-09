# Sapto Changes — 9 November 2026 (BE-P2-009)

## Ringkasan

Export CSV registrasi event (`GET /admin/events/{event}/exports/registrations.csv`)
mengurutkan baris dengan `form_answers.created_at DESC` lalu memakai
`chunk(250)` — yaitu `LIMIT/OFFSET` di atas kunci urut yang **tidak unik**
(`created_at` hanya presisi detik). Dua akibat:

1. baris dengan timestamp sama dapat berpindah posisi antar halaman sehingga
   satu baris terlewat atau tertulis dua kali;
2. `OFFSET` besar mematerialisasi ulang hasil terurut pada setiap halaman
   (biaya kuadratik terhadap jumlah baris).

Setelah patch, export memakai **keyset pagination** `(created_at DESC, id DESC)`
dengan kursor komposit, mengikuti pola yang sudah dipakai
`RecruitmentInterviewExportController::streamRows()`. Kontrak CSV tidak berubah:
13 kolom, urutan kolom, BOM UTF-8, nama file, dan arah urutan `created_at DESC`.

| Issue | Commit |
| ----- | ------ |
| BE-P2-009 | `682a502` — `fix: paginate event registration export by unique keyset` |

Laporan lengkap: `docs/big-changes/patching-ticket/BE-P2-009.md`.

## Akar masalah (bukti)

`app/Http/Controllers/Dashboard/Events/Exports/EventRegistrationsCsvExportController.php`
(sebelum):

```php
FormAnswer::query()
    ->join('forms', 'form_answers.form_id', '=', 'forms.id')
    // ...
    ->orderByDesc('form_answers.created_at')
    ->chunk(250, function ($answers) use ($out, $summarizer): void { /* ... */ });
```

- `form_answers.created_at` = MySQL `timestamp` presisi detik, nullable, dan
  **tidak** unik; seeder stress mengisi seluruh baris dengan timestamp identik
  (`StressEventDomainSeeder`), jadi tie bukan skenario teoretis.
- `form_answers.id` = `char(36)` primary key unik.
- `chunk()` di Laravel = `forPage()` = `LIMIT/OFFSET`
  (`Illuminate/Database/Concerns/BuildsQueries.php`).

## Perubahan

| File | Perubahan |
| --- | --- |
| `app/Http/Controllers/Dashboard/Events/Exports/EventRegistrationsCsvExportController.php` | `streamRows()` (loop halaman + kursor `(created_at, id)`), `answerPage()` (predikat `created_at < ? OR (created_at = ? AND id < ?)`, `orderByDesc(created_at, id)`, `limit`), `baseAnswerQuery()`, `streamRowsWithoutCreatedAt()` + `answerPageWithoutCreatedAt()` (tail pass `created_at IS NULL`, keyset `id DESC`), dan `writeRow()`. `MAX_PAGE_SIZE = 2000`; page size dari `config('registration.event_export_page_size')` di-clamp 1..2000. `fputcsv(..., ',', '"', '')`; `submitted_at` null-safe; judul form diambil sebagai `addSelect('forms.title as form_title')` sehingga eager load `form` per halaman dihapus. |
| `config/registration.php` | Key baru `event_export_page_size` (env `REGISTRATION_EVENT_EXPORT_PAGE_SIZE`, default 250, efektif 1..2000). |
| `tests/Feature/Dashboard/EventRegistrationExportOrderingTest.php` (baru) | 7 test / 69 assertion: tie lintas halaman (created_at sama, page size 2), baris `created_at` NULL diekspor sekali di ekor, tanpa `OFFSET` + predikat kursor pada halaman 2–3, insert di antara dua halaman tidak menduplikasi/menghilangkan baris, member 403 (JSON), admin non-owner 403, event tidak ada 404. |
| `tests/e2e/event-registrations-export.desktop.spec.ts` (baru) | Spec browser: 300 `form_answers` bertimestamp identik (melewati batas page 250), unduh lewat anchor UI di halaman Laporan, verifikasi BOM, 13 kolom, 300 baris unik, urutan = urutan keyset DB; anomali otorisasi (admin non-owner 403, member 302, event tidak ada/non-UUID 404) dan dua unduhan paralel. |
| `playwright.be2p009.config.ts` (baru, sementara) | Config khusus verifikasi browser tanpa project `setup`: `auth.setup.ts` menavigasi `/dashboard` yang pada dataset stress 1 juta baris memakai fallback agregasi tren yang lambat dan memblokir worker Octane tunggal. Spec login sendiri lewat `/auth/login?intended=/up`. |

Tidak ada perubahan route, policy, permission, schema, atau migration.

## Keputusan desain

| Keputusan | Alasan / alternatif yang ditolak |
| --- | --- |
| Keyset `(created_at, id)` | Alternatif "tambah `orderByDesc(id)` saja" menghapus tie tetapi tetap `OFFSET` (O(n²) pada 1 juta baris) dan tetap drift saat insert bersamaan. Alternatif `chunkById` (= `id` saja) mengubah urutan kronologis yang sudah dijanjikan ke user. |
| Urutan tetap `created_at DESC`, `id DESC` sebagai tie-breaker | Acceptance tiket: jangan mengubah urutan yang dijanjikan. `id` hanya memutus tie, tidak mengubah urutan antar timestamp. |
| Tanpa filter snapshot `created_at <= now()` | Pada urutan DESC, baris baru selalu berada **di depan** kursor sehingga tidak pernah tertulis dua kali; tidak perlu kolom/parameter tambahan. |
| Tail pass untuk `created_at IS NULL` | Kolom nullable; `NULL` selalu berada di ekor urutan DESC dan tidak bisa dijangkau kursor non-NULL. Tanpa tail pass, baris legacy itu hilang diam-diam pada dataset > page size (ditemukan pada review independen). Hanya satu query tambahan saat tidak ada baris NULL. |
| `MAX_PAGE_SIZE = 2000` | Mengikuti pola `RecruitmentInterviewExportController`; satu halaman memuat model + JSON `answers`, jadi nilai env yang salah tidak boleh berujung 500 memory limit. |
| Judul form via `addSelect` | `forms` sudah di-join; eager load `form` menambah satu query per halaman (±4000 query untuk 1 juta baris). |
| `fputcsv(..., ',', '"', '')` | Konsisten dengan controller export lain dan menghindari deprecation `$escape` pada PHP 8.4 (`BE-P3-016`). |
| Index komposit tidak ditambahkan di tiket ini | `EXPLAIN` menunjukkan `Using filesort` pada `ORDER BY created_at DESC, id DESC`; menambah index `(form_id, created_at, id)` adalah perubahan schema terpisah (build index pada 1 juta baris) dan dicatat sebagai `BE-P2-035`. |

## Verifikasi

- `php artisan test --filter=EventRegistrationExportOrderingTest` → **7 passed (69 assertions)**.
- `php artisan test --filter=EventReportingTest` (regresi) → **9 passed (38 assertions)**.
- TDD merah: dengan implementasi lama, `test_export_paginates_with_keyset_cursor_and_never_uses_offset`
  gagal (`offset 0` ditemukan pada query halaman) dan halaman tie tidak teruji
  karena hanya satu halaman yang dijalankan. Dengan tail pass dinonaktifkan,
  `test_rows_without_created_at_are_exported_once_after_the_keyset_pages` gagal.
- Pint: `PASS — 3 files`.
- `EXPLAIN` (MySQL 9, dataset stress 1 juta baris, event contoh):

  ```text
  forms         ref   forms_event_id_foreign      rows=1  Using where; Using temporary; Using filesort
  form_answers  ref   form_answers_form_id_grou... rows=1  Using where
  ```

  Filesort adalah sisa biaya `ORDER BY`; `OFFSET` sudah tidak ada. Optimasi index
  dipisah ke `BE-P2-035`.
- E2E browser: `5 passed (4.0m)` / exit 0 — BOM, 13 kolom, 300 baris unik
  bertimestamp identik, urutan = keyset DB, 403 admin non-owner, 302 member,
  404 UUID/non-UUID, dua unduhan paralel. Rincian dan catatan durasi di
  `docs/big-changes/patching-ticket/BE-P2-009.md` §E2E.

## Risiko & rollback

- Rollback: revert satu file controller + hapus key config; tidak ada perubahan
  schema atau data, sehingga tidak perlu migration down.
- Perilaku yang berubah hanya untuk tie `created_at` (kini deterministik) dan
  baris `created_at` NULL (kini diekspor di ekor, bukan fatal/lost).
- Baris yang form-nya di-soft-delete setelah kursor akan terlewat; ini sama
  dengan perilaku filter `whereNull('forms.deleted_at')` sebelumnya dan
  didokumentasikan sebagai precondition di docblock.

## Follow-up (di luar scope, dicatat di `patching.md`)

- `BE-P2-030` export CSV attendance event (`chunk()` + `scanned_at` non-unik).
- `BE-P2-031` global scan export (`lazy()` + ordering non-unik, CSV & XLSX).
- `BE-P2-032` `ScanStreamFeed` kehilangan baris pada timestamp identik.
- `BE-P2-033` list/report paginated tanpa tie-breaker unik.
- `BE-P2-034` N+1 `RegistrationAnswersSummarizer` + pola CSV keyset belum dipusatkan.
- `BE-P2-035` index `(form_id, created_at, id)` untuk menghapus filesort export.
