# Sapto Changes — 9 November 2026 (BE-P2-006)

## Ringkasan

Halaman laporan kehadiran event (`GET /admin/events/{event}/laporan`) memanggil
`EventReportingQuery::paginateAttendanceLog()`: filter `event_id`, urutkan
`scanned_at DESC`, `paginate(20)`. Tabel `event_attendances` hanya punya unique
`(event_id, form_answer_id)` dan dua index FK; tidak ada index yang menutup
filter + sort sekaligus, sehingga MySQL memakai unique index lalu menyusun ulang
hasil dengan `Using filesort`.

Perbaikan: satu migration aditif dan idempoten menambah index komposit
`event_attendances_event_id_scanned_at_index (event_id, scanned_at)`. Tidak ada
perubahan query, urutan, kontrak response, route, policy, atau perilaku. Index
ascending melayani `ORDER BY scanned_at DESC` lewat backward index scan
(dibuktikan `EXPLAIN`). Export CSV kehadiran (`EventAttendanceCsvExportController`)
yang mengurutkan `scanned_at` pada event yang sama ikut memakai index ini tanpa
perubahan kode.

| Issue | Commit |
| ----- | ------ |
| BE-P2-006 | `e8c7d0f` |

Laporan lengkap: `docs/big-changes/patching-ticket/BE-P2-006.md`.

## Akar masalah (bukti, bukan gejala)

- `EXPLAIN` pada event sintetis 200.000 baris (MySQL 9.0.1):
  `type=ref`, `key=event_attendances_event_id_form_answer_id_unique`,
  `Extra=Using filesort`.
- Unique index hanya melayani `event_id`; `scanned_at` tidak ada di index,
  sehingga MySQL wajib menyusun ulang seluruh hasil sebelum `LIMIT/OFFSET`.
- Bukti plan sebelum/sesudah: `Using filesort` → `Backward index scan`.

## Perubahan

| File | Perubahan |
| ---- | --------- |
| `database/migrations/2026_11_09_000001_add_event_attendances_event_scanned_at_index.php` (baru) | Index `event_attendances_event_id_scanned_at_index` pada `['event_id','scanned_at']`; idempoten lewat `Schema::hasIndex`; `down()` hanya drop bila ada. Additive, tanpa perubahan data. |
| `tests/Feature/Dashboard/EventAttendanceLogPaginationTest.php` (baru) | 6 test: urutan kolom index, payload + urutan `scanned_at DESC` (termasuk `attendee`/`scanned_by` null), partisi 3 halaman tanpa duplikat/gap, `preventLazyLoading(true)` aman, query count halaman = 5, EXPLAIN MySQL (skip-guarded) memakai index baru tanpa `Using filesort`. |
| `app/Console/Commands/E2e/FixtureCommand.php` | Opsional `ownerEmail`: marker user tetap dibuat/dijurnal (anchor `e2e:sweep`), `events.created_by` memakai user `ownerEmail` agar admin E2E bisa membuka halaman laporan; row lain tetap milik marker user. Email tidak diinterpolasi ke pesan exception (hindari PII di log CI). |
| `tests/e2e/support/fixtures.ts` | `ownerEmail?: string` diteruskan ke `createOwnerFixture(identity, ownerEmail?)`. |
| `tests/e2e/event-attendance-report.desktop.spec.ts` (baru) | Happy path 20/5 baris (25 attendance dari 25 form × 1 submission), matriks anomali `page`/`per_page`, 403 non-owner, guest redirect login. |

Tidak ada perubahan pada `AGENTS.md`, `docs/rules/**`, route, policy, model, atau
`EventReportingQuery.php`.

## Keputusan desain

| Keputusan | Alasan / alternatif yang ditolak |
| --- | --- |
| Index-only, query tidak diubah | Acceptance hanya menuntut index filter+sort dan `EXPLAIN` tanpa filesort; tiebreaker paginasi adalah perubahan perilaku dan sengaja ditunda ke `BE-P2-025`. |
| `(event_id, scanned_at)` ascending | Backward index scan memenuhi `ORDER BY scanned_at DESC` setelah equality `event_id`; index DESC terpisah tidak menambah manfaat. |
| Index komposit, bukan index tunggal `scanned_at` | `event_id` harus leading column agar range index selektif; index tunggal `scanned_at` memaksa scan event lain. |
| Migration idempoten | Index bisa sudah dibuat manual saat benchmark; DDL tidak transaksional sehingga kegagalan di tengah tidak boleh meninggalkan index tanpa baris migrasi. |
| 25 form × 1 submission di E2E | `form_answers_active_user_form_unique` hanya mengizinkan satu answer aktif per `(user_id, form_id)`; 25 form masing-masing satu answer menghasilkan 25 attendance tanpa melanggar unique. |
| `ownerEmail` hanya untuk `events.created_by` | `EventPolicy::view` butuh pemilik; marker user tetap ada agar `e2e:sweep` tetap menemukan run yang crash sebelum cleanup. |

## Bukti pengukuran

| Metrik | Sebelum | Sesudah |
| --- | --- | --- |
| `EXPLAIN` key (page 1 & deep page) | `event_attendances_event_id_form_answer_id_unique` | `event_attendances_event_id_scanned_at_index` |
| `EXPLAIN` Extra | `Using filesort` | `Backward index scan` |
| Wall page 1 (1 run, host contended) | tidak terukur | 44,01 ms |
| Wall deep page `OFFSET 100000` (1 run) | tidak terukur | 33.886,96 ms |
| Migration create / rollback (1 juta baris) | — | 8 s / 92 ms |
| Index round-trip | — | hasIndex yes → no → yes |
| Cleanup dataset benchmark | — | `event_attendances` kembali 1.000.000; `be2p006%` = 0 |

Batas kejujuran: median-of-3 dan delta `Handler_read_*` **tidak** terukur
(`Needs verification`) karena benchmark dihentikan agar environment bersama
tidak kolaps. `rows=372670` pada `EXPLAIN` adalah estimasi optimizer yang sama
sebelum/sesudah, bukan jumlah baris terukur. `stress:queries` pada dataset stress
tidak dapat memperlihatkan filesort karena distribusinya 1 attendance per event.

## Verifikasi

| Gate | Hasil |
| --- | --- |
| `php artisan test tests/Feature/Dashboard/EventAttendanceLogPaginationTest.php` | 6 passed (36 assertions), exit 0 |
| `php artisan test tests/Feature/Dashboard/EventReportingTest.php` | 9 passed (38 assertions), exit 0 |
| `pint --test` 3 file tiket | PASS, exit 0 |
| `npm run test:e2e:types`, `eslint` spec+fixtures, `prettier --check` spec | exit 0 semua |
| E2E browser | 1/4 lolos (guest redirect). 3 test lain `Needs verification`: single Octane worker diblok `/dashboard` lane lain (`/up` 000 selama 48 s+). |

## Review

Review @oracle (kebenaran/performa/kepatuhan) dan @paladin (keamanan) dijalankan;
semua temuan yang bisa diperbaiki di scope tiket sudah diperbaiki:

- Query-count test: assert exact 5.
- Resolver paginator: simpan/restore closure asli (bukan closure palsu).
- Test EXPLAIN: pilih event dengan attendance terbanyak + `whereNull('deleted_at')`,
  restore state koneksi di `finally`.
- Docblock migration: tidak lagi mengklaim bukti otomatis.
- Prettier spec, regex range en dash, scoping locator `tbody tr`.
- `e2e:sweep` blind spot untuk run `ownerEmail`: marker user selalu dibuat.
- Email owner tidak lagi bocor ke pesan exception.

Temuan yang sengaja tidak diperbaiki di tiket ini dicatat sebagai tiket baru:
`BE-P2-025`, `BE-P2-026`, `BE-P3-018`, `BE-P3-019`, `HYG-005`.

## Risiko & rollback

- Create index 8 s pada 1 juta baris (MySQL 9); prod MariaDB menjalankan
  `migrate --force` sebelum Octane start, jadi waktu build menambah startup.
  Additive dan reversible, tanpa perubahan data.
- Rollback: `php artisan migrate:rollback --path=database/migrations/2026_11_09_000001_add_event_attendances_event_scanned_at_index.php --batch=<batch>`.
  Query kembali memakai unique index + filesort tetapi hasil tetap benar.
- Tidak ada perubahan perilaku; tiebreaker paginasi dan `per_page` tetap seperti
  sebelumnya dan ditangani tiket terpisah.
