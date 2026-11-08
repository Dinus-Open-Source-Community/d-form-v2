# Sapto Changes — 8 November 2026 (AUDIT-P1-009)

## Ringkasan

Rollback schema sekarang benar-benar dapat dijalankan. Sebelum patch,
`php artisan migrate:rollback` gagal pada MySQL di 5 migration dan pada SQLite di
3 migration; `down()` beberapa migration juga meninggalkan schema yang berbeda
dari kondisi sebelum migrasi (FK hilang, index sisa) atau menghapus kolom milik
migration lain. Setelah patch, migrasi penuh (57 file) berhasil
`migrate → rollback semua → migrate` pada MySQL dan SQLite, dan setiap migration
destruktif punya rencana rollback di runbook baru.

| Issue | Commit |
| ----- | ------ |
| AUDIT-P1-009 | belum di-commit (working tree masih memuat perubahan sesi lain; commit terpisah menunggu koordinasi) |

Laporan lengkap: `docs/big-changes/patching-ticket/AUDIT-P1-009.md`.
Runbook: `docs/runbook/database-migrations.md`.

## Akar masalah (bukti, bukan gejala)

| Migration | Driver | Gejala terukur | Akar masalah |
| --------- | ------ | -------------- | ------------ |
| `2026_05_02_100001_add_unique_to_form_answers` | MySQL | `SQLSTATE 1553 Cannot drop index 'form_answers_user_form_unique': needed in a foreign key constraint` | InnoDB mengikat FK `form_answers_user_id_foreign` ke unique tersebut; `down()` men-drop unique tanpa melepas FK |
| `2026_05_10_100000_m4c_bundle_registration_columns_on_form_answers` | MySQL | `1553 ... 'form_answers_form_id_group_token_index'` | FK `form_answers_form_id_foreign` mengandalkan index itu setelah migration `2026_06_05` melepas index pendukungnya |
| `2026_06_12_133638_add_created_by_to_events_table` | MySQL + SQLite | `1091 Can't DROP 'created_by'` / `RuntimeException: This database driver does not support dropping foreign keys by name` | `dropForeign('created_by')` memakai nama kolom, bukan nama constraint |
| `2026_06_12_133654_add_created_by_to_forms_table` | MySQL + SQLite | sama dengan di atas | sama |
| `2026_09_07_100000_add_recruitment_application_id_to_email_logs` | SQLite | `RuntimeException: This database driver does not support dropping foreign keys by name` | `dropForeign('email_logs_rec_app_fk')` (string) tidak didukung SQLite; tidak ada branch driver |
| `2026_10_08_000001_secondary_interview_support` | MySQL | `1553 ... 'rec_eval_interview_uniq'` | FK `rec_eval_interview_fk` menopang unique yang di-drop; `down()` tidak melepas/memasang ulang FK |
| `2026_11_04_000002_create_active_form_answer_indexes` | semua | tidak error, tetapi rollback satu langkah menghapus `form_answers.is_active` | `down()` menghapus kolom milik migration expand (`2026_11_03`), bukan hanya index miliknya |

Pengukuran sebelum patch (container `d_form_app`, PHP 8.4.27; MySQL 9.0.1;
scratch DB `d_form_rollback_check`):

```text
$ DB_DATABASE=d_form_rollback_check php artisan migrate --force      # exit 0, 57 migration (saat pengukuran awal)
$ DB_DATABASE=d_form_rollback_check php artisan migrate:rollback --force  # exit 1
  ↳ 2026_10_08_000001_secondary_interview_support .... FAIL (1553)
```

Inventaris rollback penuh pada MySQL: `5 FAIL` dari 49 langkah yang sempat
berjalan; dua di antaranya membuat langkah setelahnya tidak dapat dievaluasi.
SQLite: `3 FAIL`.

## Keputusan desain

| Keputusan | Alasan / alternatif yang ditolak |
| --------- | -------------------------------- |
| Melepas FK lalu memasangnya kembali, bukan menambah index pendukung permanen | Menambah index menghilangkan error 1553, tetapi setelah rollback schema menyimpan index yang tidak ada sebelum migration. Drop + re-add FK mengembalikan tepat kondisi awal (FK + auto index-nya). Alternatif "biarkan index sisa" ditolak karena rollback harus idempoten dan setara dengan kondisi pra-migration. |
| `dropForeign(['kolom'])` untuk FK bernama konvensi | SQLite hanya mendukung bentuk array (table rebuild); MySQL menerima keduanya. Satu bentuk yang bekerja di dua driver lebih baik daripada dua cabang tanpa alasan. |
| Branch driver eksplisit untuk FK bernama custom (`email_logs_rec_app_fk`) | Nama custom tidak dapat diturunkan dari kolom; SQLite tidak mendukung drop-by-name. Branch driver adalah pola yang sudah dipakai migration lain di repo. |
| `down()` contract hanya melepas index | `up()` contract hanya membuat index; satu migration = satu alasan berubah. Menghapus `is_active` di contract membuat rollback satu langkah merusak aplikasi yang masih membaca kolom tersebut. |
| `down()` gagal (fail-closed) bila data tidak kompatibel | Mengembalikan `unique(application_id)` saat interview secondary masih ada akan menghapus data bila dipaksa. Lebih baik database menolak dan operator memutuskan eksplisit. |
| Rollback `email_logs.event_id` NOT NULL tidak dipulihkan | Baris recruitment yang dibuat setelah migration memiliki `event_id` NULL; memaksa NOT NULL akan gagal. Dicatat sebagai limitasi eksplisit di kode dan runbook, bukan diperbaiki dengan menebak data. |
| Verifikasi rollback memakai database sekali pakai | Rollback tidak boleh diuji pada database aplikasi. Test MySQL opt-in membuat/menghapus database berakhiran `_check`/`_test` dan menolak berjalan di production. |
| Runbook di `docs/runbook/` (folder baru) | Tiket meminta runbook deploy yang dapat dirujuk operator. Folder baru dipilih agar dokumen operasional tidak bercampur dengan `docs/rules/` (konvensi kode) atau `docs/big-changes/` (audit). |

## Perubahan

| File | Perubahan |
| ---- | --------- |
| `database/migrations/2026_05_02_100001_add_unique_to_form_answers.php` | `down()`: pada MySQL lepas FK `form_answers_user_id_foreign` (guarded `information_schema`), drop unique, pasang kembali FK dengan definisi asli (`cascadeOnUpdate`). Tidak ada `up()` yang berubah. |
| `database/migrations/2026_05_10_100000_m4c_bundle_registration_columns_on_form_answers.php` | `down()`: pola sama untuk `form_answers_form_id_foreign` sebelum drop `form_answers_form_id_group_token_index`. |
| `database/migrations/2026_06_12_133638_add_created_by_to_events_table.php` | `down()`: `dropForeign(['created_by'])`. |
| `database/migrations/2026_06_12_133654_add_created_by_to_forms_table.php` | `down()`: `dropForeign(['created_by'])`. |
| `database/migrations/2026_09_07_100000_add_recruitment_application_id_to_email_logs.php` | `down()`: branch driver (`sqlite` array form, `mysql` nama custom guarded, `pgsql` guarded `pg_constraint`), guard kolom/index, early return; komentar mencatat `event_id` NOT NULL sengaja tidak dipulihkan. |
| `database/migrations/2026_10_08_000001_secondary_interview_support.php` | `down()` MySQL: drop FK yang menopang unique → drop unique → drop kolom → pasang kembali unique lama → pasang kembali kedua FK (`rec_eval_app_fk`, `rec_eval_interview_fk`, `rec_interviews_app_fk`) dengan definisi asli; semua guarded; jalur non-MySQL tidak berubah. |
| `database/migrations/2026_11_04_000002_create_active_form_answer_indexes.php` | `down()` hanya melepas dua unique index; kolom `is_active` (milik expand `2026_11_03`) dipertahankan. Import `Blueprint` yang menjadi tidak terpakai dihapus. |
| `tests/Feature/Migrations/MigrationRollbackTest.php` (baru) | Round-trip penuh SQLite pada koneksi `:memory:` terpisah (selalu jalan), round-trip penuh MySQL opt-in (`MIGRATION_ROLLBACK_DATABASE`, menolak production dan nama database tanpa akhiran `_check`/`_test`), contract-rollback marker guard (langkah dihitung dari tabel `migrations`), dan guard bahwa setiap migration yang dapat kehilangan data terdaftar di runbook. |
| `docs/runbook/database-migrations.md` (baru) | Checklist backup/verifikasi, urutan deploy, verifikasi rollback, urutan expand → backfill → audit → contract, inventaris 32 migration dengan kelas reversibility (24 data-loss, 8 index/FK), batasan yang diketahui, prosedur insiden rollback gagal. |
| `docs/big-changes/patching.md` | Status `AUDIT-P1-009` → `In Review`; tiket baru `INFRA-P3-022` (entrypoint produksi auto-migrate tanpa gate). |

Tidak ada migration baru, tidak ada perubahan `up()`, tidak ada perubahan schema
pada database aplikasi, tidak ada dependency baru, dan tidak ada perubahan
runtime aplikasi.

### Koreksi setelah review independen

Review pertama memberi verdict REJECT dengan satu blocker: `down()`
`2026_10_08_000001` menghapus kolom dan FK sebelum constraint yang menentukan
legalitas rollback. Perbaikan:

- `assertRollbackHasNoConflicts()` dijalankan paling awal (sebelum DDL apa pun)
  dan melempar `RuntimeException` bila ada aplikasi dengan lebih dari satu
  interview/evaluation; rollback sekarang benar-benar fail-closed.
- Branch non-MySQL `down()` diberi guard `hasIndex`/`hasColumn` sehingga
  idempoten pada schema parsial.
- Dua blok guard `information_schema`/`pg_constraint` di `2026_09_07_100000`
  digabung menjadi satu `foreignKeyExists()`.
- Regression test ditambah: fidelity FK/index setelah rollback bertarget, dan
  bukti schema tidak berubah saat rollback ditolak.
- Runbook diperbaiki: klaim CI, mekanisme drop/re-add FK, kontradiksi `is_active`,
  dan biaya rebuild FK (`ALGORITHM=INPLACE` tidak didukung, error 1846).

Putaran review ketiga (APPROVE WITH FIXES) menambah assertion `unique(application_id)`
pada fidelity MySQL (mutation check sebelumnya membuktikan suite tetap hijau bila
constraint hilang) dan memperbaiki frasa pesan abort untuk rollback `--step=N`.
Reviewer memverifikasi schema hasil rollback identik dengan baseline pada MySQL
dan SQLite.

Putaran review kedua (APPROVE WITH FIXES) menutup empat hal lagi: pesan abort
dibuat akurat terhadap rollback bertahap, test MySQL menambah fidelity FK
`recruitment_evaluations`/`recruitment_interviews`, `unique(application_id)`
dibuat sebelum DDL destruktif agar race tidak bisa merusak schema, dan assertion
FK di test konflik SQLite (yang tidak mungkin gagal) diganti komentar jujur.

## Verifikasi

### Rollback (Measured, container)

| Perintah | Hasil |
| -------- | ----- |
| MySQL: `migrate --force` lalu `migrate:rollback --force` pada `d_form_rollback_check` | exit 0; seluruh migration yang ada saat pengukuran DONE di kedua arah; `migrations` kosong setelah rollback |
| SQLite: `migrate --force` lalu `migrate:rollback --force` pada file scratch | exit 0; seluruh migration DONE; 0 `RuntimeException`/`SQLSTATE` |
| Fidelity `--step=48` (berhenti sebelum `create_form_answers_table`) | FK `form_answers_user_id_foreign`, `form_answers_form_id_foreign` ada; tidak ada `form_answers_user_id_index`/`form_answers_form_id_index`/`form_answers_user_form_unique` |
| Fidelity `--step=24` (berhenti sebelum `create_recruitment_interview_tables`) | `rec_eval_app_fk`, `rec_eval_interview_fk`, `rec_eval_user_fk`, `rec_interviews_app_fk` semuanya kembali |
| `EXPLAIN` pada dua query contract (`user_id,form_id,is_active` dan `form_id,invited_email,is_active`) | index `form_answers_active_user_form_unique` (type `const`) dan `form_answers_active_guest_email_form_unique` (type `ref`) dipakai; tanpa full scan |

### Test suite

| Perintah | Hasil |
| -------- | ----- |
| `php artisan test tests/Feature/Migrations` | 5 passed, 1 skipped (MySQL opt-in), 26 assertions, 1,30 s |
| `MIGRATION_ROLLBACK_DATABASE=d_form_migration_check php artisan test --filter=..._on_mysql` | 1 passed, 19 assertions, 24,72 s |
| `php artisan test` (suite penuh) | 1130 passed, 8 skipped, 7061 assertions, 86,78 s (jumlah bergerak karena branch membawa lane lain) |
| `./vendor/bin/pint --test` pada 7 migration + 1 test | PASS 8 files |

Catatan kejujuran: run pertama suite penuh menampilkan 4 kegagalan di
`tests/Feature/Security/TrustedProxyTest`. File itu milik lane lain yang belum
di-commit (BE-P3-011) dan tidak menyentuh migration; re-run bersih memberi
1104 passed. Kegagalan tersebut tidak berkaitan dengan perubahan di tiket ini,
tetapi perlu diverifikasi ulang oleh pemilik lane tersebut sebelum commit.

### E2E browser (desktop + mobile + anomali)

| Run | Hasil |
| --- | --- |
| `--project=setup` | 1 passed |
| desktop (`form-submission-boundary`, `form-upload-max-size`, `recruitment-tracking-throttle`, `security-headers`, `auth-guard`) | 30 passed (2,2 m) |
| mobile (`form-answers-mobile`) | 5 passed, 1 flaky (retry hijau; `net::ERR_ABORTED` saat navigasi tab editor) |
| anomali (browser nyata, dinavigasi dari `/events`) | field wajib kosong → `Nama lengkap wajib diisi.`; nilai terlarang → `Format Nama lengkap tidak valid.`; URL tetap di form; `form_answers` untuk form tersebut tetap 0 baris |
| health akhir | `GET /up` = 200 |

Batasan: tidak ada form publik terbuka yang memiliki `input[type=file]` saat
pengujian, sehingga anomali non-image hanya tercakup oleh spec
`form-upload-max-size` (server-side). Detail di laporan.

## Limitasi dan tindak lanjut

- `2026_10_07_120000_recruitment_waiting_room_refactor` **irreversible**:
  `up()` menghapus tabel `recruitment_queue_entries` dan baris interview; `down()`
  hanya melepas kolom `booked_at`. Satu-satunya jalur pemulihan adalah backup.
  Terdaftar di runbook §6.
- `docker/entrypoint.prod.sh` menjalankan `migrate --force` pada setiap start
  container tanpa backup/approval. Dicatat sebagai tiket baru `INFRA-P3-022`.
- `2026_09_07_100000::down()` tidak mengembalikan `email_logs.event_id` NOT NULL;
  dicatat sebagai limitasi di kode + runbook dan menjadi bagian `AUDIT-P2-013`.

## Checklist

- [x] `down()` seluruh migration berjalan pada MySQL (seluruh batch) dan SQLite.
- [x] Schema setelah rollback setara dengan kondisi pra-migration (FK pulih, tidak
      ada index sisa).
- [x] Setiap migration destruktif punya rencana rollback terdokumentasi.
- [x] Urutan expand → backfill → audit → contract terdokumentasi dengan command.
- [x] Runbook deploy berisi checklist backup/restore dan prosedur insiden.
- [x] Guard otomatis mencegah migration destruktif baru tanpa entri runbook.
- [x] Tidak ada perubahan `up()`, schema aplikasi, atau dependency.
- [x] Suite penuh hijau (1130 passed / 8 skipped) dan E2E desktop+mobile hijau termasuk uji anomali input.
