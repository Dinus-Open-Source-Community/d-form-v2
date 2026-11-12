# Sapto Changes — 11 November 2026 (EP-04)

## Ringkasan

EP-04 menutup determinisme export dan scan pada dataset 1 juta baris: keyset
`(timestamp, id)` menggantikan `chunk()`/`lazy()`/OFFSET, kursor feed komposit
yang tidak kehilangan baris bertimestamp identik, tie-breaker unik pada semua
paginasi, clamp halaman sebelum query, seam export bersama, dan sisa N+1
registrasi team/bundle + streaming dataset broadcast.

| Issue | Commit |
| ----- | ------ |
| EP-04 (`BE-P2-019`, `BE-P2-025`, `BE-P2-030`…`BE-P2-035`, `BE-P3-018`, `BE-P3-019`, `AUDIT-P2-009`, `AUDIT-P1-005-F1`…`F4`) | Belum ada; perubahan masih di worktree bersama lane EP-02 |

Laporan lengkap: `docs/big-changes/patching-ticket/EP-04/patching-ticket.md`.

## Hasil terukur

| Perubahan | Sebelum | Sesudah |
| --- | ---: | ---: |
| Export registrasi event, event 150k (1 form) | 4,06 s/halaman + filesort | **0,89 ms/halaman**, tanpa filesort |
| Export registrasi event, 2 form (20k + 500) | 169 / 96 ms + filesort | **0,46 + 0,47 ms**, tanpa filesort |
| Feed scan `ORDER BY ts` lintas tabel | full scan 1.029.012 / 732.832 baris + filesort | range scan 100 baris, **0,94–4,2 ms** |
| Paginasi log kehadiran `?page=999999999` | 4 query, `offset 19.999.999.960` | **2 query**, `limit 20 offset 40` |
| `LOWER(email)` lookup user | 1,5637 s / 866.292 baris | **0,0013 s / 2 baris** |
| `LOWER(invited_email)` cek duplikat bundle | 1,1259 s / 281.500 baris | **0,00037 s / 2 baris** |
| Snapshot broadcast 100k | peak 99,5 MB | **30,0 MB** |
| `RegistrationAnswersSummarizer` 300 submission | 300 query `form_fields` | **1 query** |
| Registrasi team 10 anggota | 163 query, 11 `exists()` token, 11 `fresh()` | **135 query, 1 batch token check, 0 `fresh()`** |
| Registrasi bundle 10 anggota | 49 query, 12 lookup user | **29 query, 0 lookup di loop** |

## Perubahan

- **Seam export** `app/Support/Export/` (folder baru; alasan satu baris: satu
  implementasi keyset + CSV untuk seluruh export): `KeysetPage`,
  `KeysetPageIterator`, `KeysetMergeIterator`, `CsvStream`. Dipakai export
  registrasi, attendance, interview, applicant recruitment, dan scan (CSV/XLSX).
  Iterator menolak query yang sudah punya `orderBy`/`limit`/`offset` dan
  melempar bila kursor tidak maju.
- **Kursor feed scan** `base64url(ts|eventId|recruitmentId)`; kursor memakai baris
  yang sudah dikonsumsi termasuk yang permanen di-drop, sehingga blok event/form
  soft-deleted tidak membekukan meja scan. `limit` 1..100 di service, timestamp
  kursor harus absolut (`''`/`now`/`next monday` ditolak).
- **Paginasi** tie-breaker `id` pada log kehadiran, daftar broadcast, sort event;
  `per_page` allowlist `[8,20,25,50,100]`; `page` di-clamp sebelum query.
- **Export scan** memvalidasi `kind`/`format`/`target` (422/404, bukan 500) dan
  memakai keyset per domain; CSV == XLSX == `COUNT(*)`.
- **Registrasi** team/bundle: batch lookup email → user dengan satu resolver
  `User::resolveByNormalizedEmails()` (dipakai 5 call site: submit team,
  availability check, checker bundle, 2× OAuth), token batch sekali cek,
  `fresh()` per anggota dihapus, duplicate-key → `ValidationException`.
- **`users.email_normalized`** generated column (STORED MySQL/MariaDB, VIRTUAL
  SQLite) + index: functional index ditolak karena MariaDB 10.11 tidak
  mendukung dan optimizer mengabaikan VIRTUAL indexed sampai 11.8.
- **Broadcast** dataset di-stream (`lazyById`), insert 500/batch, ringkasan
  duplikat dari agregat SQL, `preview()` satu pass streaming.
- **Audit export** dijalankan di `finally` sehingga stream yang gagal/abort tetap
  menghasilkan satu baris audit PII-free dengan jumlah baris yang sudah ditulis.
- **Observability/keamanan**: `email_normalized` di `$hidden`, tidak
  mass-assignable, tidak muncul di Inertia/CSV; cakupan accepted-risk feed scan
  didokumentasikan ulang sesuai payload sebenarnya (keputusan owner, perilaku
  tidak berubah).

## Migration

| Migration | Isi | Catatan |
| --- | --- | --- |
| `2026_11_10_000008_users_email_lookup_index` | generated `email_normalized` + index | STORED = rebuild tabel `users` ≈60 s/1 juta baris; rollback **kode dulu**, lalu `down()`; `DB_COLLATION` wajib `utf8mb4_unicode_ci`; terverifikasi di MariaDB 10.11 (container sekali pakai) |
| `2026_11_10_000009_event_attendance_pagination_indexes` | `event_attendances (event_id, scanned_at, id)`, `form_answers (form_id, created_at, id)` | add-only; kolom `id` untuk determinisme SQLite (InnoDB menambahkan PK ke secondary index); index 2 kolom lama dipertahankan |
| `2026_11_10_000010_scan_feed_indexes` | `event_attendances (scanned_at, id)`, `recruitment_attendances (checked_in_at, id)` | add-only; idempoten |

## Review dan perbaikan

`oracle` (arsitektur/performance) dan `paladin` (keamanan) meninjau seluruh lane.
Temuan yang sudah ditutup: **H1** feed stall (baris soft-deleted membekukan
cursor), **H2** `email_normalized` baru dipakai 2/5 call site, **H3** migration
`000008` rebuild tabel saat deploy + runbook/detektor migration, **H4** multi-form
export masih filesort (merge k-way, bukan residual), **M1/M2** dua keyset lokal
lain, **M3** docblock snapshot + `form_title` kosong, **M4** perubahan collation
yang membuat team error / bundle jadi guest, **M5** snapshot broadcast O(unique),
**M6** `preview()` materialisasi penuh, **M7** iterator mewarisi `orderBy`
pemanggil, **M-1** `?page=` tak terbatas, **L-1** stall feed, **L-2** 500 pada
parameter array, **L-3** audit hilang saat stream gagal, **L-5** guard progres
kursor, **L-8/L-9** clamp dan timestamp kursor, **L-10** `LOWER(invited_email)`,
**L-11** rollback migration, **L-12** memori broadcast, **L-13** first-match
nondeterministik.

**Keputusan owner:** temuan **M-2** (feed scan mengirim nama + email event dan
nama + no. registrasi + status interview OPREC lintas domain) ditutup dengan
memperluas dokumentasi risiko; perilaku tidak berubah.

Residual yang dicatat: event multi-form dengan >2 form belum diukur ulang pada
1 juta baris; plan MariaDB 10.11 untuk query export `Needs verification`;
`UserManagementService` dan `GlobalScanResolver` masih memakai `LOWER(...)` pada
kolom yang bukan `users.email` (di luar scope EP-04).

## Verifikasi

| Perintah | Hasil |
| --- | --- |
| `php artisan test --filter='Scan\|GlobalScan\|Broadcasting\|Event\|Export\|Registration\|Reporting\|RecruitmentReport\|RecruitmentInterviewExport\|FormSubmission\|Team\|Bundle\|User\|Migration'` | **580 passed, 8 skipped** (3394 assertions) |
| `php artisan test --testsuite=Unit` | **195 passed** (1428 assertions) |
| `php artisan test --testsuite=Feature` | **1.181 passed, 9 skipped, 0 failed** (9311 assertions) |
| Pint | PASS, **750 file** |
| Prettier / ESLint / `vue-tsc` / E2E types | 0 / 0 / 0 / 0 |
| Vitest | **306 passed** |
| `npm run build` + budget | PASS; entry ≤350 kB gzip, 239 chunk ≤200 kB |
| E2E attendance/report/export/interview/qr-scan/error-feedback | **27 passed** (`--retries=0`) |
| Migrasi | `000008` (MariaDB 10.11 scratch), `000009`, `000010` diterapkan idempoten; `MigrationRollbackTest` hijau |
