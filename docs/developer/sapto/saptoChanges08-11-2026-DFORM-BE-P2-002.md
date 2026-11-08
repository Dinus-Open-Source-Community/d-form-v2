# Sapto Changes — 8 November 2026 (BE-P2-002)

## Ringkasan

Export CSV interview (`/admin/recruitment/periods/{period}/interviews/export.csv`)
tidak lagi memakai paginasi OFFSET. Sebelumnya `chunk(200)` menjalankan ulang
query join + `Using temporary; Using filesort` untuk setiap chunk, sehingga
periode 20.000 baris berarti 100 kali penyusunan ulang seluruh hasil terurut.
Sekarang export memakai keyset pagination di atas
`recruitment_applications.registration_number` (unik global), mengambil interview
satu halaman lewat satu `whereIn` + eager loading, dan menulis baris dalam urutan
`(registration_number, interview_kind, id)` yang sama seperti sebelumnya.

Pada dataset stress yang sama (1.000.000 aplikasi, 20.000 per periode) export
default turun dari **1.046 query / 6.169.310 baris dibaca / 1.347 s** menjadi
**425 query / 186.821 baris dibaca / 32,9 s**, dengan isi CSV identik (20.001
baris termasuk header). Tidak ada perubahan route, nama file, header, kolom,
filter, label enum, atau otorisasi.

| Issue | Commit |
| ----- | ------ |
| BE-P2-002 | belum di-commit (working tree berisi perubahan sesi lain; commit hanya file tiket ini) |

Laporan lengkap: `docs/big-changes/patching-ticket/BE-P2-002.md`.

## Akar masalah (bukti, bukan gejala)

- `Illuminate\Database\Eloquent\Builder::chunk()` = `forPage()` = `LIMIT/OFFSET`.
  Query lama men-join `recruitment_applications` dan mengurutkan
  `recruitment_applications.registration_number`; `EXPLAIN` menunjukkan
  `type=ref`, `Using temporary; Using filesort` untuk setiap chunk.
- Urutan tidak dapat ditutup index meski index `(period, registration_number)`
  dipaksa, karena `ORDER BY` memuat kolom tabel hasil join
  (`recruitment_interviews.interview_kind`). Karena itu perbaikan harus menghapus
  pengulangan penyusunan ulang, bukan hanya menambah index.
- Tanpa index baru, halaman akhir keyset mundur ke unique index global
  `registration_number` dengan estimasi pemindaian **486.223** baris.

## Perubahan

| File | Perubahan |
| ---- | --------- |
| `app/Http/Controllers/Dashboard/Recruitment/RecruitmentInterviewExportController.php` | `baseQuery()->chunk(200)` diganti `streamRows()` + `applicationPage()` (keyset `registration_number`, `select(['id','registration_number'])` agar index menjadi covering) + `interviewsForPage()` (satu `whereIn`, filter `scope`/`include_secondary` yang sama, tujuh eager load yang sama, `orderBy('interview_kind')->orderBy('id')`, dikelompokkan per aplikasi). `row()` dan `COLUMN_LABELS` tidak berubah. |
| `config/recruitment.php` | `interview_export_page_size` dari `RECRUITMENT_INTERVIEW_EXPORT_PAGE_SIZE`, default 500, dijepit ke rentang `1..2000`. |
| `database/migrations/2026_11_08_000002_add_recruitment_interview_export_index.php` (baru) | Index `rec_apps_period_regno_idx (recruitment_period_id, registration_number)`, idempoten lewat `Schema::hasIndex`; `down()` hanya drop bila ada. Additive, tanpa perubahan data. |
| `tests/Feature/Recruitment/RecruitmentInterviewExportPaginationTest.php` (baru) | Kelengkapan + urutan lintas halaman, primary sebelum secondary, filter `scope` melintasi batas halaman, halaman yang seluruh barisnya tersaring tetap maju, kelipatan tepat page size, `preventLazyLoading` (termasuk `attendance` + `finalDecision.finalDivision`), jumlah query datar, dan assert tidak ada `OFFSET`. |
| `tests/e2e/recruitment-interview-export.desktop.spec.ts` (baru) | Unduhan nyata di browser (BOM, 28 label, urutan baris, label enum, netralisasi formula), subset `columns[]`, `scope` evaluated/pending, empat input anomali (302 + session error, JSON 422), 403 untuk role tanpa izin, dua export paralel, dan `include_secondary=false`. |

Tidak ada perubahan pada `AGENTS.md`, `docs/rules/**`, route, policy, atau kontrak response.

## Keputusan desain

| Keputusan | Alasan / alternatif yang ditolak |
| --- | --- |
| Keyset di atas `registration_number` | Unik global + `NOT NULL`, jadi kursor `>` deterministik tanpa tie-breaker. `chunkById`/`lazyById` memaksa urutan `id` (uuid acak) sehingga urutan CSV berubah; `cursor()` pada query interview memicu lazy loading per model. |
| Halaman berbasis aplikasi | Interview per aplikasi maksimal dua (unique `(application_id, interview_kind)`), jadi satu halaman aplikasi membawa seluruh interview milik baris tersebut tanpa offset. |
| Satu query interview + eager loading per halaman | Query datar 10 `LEFT JOIN` + `cursor()` diukur 121 s untuk 20.000 baris dan menahan seluruh result set di memori (26 MB); eager loading per halaman memberi 33× lebih sedikit baris dibaca dengan memori per halaman konstan. |
| Index komposit `(period, registration_number)` | Halaman menjadi `type=range`, `Using index`, 1,1 ms/500 baris; tanpa index estimasi 486.223 baris pada halaman akhir. Tabel menulis sekali per pelamar, jadi biaya tulis dapat diterima. |
| Page size config dengan batas atas 2000 | Softcode (aturan user 11) sekaligus mencegah operator menaikkan nilai sampai melampaui `memory_limit` (±25 MB per 500 baris). |
| `select(['id','registration_number'])` | Membuat index komposit covering; `full_name`, `nim`, dst. tetap datang dari eager loading interview, jadi kontrak kolom tidak berubah. |
| Urutan `registration_number` saja ditolak | Menghapus filesort tetapi urutan primary/secondary dalam satu aplikasi tidak dijamin — kelas bug `BE-P2-009`. |

## Verifikasi

| Pemeriksaan | Hasil |
| ----------- | ----- |
| `php artisan test --filter=RecruitmentInterviewExport` | 18 passed (148 assertions) |
| `php artisan test tests/Feature/Recruitment` | 345 passed (2.402 assertions) |
| `php artisan test` (suite penuh, setelah perbaikan review) | 1.130 passed, 8 skipped (7.061 assertions), 88,6 s |
| `pint --test` file tiket | PASS |
| E2E `tests/e2e/recruitment-interview-export.desktop.spec.ts` (`chromium-desktop`, `--retries=0`) | 8 passed (setup + 7 test) |
| Migration round-trip MySQL 9 (tabel 1 juta baris) | create 19 s → rollback 205 ms → create 17 s |

## Risiko & rollback

- Build index 17–19 s pada tabel 1 juta baris saat deploy; DDL secondary index
  InnoDB berjalan online.
- Rollback: `php artisan migrate:rollback --path=database/migrations/2026_11_08_000002_add_recruitment_interview_export_index.php`
  (205 ms, terukur). Export tetap benar, hanya halaman akhir menjadi lebih lambat.
