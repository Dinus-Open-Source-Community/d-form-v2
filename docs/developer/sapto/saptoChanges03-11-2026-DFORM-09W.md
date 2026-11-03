# Sapto Changes — 3 November 2026 (DFORM-09W)

## Ringkasan

Ticket menyebut partial unique index `form_answers` hanya berlaku di SQLite/PostgreSQL sehingga MySQL bergantung pada aplikasi. Investigasi menemukan akar yang lebih dalam: **index parsial itu juga tidak berlaku di SQLite/PostgreSQL untuk submission biasa**, karena predikatnya memakai `NOT (registration_role = 'member' AND ...)` yang bernilai NULL ketika `registration_role` NULL. Di semua driver, proteksi duplikat efektif hanya dari `FormAccessGuard`.

Perbaikan: constraint ditegakkan di database untuk semua driver — MySQL memakai generated column + unique index, SQLite/PostgreSQL memakai index parsial dengan predikat NULL-safe.

| Issue       | Commit                                                     |
| ----------- | ---------------------------------------------------------- |
| `dform-09w` | `fix(forms): enforce active answer uniqueness in database` |

## Akar masalah (Evidence: Confirmed + Reproduced)

1. **Confirmed** — `2026_06_05_000001` hanya membuat `form_answers_active_user_form_unique` bila driver `sqlite`/`pgsql`; `2026_06_11_000001` sama untuk `form_answers_active_guest_email_form_unique`. MySQL tidak pernah mendapat keduanya (`SHOW INDEX FROM form_answers` di MySQL dev: tidak ada index `*_active_*`).
2. **Reproduced** — predikat index yang ada memakai
   `NOT (registration_role = 'member' AND member_confirmation_status IN ('rejected','expired'))`.
   Untuk baris dengan `registration_role` NULL (submission single), `registration_role = 'member'` → NULL, `NOT (NULL AND ...)` → NULL, dan SQLite/PostgreSQL hanya mengindeks baris yang ekspresinya TRUE. Test regresi yang menulis langsung ke tabel gagal di SQLite **sebelum** migrasi ini (2 dari 4 kasus), membuktikan tidak ada proteksi DB.
3. **Reproduced** — di MySQL (`dform_probe_09w`, database scratch) test yang sama gagal untuk dua kasus sebelum migrasi: submit aktif kedua untuk user+form yang sama dan guest dengan `invited_email` sama berhasil masuk.

`FormAccessGuard` dan pengecekan di dalam transaksi tetap ada, tetapi bersifat TOCTOU; `SELECT ... FOR UPDATE` pada baris yang belum ada tidak menjamin serialisasi, dan `submitOtherForm()` tidak mengunci baris event.

## Keputusan

**Generated column + unique index untuk MySQL, index parsial NULL-safe untuk SQLite/PostgreSQL.**

- MySQL tidak punya partial index; unique index mengabaikan NULL, sehingga satu kolom generated yang bernilai NULL untuk baris non-aktif memberi semantik yang sama dengan index parsial. Kolom dibuat `VIRTUAL` agar penambahan tidak menyalin tabel dan index dibangun tanpa menyimpan kolom.
- Index parsial lama di SQLite/PostgreSQL dibuat ulang dengan predikat `COALESCE(...)` yang NULL-safe; tanpa ini perbaikan MySQL tidak menutup lubang yang sama di driver test.
- Alternatif yang ditolak: **enforcement service saja**. Itu tidak menutup race dua request bersamaan (kasus yang justru menjadi alasan tiket) dan tidak memberi jaminan di level data; aplikasi tetap menyimpan pengecekan untuk pesan UX.
- Migrasi **tidak menghapus data**. Bila sudah ada duplikat, migrasi berhenti dengan pesan berisi jumlah grup duplikat; pembersihan data produksi harus keputusan manusia.
- `down()` mengembalikan definisi index lama dan menghapus kolom MySQL, sehingga rollback setara keadaan sebelumnya.

## Perubahan

- `database/migrations/2026_11_03_000001_enforce_active_form_answer_uniqueness.php` (baru): preflight duplikat, DDL per driver (`active_user_form_key`/`active_guest_email_form_key` + unique index di MySQL; recreate index parsial NULL-safe di SQLite/PostgreSQL).
- `tests/Feature/Forms/FormAnswerDuplicateConstraintTest.php` (baru): menulis langsung ke `form_answers` (melewati `FormAccessGuard`) dan membuktikan database menolak duplikat; juga membuktikan baris `rejected`/member `rejected`/`expired` tidak memblokir submission baru.

Tidak ada perubahan controller, route, atau perilaku UI: pesan "already submitted" tetap dari `FormAccessGuard`.

## Verifikasi

Test regresi pada SQLite (sebelum migrasi — merah, sesudah — hijau):

```text
# sebelum migrasi
Tests: 2 failed, 2 passed (11 assertions)
  active answer for same user and form is rejected by database
  guest answer with same invited email is rejected by database

# sesudah migrasi
PASS Tests\Feature\Forms\FormAnswerDuplicateConstraintTest
Tests: 4 passed (14 assertions)
```

MySQL (database scratch `dform_probe_09w`, `phpunit.probe.xml` sementara dengan `DB_CONNECTION=mysql`):

```text
# sebelum migrasi
F..F  4 / 4 (100%)  Tests: 4, Assertions: 11, Failures: 2

# sesudah migrasi
....  4 / 4 (100%)  OK (Tests: 4, Assertions: 14)

$ php artisan migrate:rollback --step=1   → DONE, kolom/index aktif hilang
$ php artisan migrate                     → DONE (up valid setelah down)
```

Schema MySQL sesudah migrasi:

```text
form_answers_active_user_form_unique  non_unique=0  col=active_user_form_key
form_answers_active_guest_email_form_unique  non_unique=0  col=active_guest_email_form_key
active_user_form_key varchar(73) VIRTUAL GENERATED
active_guest_email_form_key varchar(292) VIRTUAL GENERATED
```

Suite penuh (`composer test` di container): **840 passed, 36 skipped** (5.275 assertions), 75 detik. `pint --test` pada dua file yang berubah: PASS.

Pemeriksaan data dev sebelum migrasi (read-only): grup duplikat user/form = 0, guest = 0, sehingga migrasi aman dijalankan pada dataset dev saat ini.

## Batasan

- CI menjalankan test suite di SQLite `:memory:`; bukti MySQL berasal dari run manual terhadap database scratch di container dev, bukan dari pipeline. Batasan ini tidak bisa ditutup tanpa job CI MySQL (layanan tambahan + biaya waktu), di luar scope tiket.
- Index parsial SQLite/PostgreSQL membandingkan `invited_email` apa adanya (case-sensitive), sama seperti index lama. Controller sudah menormalkan email ke huruf kecil sebelum insert, jadi perbedaan ini tidak teramati pada alur aplikasi.
