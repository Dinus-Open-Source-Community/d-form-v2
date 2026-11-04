# Sapto Changes — 3 November 2026 (DFORM-09W)

## Ringkasan

> **Status 4 November 2026:** Bagian awal dokumen ini merekam percobaan
> generated-column yang kemudian terbukti tidak kompatibel dengan MariaDB 10.11.
> Addendum di bawah adalah keputusan dan implementasi yang berlaku sekarang.

Ticket menyebut partial unique index `form_answers` hanya berlaku di SQLite/PostgreSQL sehingga MySQL bergantung pada aplikasi. Investigasi menemukan akar yang lebih dalam: **index parsial itu juga tidak berlaku di SQLite/PostgreSQL untuk submission biasa**, karena predikatnya memakai `NOT (registration_role = 'member' AND ...)` yang bernilai NULL ketika `registration_role` NULL. Di semua driver, proteksi duplikat efektif hanya dari `FormAccessGuard`.

Percobaan awal (superseded oleh addendum): constraint hendak ditegakkan dengan generated
column di MySQL dan index parsial NULL-safe di SQLite/PostgreSQL.

## Addendum 4 November 2026 — Opsi A portabel (current)

Keputusan user untuk `dform-09w` dan `dform-kzc` adalah pola audit → expand → backfill → contract.
Migration generated-column lama ditulis ulang karena **belum pernah berhasil dijalankan di
production**; rewrite ini tidak menghapus baris data dan menghilangkan risiko deploy MariaDB.

Schema akhir dibuat sama secara logis pada SQLite, PostgreSQL, MySQL, dan MariaDB:

- `form_answers.is_active TINYINT NULL`: `1` untuk submission aktif, `NULL` untuk submission tidak aktif.
- `form_answers_active_user_form_unique`: unique `(user_id, form_id, is_active)`.
- `form_answers_active_guest_email_form_unique`: unique `(form_id, invited_email, is_active)`.
- Generated columns dan partial unique indexes lama dibuang oleh migration expand secara idempotent.
  Cabang driver hanya dipakai untuk sintaks `ALGORITHM=INSTANT`/`INPLACE, LOCK=NONE`, bukan untuk
  menghasilkan schema yang berbeda.

### Implementasi

- `2026_11_03_000001_enforce_active_form_answer_uniqueness.php` sekarang hanya membersihkan sisa
  percobaan lama dan menambah `is_active` tanpa index. Pembersihan memakai pemeriksaan `IF EXISTS`
  dan tidak menghapus baris.
- `form-answers:audit-active-uniqueness` menjalankan audit read-only untuk jumlah duplicate group
  user/guest dan kelengkapan marker. Exit code non-zero berarti ada temuan; command tidak menulis
  data. Sebelum expand, pemeriksaan kelengkapan ditunda karena kolom marker belum ada; jalankan
  audit lagi setelah backfill.
- `2026_11_04_000002_create_active_form_answer_indexes.php` hanya membuat dua index online dan
  mempertahankan duplicate-key exception sebagai jaring terakhir. Tidak ada GROUP BY pra-index
  atau pembersihan otomatis. `down()` menjatuhkan kedua index dan kolom marker.
- `form-answers:backfill-active-marker` memproses `chunkById` (default 1.000), mendukung
  `--after-id`, idempotent, dan mencetak `Rows scanned`, `Rows changed`, serta `Last ID`.
- `FormAnswerObserver` adalah choke point lifecycle Eloquent untuk marker create/update; production
  registration single/team/bundle memakai `FormAnswerWriter`. E2E fixture dan stress bulk insert
  mengisi marker eksplisit.

### Urutan deploy

1. Jalankan `form-answers:audit-active-uniqueness` sebelum deploy. Audit ini mendeteksi duplicate
   aktif memakai predikat domain yang sama; sebelum expand, kelengkapan marker dilaporkan sebagai
   deferred.
2. Jalankan expand migration untuk membersihkan sisa percobaan lama dan menambah `is_active`.
3. Jalankan `form-answers:backfill-active-marker --chunk=1000` sampai selesai/resumable.
4. Jalankan audit lagi. Contract hanya boleh dilanjutkan bila duplicate group user/guest dan
   `Unmarked active rows` semuanya `0`.
5. Jalankan contract migration untuk membangun kedua unique index.

### Verifikasi aktual

Evidence `Measured` pada local MySQL 9 dev (`form_answers` 1.001.048 baris sebelum rollback lokal):

```text
expand migration: DONE (is_active TINYINT NULL; no uniqueness index)
backfill --chunk=1000: Rows scanned 1,001,051; Rows changed 1,000,764
backfill resume --after-id=ffffe1b2-2ab4-583e-ab54-b701a5820ab6:
  Rows scanned: 0; Rows changed: 0
old full audit command before marker-aware query selection: 3m 38.469s
optimized full audit command after marker-aware query selection: 7.628s
optimized query breakdown: user duplicates 1.121s; guest duplicates 0.002s;
unmarked active rows (required full predicate) 5.791s
separate online index build measurement:
  user index 6.160s; guest index 5.808s; total 11.969s
```

Target command time `<3 detik` belum tercapai karena kelengkapan marker wajib mengevaluasi
predikat penuh pada baris `is_active IS NULL`; mempercepat bagian itu lebih jauh membutuhkan
keputusan schema/index tambahan di luar Opsi A yang disetujui.

Row count bertambah selama app dev tetap hidup, sehingga angka command di atas bukan benchmark
dataset statis. Schema nyata sesudah build: `is_active` adalah `tinyint` nullable dan kedua index
memiliki `Non_unique=0` dengan tiga kolom komposit yang diminta.

Transactional insert probe MySQL nyata menghasilkan:

```text
active_first_insert=true
active_duplicate_insert=false (SQLSTATE 23000)
inactive_first_insert=true
inactive_second_insert=true
rolled_back=true
```

Image `mariadb:10.11` lokal juga menjalankan seluruh migration dari database kosong sampai selesai,
audit command, backfill command (`Rows scanned: 0`), dan probe insert yang sama dengan hasil duplicate
aktif ditolak serta dua baris nonaktif diterima. Durasi MariaDB production **belum diukur**; wajib diukur di
staging yang merepresentasikan ukuran dan konfigurasi production sebelum deploy.

Output audit MariaDB fresh:

```text
Active user duplicate groups: 0
Active guest duplicate groups: 0
Unmarked active rows: 0
Active uniqueness audit passed; no rows were changed.
```

Index guest sengaja mencakup `invited_email = ''` karena schema akhir memakai composite unique
index tanpa predicate; jalur aplikasi hanya menghasilkan email ter-normalisasi atau `NULL`, dan
verifikasi dev menemukan **0** baris dengan email kosong.

Rollback contract menjatuhkan kedua composite index dan kolom `is_active`; ia **tidak** membuat
kembali partial index lama. Hasil rollback adalah schema tanpa enforcement uniqueness database,
sehingga rollback hanya mitigasi sementara dan perlu diikuti keputusan operasional.

Regression suite di container: **844 passed, 7 skipped** (5.413 assertions, 71.389 detik; baseline
sebelum audit-query fix). Targeted marker, audit, and constraint tests: **12 passed** (56 assertions). Pint pada
file perubahan: PASS. Tidak ada production
access, deploy, push, atau penghapusan baris.

| Issue       | Commit                                                     |
| ----------- | ---------------------------------------------------------- |
| `dform-09w` | `33a3e98`, `16790a0`                                        |
| `dform-kzc` | `33a3e98`, `16790a0`                                        |

## Catatan historis — investigasi sebelum Opsi A (superseded)

Bagian berikut merekam bukti dan alternatif dari investigasi awal. Ia dipertahankan untuk audit
keputusan, tetapi bukan definisi schema atau runbook deployment saat ini; addendum Opsi A di atas
adalah sumber kebenaran implementasi sekarang.

## Akar masalah (Evidence: Confirmed + Reproduced)

1. **Confirmed** — `2026_06_05_000001` hanya membuat `form_answers_active_user_form_unique` bila driver `sqlite`/`pgsql`; `2026_06_11_000001` sama untuk `form_answers_active_guest_email_form_unique`. MySQL tidak pernah mendapat keduanya (`SHOW INDEX FROM form_answers` di MySQL dev: tidak ada index `*_active_*`).
2. **Reproduced** — predikat index yang ada memakai
   `NOT (registration_role = 'member' AND member_confirmation_status IN ('rejected','expired'))`.
   Untuk baris dengan `registration_role` NULL (submission single), `registration_role = 'member'` → NULL, `NOT (NULL AND ...)` → NULL, dan SQLite/PostgreSQL hanya mengindeks baris yang ekspresinya TRUE. Test regresi yang menulis langsung ke tabel gagal di SQLite **sebelum** migrasi ini (2 dari 4 kasus), membuktikan tidak ada proteksi DB.
3. **Reproduced** — di MySQL (`dform_probe_09w`, database scratch) test yang sama gagal untuk dua kasus sebelum migrasi: submit aktif kedua untuk user+form yang sama dan guest dengan `invited_email` sama berhasil masuk.

`FormAccessGuard` dan pengecekan di dalam transaksi tetap ada, tetapi bersifat TOCTOU; `SELECT ... FOR UPDATE` pada baris yang belum ada tidak menjamin serialisasi, dan `submitOtherForm()` tidak mengunci baris event.

## Keputusan historis sebelum Opsi A

**Generated column + unique index untuk MySQL, index parsial NULL-safe untuk SQLite/PostgreSQL.**

- MySQL tidak punya partial index; unique index mengabaikan NULL, sehingga satu kolom generated yang bernilai NULL untuk baris non-aktif memberi semantik yang sama dengan index parsial. Kolom dibuat `VIRTUAL` agar penambahan tidak menyalin tabel dan index dibangun tanpa menyimpan kolom.
- Index parsial lama di SQLite/PostgreSQL dibuat ulang dengan predikat `COALESCE(...)` yang NULL-safe; tanpa ini perbaikan MySQL tidak menutup lubang yang sama di driver test.
- Alternatif yang ditolak: **enforcement service saja**. Itu tidak menutup race dua request bersamaan (kasus yang justru menjadi alasan tiket) dan tidak memberi jaminan di level data; aplikasi tetap menyimpan pengecekan untuk pesan UX.
- Migrasi **tidak menghapus data**. Duplikat terdeteksi dari kegagalan unique index (DDL atomic: tabel kembali ke keadaan semula) dan dipetakan menjadi `RuntimeException` yang menyebut duplikat aktif; pembersihan data produksi harus keputusan manusia. Preflight `GROUP BY` penuh dihapus setelah terukur memakan >5 menit pada 1 juta baris tanpa memberi jaminan tambahan.
- `down()` mengembalikan definisi index lama dan menghapus kolom MySQL, sehingga rollback setara keadaan sebelumnya.

## Perubahan historis sebelum Opsi A

- `database/migrations/2026_11_03_000001_enforce_active_form_answer_uniqueness.php` (baru): DDL per driver (`active_user_form_key`/`active_guest_email_form_key` + unique index di MySQL; recreate index parsial NULL-safe di SQLite/PostgreSQL), dengan pemetaan error duplikat.
- `tests/Feature/Forms/FormAnswerDuplicateConstraintTest.php` (baru): menulis langsung ke `form_answers` (melewati `FormAccessGuard`) dan membuktikan database menolak duplikat; juga membuktikan baris `rejected`/member `rejected`/`expired` tidak memblokir submission baru.

Tidak ada perubahan controller, route, atau perilaku UI: pesan "already submitted" tetap dari `FormAccessGuard`.

## Verifikasi historis sebelum Opsi A

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

## Migrasi pada dataset 1 juta baris (Measured)

Data dev `form_answers`: 1.000.932 baris, 435 MB data + 868 MB index.

- Versi pertama (satu `ALTER` gabungan dua kolom + dua index, algoritma default) teramati memakai `copy to tmp table` dan berjalan **>20 menit** dengan DML terblokir. MySQL menolak `ALGORITHM=INPLACE` untuk bentuk itu (`error 1846: INPLACE ADD or DROP of virtual columns cannot be combined with other ALTER TABLE actions`).
- Versi final memisahkan per kolom: `ADD COLUMN ... VIRTUAL, ALGORITHM=INSTANT`, lalu `ADD UNIQUE INDEX ..., ALGORITHM=INPLACE, LOCK=NONE`. Kolom kunci user memakai `CHARACTER SET ascii` (UUID heksadesimal) sehingga key turun dari 292 byte menjadi 73 byte per baris. Penambahan index berjalan online (tulis tidak diblokir).
- Hasil pada DB dev yang sama: migrasi selesai **<60 detik** sejak container di-restart; schema terverifikasi (`active_user_form_key varchar(73) VIRTUAL GENERATED`, dua unique index `non_unique=0`), aplikasi merespons 200.
- Preflight `GROUP BY` dihapus karena terukur >5 menit pada dataset ini; kegagalan unique index tetap dipetakan ke pesan duplikat yang jelas.

Angka ini dari container dev (buffer pool default 128 MB), bukan benchmark produksi; tetap menunjukkan bentuk DDL yang harus dipakai agar deploy tidak mengunci tulis selama puluhan menit.

## Batasan

- CI menjalankan test suite di SQLite `:memory:`; bukti MySQL berasal dari run manual terhadap database scratch di container dev, bukan dari pipeline. Batasan ini tidak bisa ditutup tanpa job CI MySQL (layanan tambahan + biaya waktu), di luar scope tiket.
- Index parsial SQLite/PostgreSQL membandingkan `invited_email` apa adanya (case-sensitive), sama seperti index lama. Controller sudah menormalkan email ke huruf kecil sebelum insert, jadi perbedaan ini tidak teramati pada alur aplikasi.

## Blocker terbuka: MariaDB 10.11 (prod) menolak index pada generated column

Diuji di container `mariadb:10.11.19` dengan tabel minimal:

```text
ALTER TABLE ... ADD COLUMN k VARCHAR(80) AS (CONCAT(a,b)) VIRTUAL   -> OK
ALTER TABLE ... ADD UNIQUE INDEX ix (k)                             -> ERROR 1901
  "Function or expression 'concat(`a`,`b`)' cannot be used in the GENERATED ALWAYS AS clause of `k`"
-- UPPER(a) juga 1901; hanya ekspresi kolom polos (AS (a)) yang bisa di-index.
-- PERSISTENT + CASE/CONCAT ditolak sejak ADD COLUMN.
```

Artinya jalur `driver === 'mysql'` (yang juga dipakai koneksi MariaDB) **akan gagal saat deploy prod** (`migrate --force` berhenti, container tidak boot). Perbaikan DDL online di atas hanya menyelesaikan masalah durasi/lock di MySQL 8; ia tidak membuat skema ini kompatibel dengan MariaDB.

Opsi yang diajukan (butuh keputusan sebelum perubahan berikutnya):

1. **Kolom biasa + unique index, diisi aplikasi** (`active_user_form_key`/`active_guest_email_form_key` nullable, dihitung di model `FormAnswer` saat create/update, backfill bertahap, index dibuat online). Portabel ke MySQL, MariaDB, SQLite, PostgreSQL; butuh audit semua jalur update `review_status`/`member_confirmation_status`/`invited_email`.
2. **Penanda nullable `active_submission` (1/NULL) + unique index komposit** `(user_id, form_id, active_submission)` dan `(form_id, invited_email, active_submission)`. Nullable menjaga baris rejected tidak saling bentrok; kolom tetap diisi aplikasi.
3. **Pertahankan generated column hanya untuk MySQL 8** dan pakai salah satu opsi di atas untuk MariaDB. Ditolak sementara: dua skema berbeda per driver menambah risiko.

Sampai keputusan diambil, jalur MariaDB tidak memiliki proteksi database; rekomendasi: jangan deploy migrasi ini ke prod sebelum opsi 1/2 dipilih.
