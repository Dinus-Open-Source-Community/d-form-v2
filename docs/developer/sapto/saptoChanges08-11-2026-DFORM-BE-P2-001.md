# Sapto Changes — 8 November 2026 (BE-P2-001)

## Ringkasan

Daftar pelamar pada tab **Peserta** (`/admin/recruitment/periods/{period}`) memakai
`ORDER BY submitted_at DESC, id DESC` tanpa index yang memuat `submitted_at`,
sehingga MySQL melakukan filesort atas seluruh periode (±20.000 baris) dan
membaca seluruh kolom lewat `SELECT *`. Setelah patch:

- tiga index komposit (period + sort + kolom filter) membuat query daftar
  berjalan sebagai backward index scan tanpa filesort;
- `SELECT *` diganti daftar kolom eksplisit yang benar-benar dipakai
  `toListArray()`.

Pencarian `LIKE '%kata%'` **tidak** diubah: percobaan menggantinya dengan
FULLTEXT diukur lebih lambat untuk pola ketik paling umum dan dibatalkan
(detail di laporan tiket, lanjutan di `BE-P2-013`).

| Issue | Commit |
| ----- | ------ |
| BE-P2-001 | `perf: index and narrow recruitment applicant list query` |

Laporan lengkap: `docs/big-changes/patching-ticket/BE-P2-001.md`.
Plan: `docs/big-changes/patching-ticket/BE-P2-001-plan.md`.

## Akar masalah (bukti)

- `app/Services/Recruitment/RecruitmentApplicationService.php:26-67` — query
  daftar: `SELECT *` + `orderByDesc('submitted_at')` + `orderByDesc('id')`.
- `SHOW INDEX FROM recruitment_applications` sebelum patch: PK `id` (char(36)),
  unique `registration_number`, unique `(recruitment_period_id, nim)`,
  `(recruitment_period_id, stage)`, `(recruitment_period_id, result)`,
  `(recruitment_period_id, primary_division_id)`. Tidak ada `submitted_at`.
- `EXPLAIN` sebelum patch: `key=rec_apps_period_result_idx`,
  `Extra=Using filesort`; 5.181–6.231 ms untuk halaman 1 (dataset stress
  1.000.000 baris, 50 periode × 20.000), konsisten dengan angka audit 5,15 s.

## Perubahan

| File | Perubahan |
| ---- | --------- |
| `database/migrations/2026_11_08_000001_add_recruitment_application_list_indexes.php` (baru) | `rec_apps_period_submitted_idx (recruitment_period_id, submitted_at)`, `rec_apps_period_stage_submitted_idx (recruitment_period_id, stage, submitted_at)`, `rec_apps_period_semester_submitted_idx (recruitment_period_id, semester, submitted_at)`. `down()` menghapus ketiganya. Docblock mencatat alasan terukur dan alasan FULLTEXT tidak dipakai. |
| `app/Services/Recruitment/RecruitmentApplicationService.php` | `LIST_COLUMNS` 12 kolom eksplisit (id, `recruitment_period_id`, nomor registrasi, nama, nim, semester, stage, result, revision_required, submitted_at, dua FK divisi) dipakai `paginate()`; urutan, filter, dan pencarian `LIKE` tidak berubah. |
| `tests/Feature/Recruitment/RecruitmentApplicationListQueryTest.php` (baru) | Index & urutan kolom lewat `Schema::getIndexes()`, kolom selektif dengan nilai nyata, filter stage/queue/semester/divisi-secondary/search, paginasi deterministik saat `submitted_at` sama (25 baris, 2 halaman), dan penjaga `EXPLAIN` MySQL yang membaca SQL asli dari `paginate()` (read-only, skip bila MySQL tidak tersedia). |
| `tests/e2e/recruitment-applicant-list.desktop.spec.ts` (baru) | E2E browser: render daftar, paginasi, filter queue/stage/semester/search, enam input anomali, dan pengukuran waktu navigasi. |

Tidak ada perubahan route, response contract, enum, otorisasi, atau perilaku
filter. Tidak ada perubahan `AGENTS.md`/`SKILL.md`.

## Keputusan desain

| Keputusan | Alasan / alternatif yang ditolak |
| --- | --- |
| Index `(recruitment_period_id, submitted_at)` tanpa `id` eksplisit | InnoDB menambahkan PK (`id`) sebagai suffix index, jadi `ORDER BY submitted_at DESC, id DESC` terpenuhi (backward index scan). Menulis `id` eksplisit menambah 144 byte/baris dengan plan identik (diukur). |
| Tiga index komposit, bukan satu | Filter `stage`/`semester` butuh kolom equality di depan `submitted_at`. Dengan index tunggal, MySQL membaca baris satu per satu untuk mengevaluasi filter: `stage=completed` 27,5 ms, `semester` tanpa hasil 5.654 ms. Dengan index khusus: 1 ms dan 0,3 ms. |
| FULLTEXT untuk pencarian ditolak | Diukur: index FULLTEXT bersifat table-wide sehingga token umum (`A11` awalan semua NIM) harus diurutkan sebelum `LIMIT 20` — halaman 10–11 s + COUNT 8,9–10,7 s per ketikan, vs `LIKE` ber-index 8 ms + 4,35 s. `ALTER TABLE ADD FULLTEXT` juga tidak online di MariaDB (`LOCK=NONE` → ERROR 1846, build 446 s pada 1 juta baris memblokir write). Optimasi pencarian dipisah ke `BE-P2-013`. |
| `SELECT *` → daftar kolom | Menghemat hidrasi `phone`, `personal_email`, `student_email`, `instagram_username`, `tracking_token_hash` yang tidak dipakai `toListArray()`. Kontrak output diuji tidak berubah. |
| `cursorPaginate`/keyset ditolak | Mengubah kontrak paginator dan UI. Tidak diperlukan: setelah index, halaman dalam (page 500) turun dari 1.888 ms menjadi 0,5 ms. |
| Index lebar `(period, submitted_at, stage, result, revision_required)` ditolak | Diukur: memunculkan `Using filesort` pada path utama (karena `id` bukan lagi kolom urut berikutnya) dan berukuran 117 MB. |

## Hasil pengukuran (dataset stress 1.000.000 baris, 50 periode)

`RecruitmentApplicationService::paginate()`, halaman 1, 10 periode:

| Path | Sebelum (avg) | Sesudah (avg / min / max) |
| --- | --- | --- |
| tanpa filter (path utama) | 14.156 ms | **47,2 / 2,6 / 71,8 ms** |
| `stage=completed` | 3.230 ms | 29,6 / 0,4 / 39,5 ms |
| `semester` tanpa hasil | 20.449 ms | 0,5 / 0,4 / 0,7 ms |
| `queue=screening` | 1.323 ms | 805 / 0,8 / 1.690 ms |
| `division_id` (OR primary/secondary) | 6.729 ms | 3.670 / 1,5 / 4.983 ms |

Halaman dalam: page 1 112 ms (cold), page 2 0,8 ms, page 10 0,6 ms,
page 100 0,5 ms, page 500 0,5 ms (sebelum: 1.888 ms).

`EXPLAIN FORMAT=JSON` path utama: `using_filesort=false`,
`key=rec_apps_period_submitted_idx`, `access_type=range`. Di MariaDB 10.11
(probe 200.000 baris) `type=ref`, `key=apps_period_submitted`, tanpa filesort.

Biaya migrasi di dev (tabel 1 juta baris, online DDL per index):
8,6 s (±94 MB), 20,0 s (±107 MB), 10,7 s (±95 MB).

Catatan: pengukuran memakai buffer pool default 128 MB untuk ±700 MB data +
±1,1 GB index, sehingga jalur yang masih memindai satu periode (COUNT dengan
filter OR) tetap I/O-bound dan variasinya besar.

## Dampak & risiko

- Write path: tiga index tambahan pada tabel 1 juta baris; `recruitment_applications`
  ditulis sekali per pelamar dan dibaca terus-menerus oleh staf (read-optimized).
- `rec_apps_period_stage_idx (period, stage)` kini menjadi left-prefix dari index
  baru `(period, stage, submitted_at)`. Tidak dihapus di tiket ini (mengubah
  index lama di luar scope); dicatat sebagai waiver satu baris di laporan.
- Filter `queue` (stage IN …) dan `division_id` (OR dua kolom) masih menyisakan
  filesort/COUNT yang memindai periode — dicatat sebagai `BE-P2-012`.
- Pencarian `LIKE '%kata%'` tetap memindai periode untuk COUNT; optimasi
  pencarian dicatat sebagai `BE-P2-013`.
- Tidak ada perubahan otorisasi: query tetap ter-scope `recruitment_period_id`
  dan endpoint tetap di belakang policy period + `recruitment.applications.list`.
- Rollback: `migrate:rollback --step=1` menghapus tiga index; tidak ada
  perubahan data dan tidak ada query yang bergantung pada index tersebut untuk
  benar (hanya lebih lambat).

## Verifikasi

Lihat `docs/big-changes/patching-ticket/BE-P2-001.md` §Verifikasi untuk perintah,
exit status, dan keluaran ringkas (PHPUnit, Pint, EXPLAIN MySQL/MariaDB, E2E
Playwright desktop + anomali input).
