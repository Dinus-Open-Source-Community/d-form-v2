# Sapto Changes — 24 November 2026 (DFORM-TBD)

## Ringkasan

Form D-Kelas sekarang benar sejak halaman create dimuat: URL
`?purpose=d_kelas` menghasilkan draft ber-purpose `d_kelas` dengan satu field
wajib `Kelas`, bukan form kosong dengan tujuan `Pendaftaran`. Dropdown kelas
dibaca dari tabel cache `du_courses` di database D-Form, bukan dari panggilan
HTTP DU pada setiap render/fill/issuance, dan admin punya satu aksi eksplisit
`Sync kelas`. Respons redeem DU disimpan lengkap (kolom esensial + payload
mentah) sehingga detail kode tidak hilang setelah issuance.

| Issue                                                                              | Commit                                                                                                  |
| ---------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------- |
| TBD (rename this file and update the issue field when the Jira ticket is assigned) | pending (the working tree also contains the uncommitted referral feature; the final commit is deferred) |

## Kenapa

Dua bug yang dilaporkan user:

1. Halaman create D-Kelas menampilkan bagian field kosong, karena field sistem
   baru dibuat di `store()`/`show()`, bukan saat draft dibuat.
2. `Tujuan form` terbaca `Pendaftaran` pada URL `?purpose=d_kelas`, karena
   `storeDraft()` menulis `metadata: null` dan halaman create mem-hidrasi draft
   itu menimpa prefill dari query string.

Selain itu implementasi DU yang berjalan memanggil katalog DU lewat HTTP pada
setiap render halaman fill dan setiap pemeriksaan issuance, sehingga halaman
gagal saat DU tidak tersedia dan biaya request naik mengikuti traffic.

## Alternatif yang ditolak

- **Satu tabel `du` campuran untuk katalog dan baris redeem.** Tabel lebih
  sedikit, tetapi mencampur dua siklus hidup, memaksa kolom nullable, melemahkan
  constraint, dan menuntut penulisan ulang model serta UI issuance yang sudah
  ada. Dipilih: tabel cache terpisah + migration additive pada tabel issuance.
- **Tetap memakai live fetch dan hanya memperbaiki UI.** Diff paling kecil,
  tetapi melanggar kebutuhan "D-Form tidak memanggil DU terus-menerus" dan
  halaman fill tetap gagal saat DU tidak tersedia.

## Perilaku dan arsitektur

### Skema

- Tabel baru `du_courses` sebagai cache katalog: `id` UUID primary key,
  `uid` string unique, `title` string, `is_active` boolean default `true`,
  `last_synced_at` timestamp nullable, `timestamps`.
- `du_redeem_issuances` ditambah kolom respons nullable: `du_uid` (index
  non-unique), `course_title`, `target_email`, `max_uses`, `used_count`,
  `is_active`, `expires_at`, `du_created_at`, `response_payload` (json).
  `du_uid` sengaja non-unique: duplikat UID dari upstream tidak boleh melempar
  error setelah DU sudah menerbitkan kode.
- Cache ditulis hanya oleh `POST /admin/du/courses/sync` (middleware `auth`,
  `organizer`, throttle `du-sync` 6/menit): upsert per `uid`, set
  `is_active = true` dan `last_synced_at`, lalu menandai baris yang hilang dari
  respons sebagai `is_active = false` (tidak dihapus) agar jawaban lama tetap
  dapat diresolusi. `syncIfEmpty()` melakukan satu percobaan otomatis hanya saat
  cache tidak punya baris aktif.

### Metadata field sistem

`DuCourseFieldService::ensure()` menjamin satu field `du_course_uid`:
`input_type = selectInput`, label `Kelas`, metadata
`{ system: true, locked: true, options_source: "du_catalog", duplicatable: true, rules: { required: true } }`,
`order = 0`. Dua mekanisme berbeda menangani peserta tambahan:

- **Bundle** memakai `metadata.duplicatable`: backend selalu meminta
  `bundle__du_course_uid__{i}` untuk setiap slot, jadi UI fill menggandakan
  field ini per peserta.
- **Team** memakai `is_append = true` (hanya untuk `registration_mode = team`):
  anggota yang diundang melihat dropdown `Kelas` di halaman undangan, terisi
  default kelas ketua, dan memilih kelasnya sendiri sebelum konfirmasi; pilihan
  itu divalidasi terhadap katalog aktif di boundary undangan. Field sistem tidak
  membawa `rules.in`, jadi aturan validasi `in` dipasang di controller undangan.
- **Single** tetap satu field dengan `is_append = false`.

Snapshot autosave field juga menegakkan identitas sistem (nama, tipe, metadata,
dan `is_append` sesuai mode) dan hanya mempertahankan label/description yang
diedit admin. Pada field yang sudah ada, service me-restore baris yang
ter-trashed dan memperbaiki tipe/metadata/`is_append` tanpa menimpa label dan
description yang diedit admin, dan hanya menulis bila ada yang berubah. Daftar
opsi tidak pernah disimpan di metadata; opsi selalu dibaca dari `du_courses` saat
payload dibentuk, sehingga satu sumber kebenaran dan tidak ada drift per form.

### Draft per purpose

`storeDraft()` menerima `purpose`/`mode`, menormalkan metadata lewat
`FormPurposeRules::apply()`, dan mencari draft dengan event + owner + purpose
yang sama (`metadata = null` diperlakukan sebagai `registration` untuk draft
lama). Draft purpose lain tidak pernah dipakai ulang. Untuk `d_kelas`, draft
pertama menjalankan `syncIfEmpty()` lalu `ensure()`; kegagalan sync tidak
menggagalkan create, field tetap dibuat tanpa opsi dan builder menyediakan
tombol coba lagi. Halaman create mengirim `initialPurpose`/`initialMode` ke
endpoint draft sehingga hidrasi pertama sudah membawa metadata yang benar.

### Resolusi title/UID dan aturan fail-closed

Select field mengirim label opsi, dan setiap field form lain juga menyimpan
label di `answers`, jadi D-Kelas mengikuti konvensi itu: jawaban menyimpan
**title** kelas. Di boundary submission, `DuCourseResolver::activeUidFor()`
menerima tepat satu kecocokan aktif (UID atau title); nol atau lebih dari satu
kecocokan menghasilkan `422` untuk `du_course_uid`. Saat issuance,
`uidForIssuance()` mencocokkan UID lebih dulu lalu title (baris non-aktif
diizinkan agar jawaban lama tetap dapat diterbitkan), dan gagal closed tanpa
menebak bila tidak tunggal. Override `course_uid` oleh admin tetap menjadi
jalur koreksi. UID hasil resolusi disimpan di baris issuance bersama
`course_title` dan dikirim ke DU; jawaban historis tidak ditulis ulang.

### Kolom respons redeem

`DuRedeemClient` memetakan seluruh `data` dari respons `201` lewat
`DuRedeemData::fromArray()`: field wajib (`uid`, `code`, `course_uid`) harus
string non-kosong, field lain boleh `null`, dan tipe yang salah membuat hasil
diklasifikasikan `unknown` (bukan nilai karangan). Pada `issued`,
`DuRedeemIssuanceService` menyimpan `redeem_code`, `du_uid`, `course_uid`,
`course_title`, `target_email`, `max_uses`, `used_count`, `is_active`,
`expires_at`, `du_created_at`, dan `response_payload`. Payload dan API key tidak
pernah dilog. Baris issuance lama dengan kolom respons `null` tetap terbaca
(email, detail peserta, dan status admin tidak crash).

### Outbox dan issuance manual

Endpoint issuance manual (`POST .../du-issuance`) memakai throttle
`du-issuance` (default 10/menit per user/IP dan per `formAnswer`) dan sekarang
menulis intent outbox `du-issued` seperti jalur review. Retry atas issuance yang
sudah `issued` me-replay hasil tersimpan, sehingga intent email yang hilang
dapat dipulihkan tanpa menerbitkan kode baru.

Pemulihan issuance yang `failed`/`unknown`/hilang untuk tiket ini bersifat
**API-only**: `FormAnswerReviewService` menolak menerima ulang jawaban yang sudah
direview, jadi admin memakai endpoint terautentikasi
`POST /admin/events/{event}/forms/{form}/submissions/{formAnswer}/du-issuance`
(dengan `course_uid` opsional sebagai override koreksi) sampai kontrol admin
dibuat. Field `du_issuance` pada respons review controller sengaja
API-facing, bukan konsumsi UI admin, dan tidak dihapus. Kontrol admin menjadi
follow-up tiket terpisah.

### Semantik pendaftaran D-Kelas

Form D-Kelas diperlakukan sebagai form pendaftaran untuk jendela
`registration_start`/`registration_end`, kuota `events.registered_count`
(submission D-Kelas menaikkan hitungan itu), dan aturan satu form pendaftaran
per event — `event_form_already_chosen` memblokir user yang sudah submit form
Pendaftaran saat membuka form D-Kelas, dan sebaliknya (dua arah), kecuali
submission sebelumnya ditolak (`excludeRejectedSubmissions()`, lihat
`FormAccessGuard.php:179`): user yang submission-nya ditolak tetap boleh memilih
form pendaftaran lain.

Detail pendaftaran peserta (`/joined/.../registration`) menampilkan D-Kelas,
tetapi picker form di `/joined` saat ini hanya mendaftar purpose
`registration`/null dan `other`, jadi D-Kelas belum muncul di sana; itu follow-up
tiket terpisah, bukan perilaku saat ini.

Konsekuensi yang diterima: event yang memelihara form Pendaftaran **dan** form
D-Kelas tidak bisa didaftari dua kali oleh user yang sama. Perilaku ini
disengaja untuk tiket ini dan dipin oleh test akses.

## Migration dan rollback

- `2026_11_24_000001_create_du_courses_table` — additive; `down()` drop tabel.
  Cache dibangun ulang lewat sync endpoint, tidak ada data operasional hilang.
- `2026_11_24_000002_add_du_response_columns_to_du_redeem_issuances_table` —
  additive, seluruh kolom nullable; `down()` hanya drop kolom tersebut dan
  tidak menghapus baris issuance.
- Tidak ada migration destructive dan tidak ada backfill data. Rollback tidak
  menghapus kode redeem: `du_redeem_issuances.redeem_code` dan kode yang sudah
  diterbitkan tetap valid; yang hilang hanya detail respons dan payload mentah.
- Urutan rollback: deploy kode yang tidak lagi membaca kolom respons lebih
  dulu, baru jalankan `down()`. Klasifikasi tercatat di
  `docs/runbook/database-migrations.md` §6 (`reversible` dan
  `data_loss_on_rollback`).

## Verifikasi

Seluruh perintah PHP dijalankan di container
(`podman exec -i -w /app d_form_app ...`). Angka diambil dari report per task;
perintah di bawah adalah perintah targeted untuk task tersebut (beberapa report
menjalankan cakupan lebih luas, mis. regresi Forms).

| Task | Perintah                                                                                                                                                                                                                                                                                                                                                         | Hasil                                                                   |
| ---- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ----------------------------------------------------------------------- |
| 1    | `php artisan test tests/Feature/Forms/DuCourseCacheTest.php tests/Feature/Migrations/DuRedeemIssuanceSchemaTest.php`                                                                                                                                                                                                                                             | 10 passed / 83 assertions                                               |
| 2    | `php artisan test tests/Feature/Forms/DuCourseSyncEndpointTest.php`                                                                                                                                                                                                                                                                                              | 12 passed / 44 assertions                                               |
| 3    | `php artisan test tests/Feature/Forms/DuCourseFieldTest.php tests/Feature/Forms/DuCourseCatalogTest.php tests/Feature/Forms/DuCourseSubmissionTest.php`                                                                                                                                                                                                          | 18 passed / 51 assertions                                               |
| 4    | `php artisan test tests/Feature/Forms/DuCourseResolverTest.php tests/Feature/Forms/DuCourseSubmissionTest.php tests/Feature/Forms/DuRedeemIssuanceTest.php tests/Feature/Forms/DuRedeemIssuanceConcurrencyTest.php tests/Feature/Forms/DuRedeemThrottleTest.php tests/Feature/Forms/DuRedeemIssuedOutboxTest.php tests/Feature/Jobs/DuRedeemIssuedEmailTest.php` | 42 passed, 3 skipped (terkait); MySQL race 3/3                          |
| 5    | `php artisan test tests/Unit/Services/Du/DuRedeemDataTest.php tests/Feature/Forms/DuRedeemIssuanceTest.php tests/Feature/Forms/DuRedeemIssuedOutboxTest.php tests/Feature/Jobs/DuRedeemIssuedEmailTest.php`                                                                                                                                                      | 25 passed / 69 assertions                                               |
| 6    | `php artisan test tests/Feature/Forms/DuCourseDraftTest.php` + `npm run test:unit -- resources/js/adapters/http/__tests__/forms.test.ts`                                                                                                                                                                                                                         | 42 passed / 318 assertions                                              |
| 7    | `php artisan test tests/Feature/Forms/DuCourseFieldTest.php` + `npm run test:unit -- resources/js/hooks/__tests__/useDuCourseSync.test.ts resources/js/components/modules/builder/__tests__/fieldMapping.test.ts`                                                                                                                                                | 12 PHP passed / 58 assertions + 27 frontend tests                       |
| 8    | `php artisan test tests/Feature/E2e/DuCourseCommandTest.php`; `npm run test:e2e -- tests/e2e/du-course-create.desktop.spec.ts`; regresi `tests/e2e/form-purpose-dialog.desktop.spec.ts tests/e2e/create-p0-save.desktop.spec.ts`                                                                                                                                 | command 6 passed / 21 assertions; target E2E 2 passed; regresi 6 passed |

## Validasi penuh

Dijalankan setelah seluruh task selesai:

| Pemeriksaan                                                                                                                      | Hasil                                                 |
| -------------------------------------------------------------------------------------------------------------------------------- | ----------------------------------------------------- |
| `./vendor/bin/pint --test`                                                                                                       | 977 files PASS (PSR-12)                               |
| `php artisan test`                                                                                                               | 2155 passed, 16 skipped, 0 failed (13.670 assertions) |
| `npm run typecheck`, `npm run lint`, `npm run test:e2e:types`                                                                    | clean                                                 |
| `npm run test:unit`                                                                                                              | 1307/1307 passed (129 file)                           |
| E2E target `tests/e2e/du-course-create.desktop.spec.ts`, `form-purpose-dialog.desktop.spec.ts`, `create-p0-save.desktop.spec.ts` | 7 passed (28,4 s)                                     |
| `podman exec -i -w /app d_form_app npm run build`                                                                                | sukses 15,8 s, 4110 module                            |

Catatan angka:

- 16 skip adalah test ber-gate MySQL di environment test SQLite. Kegagalan
  `EXPLAIN MySQL recruitment` yang sebelumnya dikenal tidak muncul di
  environment ini dan tetap `Needs verification` di MySQL.
- Vitest run pertama 1306/1307: satu kegagalan snapshot `SURVIVING_ROUTE_KEYS`
  untuk `admin.duCourses.sync` (disebabkan fitur ini); snapshot diperbarui lalu
  1307/1307.

### Ukuran bundle (build container)

| Bagian                                                               | Raw       | Gzip      |
| -------------------------------------------------------------------- | --------- | --------- |
| Entry `app-*.js`                                                     | 51,26 kB  | 16,22 kB  |
| `vendor-inertia` (shared)                                            | 115,07 kB | 35,58 kB  |
| `vendor-vue` (shared)                                                | 94,03 kB  | 36,99 kB  |
| `index-*.js` (Tiptap vendor, lazy)                                   | 462,60 kB | 145,66 kB |
| html5-qrcode                                                         | 334,65 kB | 99,40 kB  |
| vue3-lottie                                                          | 314,47 kB | 80,99 kB  |
| `pages/Dashboard/Recruitment/Periods/Show.vue` (page chunk terbesar) | 178,00 kB | 43,61 kB  |

- Budget lolos: entry 16,22 kB gzip ≤ 350 kB, page chunk terbesar 43,61 kB gzip
  ≤ 200 kB.
- Halaman DU: Create 4,54 kB, Fill 9,07 kB, Show 27,07 kB gzip.
- `npm run build` di host gagal hanya karena PHP host tidak tersedia
  (`php: command not found`); build wajib dijalankan di container.

## Batasan yang diketahui

- `du_courses.title` tidak ber-index. Resolusi title memindai baris cache
  (kecil dan hanya berisi katalog aktif); bila katalog tumbuh besar, tambahkan
  index pada `title`.
- Retry atas issuance yang sudah `issued` self-heal untuk outbox: hasil
  tersimpan di-replay dan intent `du-issued` ditulis ulang, tanpa memanggil DU.
- Status `unknown` tidak di-retry otomatis. Rekonsiliasi `unknown` membutuhkan
  aturan DU dan sengaja di luar scope.
- Belum ada kontrol admin untuk retry issuance; pemulihan `failed`/`unknown`
  masih lewat endpoint API `POST .../du-issuance` (lihat catatan follow-up di
  bagian outbox dan issuance manual).
- `mode` bukan scope key draft. Satu draft per event + owner + purpose, jadi
  create `single`/`team`/`bundle` untuk purpose yang sama akan melanjutkan draft
  yang sama.
- Fitur referral berada di working tree yang sama tetapi di luar scope tiket ini
  dan belum di-commit.

## Catatan pre-existing dan environment

- `npx prettier --check docs/runbook/database-migrations.md` sudah gagal di HEAD
  (baris tabel belum di-pad), jadi runbook dibiarkan dengan diff minimal dan
  tidak diformat ulang 45 baris lain.
- `docs/superpowers/` masuk `.gitignore`, sehingga perubahan status spec hanya
  ada di working tree.
- Fitur referral masih belum di-commit dan di luar scope tiket ini. Commit final
  ditunda sampai ID Jira ditetapkan dan working tree campuran dipisahkan.
