# DFORM-LAPORAN-REMOVAL — Hapus halaman Laporan Event

| Issue                                              | Commit                                                              |
| -------------------------------------------------- | ------------------------------------------------------------------- |
| DFORM-LAPORAN-REMOVAL (direct request PM; no Jira) | Uncommitted at authoring; final hash recorded in the session report |

Klasifikasi: `refactor` + `migration` (penghapusan permukaan, tanpa perubahan data).

## Ringkasan (TL;DR)

Halaman `/admin/events/{event}/laporan` dihapus seluruhnya: route, controller, request,
page Inertia, panel laporan, method query khusus laporan, attendance CSV, link di detail
event, label breadcrumb, section dokumentasi UI, probe benchmark, delapan file test, dan
satu index database yang redundan. Registrations CSV, `EventReportingQuery` untuk
dashboard, `event_daily_registrations`, serta seluruh infrastruktur export/scan tetap
utuh karena dipakai di luar halaman ini. URL lama kembali 404 tanpa redirect.

## Kondisi sebelum

| #   | Temuan                                                                                       | Bukti                                                                                 |
| --- | -------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------- |
| 1   | Route laporan hanya dipakai page laporan dan satu tombol di rail detail event                | `routes/web/admin/event.php`, `EventShowAsideRail.vue`                                |
| 2   | `EventReportingQuery` dipakai bersama dashboard; hanya sebagian method milik laporan         | `HomeController`, `dashboard:warm`, `analytics:rebuild-daily-registrations`           |
| 3   | Registrations CSV dipakai menu "Export data" pada kartu event, bukan hanya halaman laporan   | `EventCard.vue:openExport`                                                            |
| 4   | Attendance CSV hanya dipakai halaman laporan                                                 | `EventLaporanController`, `EventReportingFocusPanel.vue`                              |
| 5   | Index 2 kolom `(event_id, scanned_at)` hanya untuk paginasi laporan; index 3 kolom sudah ada | `2026_11_09_000001`, `2026_11_10_000009`, komentar `EventAttendanceLogPaginationTest` |
| 6   | Halaman laporan tidak memakai Lottie; seluruh 14 aset masih dipakai halaman lain             | `rg "LocalLottie                                                                      | lotties" resources/js`; `public/lotties/*.json` |

## Perubahan per lapisan

### Backend

| File                                                                                   | Perubahan                                                                                                                                                                           |
| -------------------------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `app/Http/Controllers/Dashboard/Events/EventLaporanController.php`                     | dihapus                                                                                                                                                                             |
| `app/Http/Requests/Events/ShowEventReportRequest.php`                                  | dihapus (folder `Requests/Events` menjadi kosong)                                                                                                                                   |
| `app/Http/Controllers/Dashboard/Events/Exports/EventAttendanceCsvExportController.php` | dihapus                                                                                                                                                                             |
| `routes/web/admin/event.php`                                                           | hapus import + route `dashboard.events.laporan`                                                                                                                                     |
| `routes/web/admin/index.php`                                                           | hapus import + route `dashboard.events.exports.attendance-csv`; `registrations.csv` dan `scan` dipertahankan                                                                        |
| `config/registration.php`                                                              | hapus `attendance_export_page_size`; `event_export_page_size` dipertahankan                                                                                                         |
| `app/Services/Reporting/EventReportingQuery.php`                                       | hapus `eventReportingSummary`, `globalAdminSummary`, `countGlobalAdminSummary`, `paginateAttendanceLog`, `attendanceLogQuery`, `GLOBAL_SUMMARY_TTL_SECONDS`; method dashboard tetap |
| `app/Console/Commands/Stress/QueryBenchmarkCommand.php`                                | hapus probe `EventReportingQuery::eventReportingSummary`; probe `ScanStreamFeed::since(50)` tetap                                                                                   |

### Database

Migration baru `2026_11_17_000001_drop_redundant_event_attendance_scanned_at_index.php`
men-drop `event_attendances_event_id_scanned_at_index (event_id, scanned_at)` karena
redundan dengan `event_attendances_event_id_scanned_at_id_index (event_id, scanned_at, id)`
yang tetap dipakai export scan. `down()` membuat ulang index 2 kolom. Tidak ada tabel,
kolom, seeder, permission, atau baris data khusus halaman ini.

Bukti eksekusi: `php artisan migrate --force` → `DONE 32.01ms`; cek schema →
`hasIndex(2 kolom) = false`, `hasIndex(3 kolom) = true`.

### Frontend — dihapus

| File                                                                     | Alasan                      |
| ------------------------------------------------------------------------ | --------------------------- |
| `resources/js/pages/Dashboard/Events/Laporan.vue`                        | page route yang dihapus     |
| `resources/js/components/modules/dashboard/EventReportingFocusPanel.vue` | hanya dipakai page tersebut |

### Frontend — disesuaikan

| File                                                               | Perubahan                                                                                                                                |
| ------------------------------------------------------------------ | ---------------------------------------------------------------------------------------------------------------------------------------- |
| `resources/js/pages/Dashboard/Events/Show.vue`                     | hapus import `routes`, `laporanHref`, dan prop `:laporan-href`                                                                           |
| `resources/js/components/modules/dashboard/EventShowAsideRail.vue` | hapus prop `laporanHref`, tombol "Laporan dan log kehadiran", dan ikon `BarChart3`                                                       |
| `resources/js/lib/routes.ts`                                       | hapus import `laporan as eventLaporan` dan getter `admin.events.laporan`; `exports.registrations` dipertahankan                          |
| `resources/js/lib/breadcrumbs.ts`                                  | hapus label `laporan: 'Laporan'`                                                                                                         |
| `resources/js/pages/Docs.vue`                                      | hapus nav `Laporan Acara`, section `#reporting`, kartu Attendance CSV, dan import `FileText`; deskripsi `EventReportingQuery` diperbarui |
| Wayfinder generated (`resources/js/{routes,actions,wayfinder}`)    | regenerasi container; file gitignored, tidak di-stage                                                                                    |

`KpiCard`, `ExportDownloadStatus`, `useExportDownload`, `AppPagination`, dan
`usePaginationNavigation` dipertahankan karena masih dipakai dashboard, scan, dan
recruitment.

### Aset

Tidak ada aset Lottie yang dihapus. Audit: halaman laporan tidak merender Lottie, dan
14 file `public/lotties/*.json` semuanya direferensikan landing, builder, atau empty/error
state. Tidak ada aset yatim dari penghapusan ini.

### Test

Dihapus:

- `tests/Feature/Dashboard/EventReportingTest.php`
- `tests/Feature/Dashboard/EventAttendanceLogPaginationTest.php`
- `tests/Feature/Dashboard/EventLaporanPaginationRequestTest.php`
- `tests/Feature/Reporting/GlobalAdminSummaryCacheTest.php`
- `tests/Feature/Dashboard/Events/EventAttendanceCsvExportTest.php`
- `tests/Feature/Dashboard/Events/EventAttendanceCsvExportKeysetTest.php`
- `tests/Feature/Dashboard/Events/EventAttendanceCsvExportAuditFailureTest.php`
- `tests/e2e/event-attendance-report.desktop.spec.ts`

Ditambah/dipindahkan:

- `tests/Feature/Dashboard/ReportsRedirectTest.php` — dua test redirect `/admin/reports`
  dari `EventReportingTest`.
- `tests/Feature/Dashboard/Events/EventRegistrationsCsvExportTest.php` — kolom CSV,
  otorisasi member, dan netralisasi formula registrations dari `EventReportingTest`.
- `tests/Feature/Dashboard/Events/EventAttendanceIndexTest.php` — kontrak index: 2 kolom
  absen, index 3 kolom + index export form_answers tetap ada, dan `down()` mengembalikan
  index 2 kolom.

Disesuaikan:

- `EventRegistrationExportOrderingTest` — limiter registrations-only.
- `HeavyExportRateLimitTest` — attendance export dihapus dari daftar route export (5 route).
- `tests/e2e/event-registrations-export.desktop.spec.ts` — unduhan happy path langsung dari
  endpoint registrations (host tombol laporan dihapus).
- `tests/e2e/upload-ui-wiring.spec.ts` — tiga test export dipindah ke halaman Scan
  (`downloadWithProgress` yang sama); fixture menyertakan `ownerEmail` agar otorisasi export lolos.
- `resources/js/lib/__tests__/routes.test.ts` — key `admin.events.laporan` dihapus.
- `resources/js/lib/__tests__/breadcrumbs.test.ts` — path non-formulir memakai `/edit`.
- `resources/js/hooks/__tests__/useExportDownload.test.ts` — path dummy netral.

## Bukti build

Diukur dengan `zlib` lalu dibagi 1024, di container `d_form_app`.

| Metrik               | Sebelum                        | Sesudah                        |
| -------------------- | ------------------------------ | ------------------------------ |
| Entry `app-*.js`     | 50,66 kB raw / 15,91 kB gzip   | 49,15 kB raw / 15,49 kB gzip   |
| Chunk terbesar       | 451,51 kB raw / 142,17 kB gzip | 451,51 kB raw / 142,17 kB gzip |
| Chunk terbesar kedua | 326,80 kB raw / 97,07 kB gzip  | 326,80 kB raw / 97,07 kB gzip  |
| Chunk lottie         | 307,10 kB raw / 79,10 kB gzip  | 307,10 kB raw / 79,10 kB gzip  |

Entry turun 1,51 kB raw / 0,42 kB gzip. Chunk page laporan tidak lagi dihasilkan; chunk
bersama tidak berubah. Budget tetap hijau (entry ≤ 350 kB gzip; page chunk ≤ 200 kB gzip).

## Verifikasi

| Perintah                                                                                                                      | Hasil                                                                                                                                                     |
| ----------------------------------------------------------------------------------------------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `php artisan route:list --path=laporan`                                                                                       | kosong (URL lama 404 alami)                                                                                                                               |
| `php artisan route:list --path=exports`                                                                                       | hanya `registrations.csv`                                                                                                                                 |
| `php artisan test tests/Feature/Dashboard tests/Feature/Export tests/Feature/Reporting tests/Feature/Forms/FormDraftTest.php` | **137 passed** (993 assertions)                                                                                                                           |
| `php artisan test` (penuh)                                                                                                    | **1632 passed, 12 skipped, 1 failed** — kegagalan pre-existing `RecruitmentApplicationListQueryTest` (EXPLAIN index MySQL, tidak menyentuh perubahan ini) |
| `npm run test:unit`                                                                                                           | **987 passed** (90 file)                                                                                                                                  |
| `npm run test:e2e:types`                                                                                                      | pass                                                                                                                                                      |
| E2E `event-registrations-export.desktop.spec.ts`                                                                              | **5 passed**                                                                                                                                              |
| E2E `upload-ui-wiring.spec.ts --grep "export:"` desktop                                                                       | **3 passed** (+setup)                                                                                                                                     |
| E2E `upload-ui-wiring.spec.ts --grep "export:"` mobile                                                                        | **3 passed** (+setup)                                                                                                                                     |
| `npm run lint`                                                                                                                | 0 error, 1 warning pre-existing (`formBuilderAutosave.test.ts`)                                                                                           |
| `npm run typecheck`                                                                                                           | pass                                                                                                                                                      |
| `pint --test` file yang diubah                                                                                                | pass                                                                                                                                                      |
| `prettier --check` file yang diubah                                                                                           | pass                                                                                                                                                      |
| `npm run build` (container)                                                                                                   | pass                                                                                                                                                      |

Kegagalan/gap pre-existing yang dicatat, bukan diklaim hijau:

- `pint --test` seluruh proyek: 4 file tidak terkait (`UserEventPortalController`, `User`,
  `KeysetMergeIterator`, `routes/ai.php`).
- `prettier --check .`: 163 file lama (mayoritas Blade) belum terformat.
- PHPUnit penuh: satu test EXPLAIN MySQL recruitment gagal karena index yang diharapkan
  tidak dipilih planner; tidak ada file recruitment yang diubah pada tiket ini.
- Full E2E suite tidak dijalankan ulang seluruhnya; scope E2E yang terdampak (export
  registrations + export download hook) dijalankan desktop dan mobile dan hijau. Suite
  penuh membutuhkan dataset stress dan `FEATURE_BROADCAST` yang tidak aktif di server lokal.

## Checklist

- [x] Route/page/controller/request/panel/attendance export/benchmark/test terhapus.
- [x] Registrations CSV dan dashboard reporting tetap berfungsi dan teruji.
- [x] Migration drop index punya `down()` dan idempoten.
- [x] Wayfinder diregenerasi; tidak ada referensi `events.laporan` di kode.
- [x] Build before/after tercatat; budget hijau.
- [x] Changelog per tiket + baris README + CONTEXT diperbarui.
- [ ] Commit tunggal (dilakukan setelah validasi akhir).
