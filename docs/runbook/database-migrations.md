# Runbook — Database migration, rollback, dan backup

Dokumen ini adalah acuan operasional untuk setiap migration yang menyentuh
schema production. Tiket asal: `AUDIT-P1-009` (`docs/big-changes/patching.md`).

## 1. Invariant

1. **Tidak ada migration destructive tanpa backup yang terverifikasi.** Destructive
   berarti: drop column/index/FK/table, delete/truncate rows, atau perubahan
   constraint yang membuat baris lama tidak valid.
2. **Rollback schema bukan rollback data.** `down()` hanya mengembalikan
   struktur. Data yang dihapus `up()` atau hilang karena drop column tidak
   kembali; jalur pemulihannya adalah restore backup.
3. **Produksi memakai forward-fix sebagai default.** Rollback hanya dipakai bila
   schema baru terbukti merusak dan restore lebih mahal daripada forward-fix.
4. **Satu migration = satu alasan berubah.** Perubahan schema dan backfill data
   dipisah ke migration/command berbeda.

## 2. Checklist sebelum deploy (wajib, dicatat)

| # | Langkah | Bukti yang dicatat |
| --- | --- | --- |
| 1 | Identifikasi migration yang akan jalan: `php artisan migrate:status` | daftar nama + batch |
| 2 | Klasifikasikan setiap migration destructive memakai tabel §6 | kelas `reversible` / `data_loss_on_rollback` / `irreversible` |
| 3 | Ambil backup penuh sebelum `migrate` | nama file, ukuran, waktu, `sha256sum` |
| 4 | Verifikasi backup dapat dipulihkan di staging (bukan hanya dibuat) | exit code restore + `SELECT COUNT(*)` tabel domain utama |
| 5 | Pastikan backup berada di luar volume container/host yang sama | path tujuan |
| 6 | Siapkan jendela rollback dan pemilik keputusan | nama + jam |
| 7 | Untuk migration `irreversible`, ekspor data yang akan dihapus lebih dulu | path export + jumlah baris |

Backup MySQL/MariaDB dari container dev:

```bash
podman exec d_form_db sh -lc \
  'mysqldump -uroot -p"$MYSQL_ROOT_PASSWORD" --single-transaction --routines --triggers d_form' \
  > ./dform-backup-$(date +%F-%H%M).sql
gzip -9 ./dform-backup-*.sql
sha256sum ./dform-backup-*.sql.gz
```

Restore (selalu ke database terpisah dulu, lalu bandingkan hitungan baris):

```bash
podman exec -i d_form_db sh -lc \
  'mysql -uroot -p"$MYSQL_ROOT_PASSWORD" -e "CREATE DATABASE IF NOT EXISTS d_form_restore_check"'
gunzip -c ./dform-backup-YYYY-MM-DD-HHMM.sql.gz \
  | podman exec -i d_form_db sh -lc 'mysql -uroot -p"$MYSQL_ROOT_PASSWORD" d_form_restore_check'
```

> Production memakai MariaDB 10.11 (`docker-compose.prod.yml`). Gunakan
> `mariadb-dump`/`mariadb` di host production; nama opsi `--single-transaction`
> dan `--routines` sama.

## 3. Urutan deploy

```text
backup + verifikasi  ->  migrate --force  ->  smoke test  ->  monitoring
                            |                     |
                            v                     v
                        gagal/rusak  ->  keputusan: forward-fix | rollback | restore
```

1. `php artisan migrate --force` **hanya setelah** langkah §2 selesai.
2. Smoke test minimal: `GET /up` = 200, login, list event, list recruitment,
   satu submit form, satu scan QR.
3. Bila migration gagal di tengah: **jangan** mengulang `migrate` sebelum
   membaca `migrations` + `information_schema`. Migration di repo ini idempoten
   sebagian, bukan seluruhnya.
4. Bila aplikasi rusak setelah migration sukses: pilih forward-fix bila
   perbaikannya < 30 menit; selain itu rollback (`php artisan migrate:rollback
   --step=N`) lalu restore data dari backup bila ada kolom yang ter-drop.

## 4. Verifikasi rollback (sebelum menyentuh production)

Rollback set penuh diuji pada database sekali pakai, **bukan** pada database
aplikasi. Perintah ini yang dipakai saat menutup `AUDIT-P1-009`:

```bash
# MySQL: seluruh migration, migrate -> rollback semua -> migrate ulang
podman exec d_form_db sh -lc \
  'mysql -uroot -p"$MYSQL_ROOT_PASSWORD" -e "DROP DATABASE IF EXISTS d_form_rollback_check; CREATE DATABASE d_form_rollback_check"'
podman exec -w /app d_form_app sh -lc 'DB_DATABASE=d_form_rollback_check php artisan migrate --force'
podman exec -w /app d_form_app sh -lc 'DB_DATABASE=d_form_rollback_check php artisan migrate:rollback --force'

# SQLite (driver yang dipakai test suite)
podman exec -w /app d_form_app sh -lc \
  'rm -f /tmp/rt.sqlite && touch /tmp/rt.sqlite && DB_CONNECTION=sqlite DB_DATABASE=/tmp/rt.sqlite php artisan migrate --force && DB_CONNECTION=sqlite DB_DATABASE=/tmp/rt.sqlite php artisan migrate:rollback --force'
```

Otomatisasi yang menjaga invariant ini:

```bash
# jalankan sebelum merge/deploy (suite Feature belum dijalankan CI; lihat INFRA-P3-021)
podman exec -w /app d_form_app php artisan test tests/Feature/Migrations

# MySQL, opt-in; nama database wajib berakhiran _check atau _test
podman exec -w /app d_form_app sh -lc \
  'MIGRATION_ROLLBACK_DATABASE=d_form_migration_check php artisan test --filter=MigrationRollbackTest'
```

`tests/Feature/Migrations/MigrationRollbackTest.php` melakukan
migrate → rollback semua → migrate ulang, dan menolak (fail) bila ada migration
destructive yang belum terdaftar di tabel §6.

## 5. Expand → backfill → audit → contract (`form_answers`)

Urutan ini **wajib** dan tidak boleh digabung. Setiap tahap punya kriteria
berhenti sendiri.

| Tahap | Perintah | Kriteria lanjut |
| --- | --- | --- |
| 1. Expand | `php artisan migrate --path=database/migrations/2026_11_03_000001_enforce_active_form_answer_uniqueness.php --force` | kolom `form_answers.is_active` ada; tidak ada unique index baru |
| 2. Backfill | `php artisan form-answers:backfill-active-marker --chunk=1000` | command exit 0; boleh diulang; resume dengan `--after-id=<uuid terakhir>` |
| 3. Audit | `php artisan form-answers:audit-active-uniqueness` | **exit 0**. Bila exit 1: duplicate group / unmarked rows > 0, hentikan, jangan lanjut ke contract |
| 4. Contract | `php artisan migrate --path=database/migrations/2026_11_04_000002_create_active_form_answer_indexes.php --force` | dua unique index ada; duplicate-key violation berarti data belum bersih |

Aturan tambahan:

- Migration contract **tidak memperbaiki data**. Duplicate key violation =
  rollback transaksi, tidak ada baris dihapus. Operator menyelesaikan duplikat
  secara eksplisit lalu mengulang.
- Backfill boleh berjalan berkali-kali; audit tidak menulis apa pun.
- Rollback contract (`down()`) hanya melepas kedua unique index. Kolom
  `is_active` tetap ada karena milik tahap expand (`2026_11_03_000001_enforce_active_form_answer_uniqueness`) dan baru
  dihapus bila expand ikut di-rollback. Jangan rollback contract lalu langsung
  menjalankan aplikasi versi baru tanpa mengulang tahap 1-4.

## 6. Inventaris migration destructive

Kelas:

- `reversible` — `down()` mengembalikan struktur ke kondisi sebelumnya tanpa
  kehilangan data operasional (index/kolom display/kolom turunan).
- `data_loss_on_rollback` — rollback berhasil secara schema, tetapi kolom yang
  di-drop memuat data yang ditulis setelah migration. Pulihkan dari backup bila
  data itu dibutuhkan.
- `irreversible` — `up()` menghapus/merombak baris atau tabel yang bukan
  miliknya. `down()` tidak dapat memulihkan; satu-satunya jalur adalah backup
  atau forward-fix.

Tabel ini memuat 32 migration: 24 di antaranya **dapat kehilangan data** dan
diwajibkan oleh guard otomatis di `MigrationRollbackTest` (drop column,
`delete`/`truncate`, atau drop tabel yang tidak ia buat); 8 sisanya hanya
membalik index/FK (kelas `reversible`) tetapi tetap didokumentasikan karena
kelompok itulah yang paling sering memicu error 1553/1091. Drift FK/index
dideteksi oleh round-trip test + assertion fidelity: FK `form_answers` setelah
rollback bertarget (SQLite) dan FK `recruitment_evaluations` /
`recruitment_interviews` setelah rollback bertarget (MySQL opt-in).

| Migration | Operasi | Kelas | Rencana rollback |
| --- | --- | --- | --- |
| `2026_03_05_221343_add_github_id_to_users_table` | drop `users.github_id` | data_loss_on_rollback | link OAuth GitHub dibuat ulang saat login berikutnya; tidak perlu restore |
| `2026_04_17_000001_add_registered_count_to_events_table` | drop `events.registered_count` | reversible | counter turunan; hitung ulang dari `form_answers` bila diperlukan |
| `2026_05_02_100001_add_unique_to_form_answers` | drop unique `(user_id, form_id)` | reversible | `down()` melepas FK `form_answers_user_id_foreign` → drop unique → memasang kembali FK (auto index aslinya kembali, tanpa index sisa) |
| `2026_05_03_000001_add_banner_to_forms_table` | drop `banner_url`, `banner_caption` | reversible | kolom display nullable |
| `2026_05_06_000001_add_review_columns_to_form_answers_table` | drop `review_status`, `reviewed_at`, `reviewed_by` | data_loss_on_rollback | backup; keputusan review hilang bila tidak di-export |
| `2026_05_07_100000_add_registration_code_and_email_notification_type` | drop `registration_code`, `notification_type` | data_loss_on_rollback | backup wajib: kode registrasi dipakai QR peserta |
| `2026_05_07_120000_add_slug_to_events_table` | drop unique + `events.slug` | data_loss_on_rollback | backup; URL publik berubah bila slug dibuat ulang |
| `2026_05_08_000001_add_metadata_to_forms_and_is_append_to_form_fields` | drop `forms.metadata`, `form_fields.is_append` | data_loss_on_rollback | backup; definisi builder hilang |
| `2026_05_09_000001_add_team_registration_columns_to_form_answers_table` | drop kolom team/leader | data_loss_on_rollback | backup; relasi leader-member hilang |
| `2026_05_10_100000_m4c_bundle_registration_columns_on_form_answers` | drop kolom bundle + index `(form_id, group_token)` | data_loss_on_rollback | `down()` melepas FK `form_answers_form_id_foreign` → drop index → memasang kembali FK; data bundle hilang |
| `2026_06_05_000001_allow_multiple_form_answers_per_user_after_rejection` | drop unique lama + index pendukung | reversible | `down()` mengembalikan unique `(user_id, form_id)`; gagal (fail-closed) bila ada duplikat aktif |
| `2026_06_11_000001_allow_guest_bundle_members_on_form_answers` | rebuild `form_answers`, `user_id` NOT NULL | data_loss_on_rollback | baris guest tanpa `user_id` tidak bisa dikembalikan; bersihkan/ekspor dulu |
| `2026_06_12_133638_add_created_by_to_events_table` | drop `events.created_by` | data_loss_on_rollback | backup; kepemilikan event hilang, policy `created_by` gagal |
| `2026_06_12_133654_add_created_by_to_forms_table` | drop `forms.created_by` | data_loss_on_rollback | backup; kepemilikan form hilang |
| `2026_07_23_015018_add_success_content_to_forms_table` | drop `forms.success_content` | reversible | kolom display nullable |
| `2026_09_07_100000_add_recruitment_application_id_to_email_logs` | drop `email_logs.recruitment_application_id`; `event_id` tetap nullable | data_loss_on_rollback | audit email recruitment hilang; `down()` sengaja tidak mengembalikan `event_id` NOT NULL karena baris recruitment punya `event_id` NULL |
| `2026_09_16_000001_add_created_by_to_recruitment_periods_table` | drop `recruitment_periods.created_by` | data_loss_on_rollback | backup |
| `2026_09_20_000001_add_sections_to_recruitment_screenings` | drop `sections` | data_loss_on_rollback | backup |
| `2026_09_20_000002_add_banner_to_recruitment_periods` | drop `banner` | reversible | kolom display nullable |
| `2026_09_20_000003_add_instagram_follow_and_twibbon_to_recruitment_documents` | drop kolom dokumen | data_loss_on_rollback | backup |
| `2026_10_01_000003_add_whatsapp_group_url_to_recruitment_periods_table` | drop `whatsapp_group_url` | data_loss_on_rollback | backup |
| `2026_10_07_120000_recruitment_waiting_room_refactor` | drop tabel `recruitment_queue_entries`; delete/rewrite baris interview | irreversible | **tidak ada rollback schema**. Ekspor `recruitment_queue_entries` + interview terdampak sebelum deploy; pemulihan hanya via backup/forward-fix |
| `2026_10_08_000001_secondary_interview_support` | drop unique lama, tambah `interview_kind` + `save_count` | data_loss_on_rollback | `down()` mengembalikan unique `(application_id)`; gagal (fail-closed) bila interview secondary masih ada — hapus/ekspor dulu |
| `2026_10_08_000002_add_whatsapp_group_aa_member_urls_to_recruitment_periods_table` | drop dua kolom URL | data_loss_on_rollback | backup |
| `2026_10_23_000001_add_dashboard_indexes_to_events_table` | drop tiga index | reversible | index performa saja |
| `2026_10_23_000002_add_registered_count_index_to_events_table` | drop `events_deleted_registered_count_index` | reversible | index performa saja |
| `2026_10_23_000003_add_created_at_index_to_form_answers_table` | drop `form_answers_created_at_index` | reversible | index performa saja |
| `2026_10_24_013527_add_performance_indexes_round2` | drop tiga index performa | reversible | index performa saja |
| `2026_10_24_193000_add_deleted_title_index_to_events_table` | drop `events_deleted_title_index` | reversible | index performa saja |
| `2026_11_03_000001_enforce_active_form_answer_uniqueness` | tambah `is_active`; drop index/kolom lama | reversible | rollback menghapus marker; ulangi §5 bila aplikasi versi baru tetap dipakai |
| `2026_11_04_000002_create_active_form_answer_indexes` | tambah dua unique index; `down()` hanya melepas kedua index (kolom `is_active` milik tahap expand) | reversible | create index **fail-closed** saat ada duplikat aktif; lihat §5 |
| `2026_11_04_000003_add_delivery_key_to_email_logs_table` | drop unique + `email_logs.delivery_key` | data_loss_on_rollback | ledger idempotency email hilang; retry email berisiko duplikat setelah rollback |

## 7. Batasan yang diketahui

1. **`docker/entrypoint.prod.sh` menjalankan `php artisan migrate --force` pada
   setiap start container.** Tidak ada approval gate, backup otomatis, atau
   verifikasi pasca-migrate di pipeline. Setiap deploy karena itu harus
   memperlakukan §2 sebagai prasyarat, dan migration destructive berisiko
   berjalan tanpa operator siap. Perbaikan entrypoint dilacak terpisah pada
   tiket `INFRA-P3-022`.
2. **MySQL menolak drop index yang masih menopang foreign key (error 1553).**
   `down()` migration lama melepas FK lebih dulu, men-drop index/unique, lalu
   memasang kembali FK dengan definisi aslinya. Migration baru yang men-drop
   index harus mengikuti pola yang sama.
   **Biaya:** `ALTER TABLE form_answers ADD CONSTRAINT ... FOREIGN KEY` di MySQL
   tidak mendukung `ALGORITHM=INPLACE` (error 1846) dan berjalan sebagai
   `COPY`/rebuild tabel. Pada tabel besar (dataset stress 1 juta baris) rollback
   `2026_05_02_100001_add_unique_to_form_answers`/`2026_05_10_100000_m4c_bundle_registration_columns_on_form_answers` karena itu **blocking dan mahal**;
   ukur dulu di staging (`Needs verification` untuk angka produksi).
3. **SQLite (test suite) tidak mendukung `dropForeign` dengan nama string.**
   Gunakan `dropForeign(['kolom'])` (table rebuild) atau branch driver.
4. **Rollback tidak memulihkan data.** Untuk kelas
   `data_loss_on_rollback`/`irreversible`, backup adalah satu-satunya jalur.
5. **`database/migrations/*.bak` tidak dieksekusi Laravel**, tetapi tetap artefak
   yang membingungkan operator; pembersihannya dilacak di `HYG-001`.

## 8. Insiden: rollback gagal di tengah

1. Jangan mengulang `migrate:rollback` secara membabi buta.
2. Baca migration yang gagal dari output + `SELECT * FROM migrations ORDER BY id DESC LIMIT 5`.
3. Periksa `information_schema.statistics`/`TABLE_CONSTRAINTS` untuk index/FK
   yang tersisa (`1553`/`1091`).
4. Pilih: (a) forward-fix schema, (b) selesaikan dependency index/FK lalu ulangi
   `migrate:rollback --step=1`, atau (c) restore backup ke database bersih dan
   deploy versi sebelumnya.
5. Catat keputusan, perintah, dan hasil di changelog tiket.
