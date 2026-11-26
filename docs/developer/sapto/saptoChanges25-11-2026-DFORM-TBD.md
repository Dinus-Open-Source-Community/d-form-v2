# Sapto Changes — 25 November 2026 (DFORM-TBD)

## Ringkasan

Form pendaftaran yang sudah berjalan di produksi kini bisa memakai redeem code DU tanpa
mengubah `purpose` form atau menyentuh data pendaftar yang sudah ada. Integrasi dinyalakan
per field: flag `du_redeem_enabled` pada field custom `Class` yang sudah dipakai form
tersebut. Selama flag aktif, isi opsi field itu dibekukan (opsi read-only) karena jawaban
menyimpan nilai opsi apa adanya; label dan deskripsi field tetap boleh diedit. Setiap opsi
dipetakan eksplisit ke satu course DU lewat `du_option_map` dengan bentuk
`{ "<option value>": { course_uid, course_title } }`, dan pencocokan judul hanya
menghasilkan saran untuk konfirmasi admin, bukan issuance otomatis. Form D-Kelas baru tetap
memakai field sistem `du_course_uid` dan subset per form `selected_course_uids`. Jawaban
lama yang sudah accepted diterbitkan kode satu per satu lewat drawer jawaban; tidak ada UI
issuance massal.

| Issue                                                                              | Commit                                                                                                      |
| ---------------------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------- |
| TBD (rename this file and update the issue field when the Jira ticket is assigned) | pending (the working tree is mixed and uncommitted; the final commit is deferred until the Jira key is set) |

Klasifikasi: `feature`.

## Kenapa

- Form produksi yang dituju ber-purpose `registration`, punya field custom `Class`, dan sudah
  menerima pendaftar. Mengganti purpose ke `d_kelas` akan mengubah semantik pendaftaran
  (jendela registrasi, kuota `registered_count`, aturan satu form pendaftaran per event) dan
  memindahkan data yang sudah ada ke jalur berbeda.
- Endpoint katalog DU mengembalikan seluruh course, sedangkan form hanya butuh sebagian
  kelas. Menampilkan semuanya membuat peserta memilih kelas yang tidak relevan, jadi
  pemilihan course dilakukan per form.
- Lima kelas nyata di form (`AI Engineer`, `Data Science`, `Cyber Security`, `UI/UX`,
  `DevOps`) belum ada di DU, dan D-Form tidak bisa membuat course. Sampai DU membuatnya,
  opsi tersebut harus tampil `Belum terhubung` dan issuance ditolak, bukan menebak course.

## Alternatif yang ditolak

- **Mengubah purpose form ke `d_kelas`.** Paling sedikit kode baru, tetapi merusak semantik
  form pendaftaran yang sedang berjalan dan memaksa migrasi data jawaban lama. Dipilih: flag
  per field tanpa mengubah purpose.
- **Seleksi course global.** Lebih sederhana di UI, tetapi tiap form membutuhkan kelas yang
  berbeda, sehingga daftar global akan menampilkan kelas yang tidak dipakai form tersebut.
  Dipilih: pemetaan dan subset per form.
- **UI issuance massal.** Menghemat klik, tetapi menerbitkan kode untuk banyak jawaban
  sekaligus sulit diaudit dan berisiko saat satu jawaban gagal. Dipilih: issuance per jawaban
  di drawer.
- **Pemetaan lewat snapshot label.** Tidak diperlukan karena isi opsi dibekukan selama
  integrasi aktif; kunci pemetaan adalah nilai opsi yang juga tersimpan di jawaban.

## Perilaku dan arsitektur

### Flag dan pembekuan opsi

- Toggle `Redeem code DU` tampil di editor setiap field pilihan (`dropdown`/`radio`) dan
  menulis `form_fields.metadata.du_redeem_enabled = true`. Hanya satu field per form boleh
  membawa flag ini. Field yang memegang flag mengunci field lain: toggle di field lain
  nonaktif sampai flag di field pemilik dimatikan. Tidak ada auto-clear diam-diam; workspace
  menolak percobaan memilih field kedua dengan toast. Invariant ditegakkan di UI dan backend.
- Field tanpa opsi tetap menampilkan toggle, tetapi nonaktif dengan alasan tambahkan opsi
  dulu. Field yang sudah terlanjur memegang flag tetap bisa mematikannya.
- Selama flag aktif di field pemilik, editor opsi menjadi read-only: tambah, ubah, dan hapus
  opsi dinonaktifkan. Label dan deskripsi field tetap dapat diedit karena jawaban dikunci
  pada `name` internal field, bukan label.
- Untuk menambah kelas baru: matikan flag, ubah opsi, nyalakan lagi, lalu petakan opsi baru.
  Pemetaan dan jawaban lama tidak berubah.

### Pemetaan dan resolusi

- Pemetaan disimpan di `form_fields.metadata.du_option_map`, key = nilai opsi (immutable),
  nilai = `{ course_uid, course_title }`. Satu pemetaan berlaku untuk semua jawaban dengan
  opsi yang sama.
- Saran otomatis hanya untuk kecocokan judul (atau UID) yang persis dan tunggal. Saran tidak
  pernah menerbitkan kode; admin selalu mengonfirmasi lewat `Gunakan`.
- Pemetaan yang menunjuk course non-aktif tetap dapat menerbitkan kode, karena baris cache
  non-aktif masih diresolusi. Sebaliknya, opsi tanpa pemetaan gagal closed (tanpa menebak
  course) dan issuance ditolak.
- Status `unknown` dari DU tidak pernah di-retry otomatis; rekonsiliasi tetap manual.
- Issuance hanya untuk jawaban dengan `review_status = accepted`, memakai mekanisme claim,
  retry, penyimpanan respons, dan outbox yang sudah ada.

### Permukaan UI

- **Editor field pilihan:** kartu `Redeem code DU` dengan toggle, daftar opsi read-only satu
  baris per opsi, combobox course DU aktif yang bisa dicari, status `Terhubung` (outline
  hijau lembut seperti badge status lain) / `Belum terhubung` / `Terdeteksi: X` + `Gunakan`,
  dan aksi `Sync katalog DU`. Pemetaan disimpan lewat autosave field yang sudah ada. Field
  tanpa opsi menampilkan pesan untuk
  mematikan flag dan menambah opsi dulu.
- **Drawer jawaban:** section `Redeem code DU` menampilkan nilai kelas, status pemetaan,
  combobox `Pilih course DU` bila belum terpetakan, tombol `Sync katalog DU` untuk
  menyegarkan katalog, tombol `Terbitkan kode` bila sudah terpetakan dan jawaban accepted,
  lalu kode dan status penerbitan setelah terbit. Setelah terbit, tersedia dua aksi pemulihan:
  `Kirim ulang email` (kode sama, tanpa memanggil DU) dan `Terbitkan ulang` (kode baru lewat
  konfirmasi; bila DU menolak, kode lama dipertahankan dan pesannya ditampilkan). Status email
  dikirim lewat outbox dan tidak ditampilkan di drawer. Pemetaan disimpan lebih dulu, baru kode
  diterbitkan. Tidak ada aksi massal.
- **Tabel jawaban:** kolom `Kode DU` muncul otomatis saat form punya integrasi DU atau sudah
  ada issuance. Sel hanya menampilkan kode (monospace) saat terbit; status lain memakai badge
  `Gagal`/`Menunggu DU`/`Belum terbit`/`Belum terhubung` dengan warna berbeda. Kolom `Referral`
  hanya menampilkan kode, dengan fallback `Tanpa referral`. Mobile list menampilkan kode ringkas
  atau badge. Kolom DU tidak muncul untuk form tanpa DU.
- Lima opsi lama menampilkan `Belum terhubung` sampai DU membuat course-nya; setelah itu admin
  memetakan satu per satu (saran judul muncul untuk kecocokan persis).

## Data dan endpoint

- Tanpa migration dan tanpa tabel baru. State disimpan di metadata field:
  `form_fields.metadata.du_redeem_enabled` (boolean per field),
  `form_fields.metadata.du_option_map` (objek per opsi), dan
  `form_fields.metadata.selected_course_uids` (subset course per form, khusus field sistem
  `du_course_uid` pada form D-Kelas).
- `GET /admin/du/courses` — daftar course dari cache lokal untuk combobox.
- `POST /admin/du/courses/sync` — refresh cache katalog (throttle `du-sync`), tidak
  mengosongkan `selected_course_uids`.
- `PUT /admin/events/{event}/forms/{form}/du-field-map` — menyimpan pemetaan satu opsi ke satu
  course aktif pada cache (throttle `du-mapping`), 422 bila field tanpa flag, opsi bukan milik
  field, atau course tidak ada/non-aktif.
- `POST /admin/events/{event}/forms/{form}/submissions/{formAnswer}/du-issuance` — endpoint
  issuance yang sudah ada, dipakai drawer; `course_uid` opsional sebagai override koreksi.
  Field `action` (`issue` default, `reissue`, `resend`) menambah pemulihan setelah terbit:
  `resend` membuat outbox email baru dengan kode yang sama tanpa memanggil DU; `reissue`
  meminta kode baru, mempertahankan kode lama bila DU menolak/timeout, dan tidak pernah
  mengulang status `unknown` otomatis. Respons selalu menyertakan `created_new_code`.

## Verifikasi

| Pemeriksaan                                                                               | Hasil                                                                  |
| ----------------------------------------------------------------------------------------- | ---------------------------------------------------------------------- |
| `./vendor/bin/pint --test`                                                                | 987 files PASS                                                         |
| `php artisan test`                                                                        | 2218 passed, 0 failed, 16 skipped (14.176 assertions)                  |
| `npm run test:unit`                                                                       | 1382 passed (136 file)                                                 |
| `npm run typecheck`, `npm run lint`, `npm run test:e2e:types`                             | clean                                                                  |
| `npm run test:e2e -- du-course-create du-drawer-issue form-purpose-dialog create-p0-save` | 8 passed                                                               |
| `podman exec -i -w /app d_form_app npm run build`                                         | sukses 14,34 s; entry 16,24 kB gzip; page chunk terbesar 41,28 kB gzip |

- Seluruh perintah PHP dijalankan di container. 16 skip adalah test ber-gate MySQL (race dan
  claim) di environment test SQLite.
- Budget bundle lolos: entry 16,24 kB gzip ≤ 350 kB dan page chunk terbesar 41,28 kB gzip
  ≤ 200 kB.

## Batasan yang diketahui

- Lima kelas nyata belum ada di DU; sampai DU membuatnya, opsi tersebut tidak bisa dipetakan
  dan issuance untuk jawaban dengan opsi itu ditolak.
- Test race/claim MySQL di-skip di environment SQLite, sehingga perilaku konkurensi pada
  MySQL tetap `Needs verification` sampai dijalankan di database tersebut.
- Belum ada UI issuance massal; ini keputusan desain, bukan kekurangan implementasi.
- Saran judul bersifat exact-case: variasi kapitalisasi judul course tidak disarankan
  otomatis dan harus dipilih manual.
- Invariant satu flag per form ditegakkan di UI dan backend, bukan constraint database.
- Residual rename `FormController::update` dari tiket sebelumnya dan minor deferred lain yang
  sudah tercatat tetap di luar scope tiket ini.

## Runbook

- Tidak ada migration baru: deploy tidak memerlukan langkah database, backfill, atau rollback
  schema. Rollback cukup menonaktifkan flag di field `Class`; pemetaan dan jawaban lama tetap
  tersimpan dan tidak merusak form.
- Setelah DU membuat kelima course, jalankan `Sync kelas`, petakan tiap opsi, lalu terbitkan
  kode per jawaban lewat drawer.

## Revisi 26-11-2026: pemilihan course per opsi lewat dialog

- Dropdown `SearchableSelect` per opsi diganti tombol `Pilih`/`Ubah` yang membuka
  `DuOptionCoursePickerDialog`. Satu dialog berisi pencarian, daftar dibatasi
  `max-h-72` dengan scroll, jumlah hasil (`N dari M course`), penanda course
  terpilih, dan gulir otomatis ke pilihan tersimpan.
- Alasan: dengan katalog 100 course, satu dropdown per opsi membuat kartu panjang
  dan setiap dropdown merender daftar penuh. Dialog hanya dirender saat dibuka,
  sehingga kartu mapping tetap ringkas dan daftar tetap terkendali. Pencarian
  dilakukan di client karena 100 baris masih murah; server-side search baru
  diperlukan bila katalog tumbuh ke ribuan.
- Sinkronisasi tetap eksplisit lewat `Sync katalog DU`, bukan otomatis saat halaman
  dibuka. Sync menarik seluruh katalog aktif ke cache; yang dibatasi adalah cara
  memilihnya, bukan jumlah data yang disinkronkan.
- Bukti: `tests/e2e/du-field-mapping.desktop.spec.ts` passed (saran judul, pencarian
  dialog, dan persist setelah reload), `vue-tsc` dan ESLint bersih, Vitest penuh
  1448 passed.

### Pilihan kelas di halaman isi

- Field kelas DU (`du_course_uid` atau `options_source=du_catalog`) kini tampil
  sebagai daftar kartu centang, bukan dropdown. Kartu terpilih memakai border primary,
  latar `bg-primary/10`, dan ikon centang di kanan; hanya satu kartu yang bisa dipilih
  (radio tersembunyi), sesuai kontrak satu kelas per jawaban. UID course tidak
  ditampilkan.
- Dialog pemilih course di sisi admin (`DuCoursePickerDialog` dan
  `DuOptionCoursePickerDialog`) tidak lagi menampilkan UID di bawah judul.
- Bukti: `tests/e2e/du-class-checkcards.desktop.spec.ts` passed (kartu centang,
  tanpa dropdown/UID, pilihan tunggal), `vue-tsc`/ESLint bersih.

### Dialog "Pilih kelas DU"

- Tombol `Sync kelas` dipindah dari kartu `DuCourseSyncCard` ke dalam dialog
  `DuCoursePickerDialog`, di samping kolom pencarian. Status menyinkronkan, waktu
  sinkron terakhir, dan pesan error memakai state yang sama seperti sebelumnya.
- Tuning UI: ikon pencarian di dalam input, jumlah `N dari M kelas`, aksi
  `Tambah semua hasil` hanya muncul saat mencari, `Kosongkan` pindah ke footer
  bersama jumlah pilihan, dan kartu terpilih memakai border primary +
  `bg-primary/10`. Dialog pemilih course per opsi memakai pola pencarian dan kartu
  terpilih yang sama.
- Bukti: E2E `du-course-create`, `du-field-mapping`, dan `du-class-checkcards`
  passed; `vue-tsc`, ESLint, dan `test:e2e:types` bersih; build container 15.54 s.

### Form D-Kelas di halaman acara

- Portal `/joined/{slug}` kini memuat form ber-purpose `d_kelas` sebagai form
  pendaftaran, bukan hanya `registration`. Baris menampilkan label `D-Kelas`, dan
  aksi `Detail pendaftaran` ikut menempel pada submission D-Kelas.
- Daftar purpose pendaftaran dipusatkan di `FormPurpose::registrationValues()` dan
  dipakai portal, `FormAccessGuard`, serta halaman detail pendaftaran.
- Bukti: `UserEventRegistrationPortalTest` 30 passed (termasuk dua test baru untuk
  listing dan submission D-Kelas), probe browser `/joined/startup-pitch-night`
  menampilkan baris "test aja D-Kelas".

### Referral dan jadwal untuk D-Kelas dan pendaftaran

- Referral kini berlaku untuk form `registration` dan `d_kelas`. `Form::isReferralActive()`
  dan `ReferralCodeService::setEnabled()` memakai `FormPurpose::registrationValues()`,
  dan kartu referral di tab Pengaturan tampil untuk kedua purpose. Blok kode di halaman
  isi, endpoint cek, redemption, dan snapshot diskon mengikuti gate yang sama.
- Jadwal pertemuan kini tersedia untuk form pendaftaran, bukan hanya D-Kelas.
  `FormSessionController` menerima kedua purpose dan panel `Jadwal pertemuan` tampil
  di tab Pengaturan untuk keduanya.
- Scan QR: form pendaftaran yang memiliki sesi memakai sesi hari itu, sehingga satu QR
  dapat dipakai sekali per pertemuan pada event multi-hari. Form pendaftaran tanpa sesi
  tetap memakai satu absensi seperti sebelumnya. Form D-Kelas tetap wajib memiliki
  jadwal hari itu.
- `RecordAttendanceJob` hanya memakai email konfirmasi kelas untuk D-Kelas; sesi
  pendaftaran memakai email konfirmasi kehadiran biasa. Portal peserta menampilkan
  check-in terbaru.
- Bukti: `FormSessionControllerTest`, `ClassAttendanceScanTest`,
  `ReferralCodeManagementTest`, dan `ReferralRedemptionTest` passed (50 test pada
  filter gabungan), termasuk tiga skenario scan pendaftaran multi-hari dan redemption
  D-Kelas.

### Empty state tabel jawaban

- Tabel jawaban selalu dirender, termasuk saat belum ada jawaban. Empty state
  `Belum ada jawaban` ditempatkan di dalam `tbody` dengan `colspan` penuh agar header
  tabel tetap terlihat, dan versi mobile tetap muncul di bawah toolbar.
- Hasil filter yang kosong tetap memakai empty state filter/search dengan aksi
  `Hapus filter`.
- Bukti: E2E `form-answers-columns.desktop.spec.ts` passed (termasuk test baru tabel
  kosong dengan empty state di dalam tbody).

### Bukti suite penuh revisi 26-11-2026

- PHP full suite di container: 2288 passed, 16 skipped.
- Vitest penuh: 1448 passed, 142 file.
- `vue-tsc`, ESLint, dan `test:e2e:types`: bersih.
- Build container: 15.12 s; entry 16.32 kB gzip; chunk halaman terbesar 41.30 kB gzip.
- E2E: `form-schedule` 3 passed, `form-answers-columns` 2 passed,
  `du-course-create`/`du-field-mapping`/`du-class-checkcards` 4 passed,
  `form-price` 4 passed, `form-fill-section-header` 1 passed,
  `builder-section-rename` 1 passed.
- Root cause `form-answers-mobile` diperbaiki: fixture kini menyimpan berkas di
  `form-uploads/{formId}/sample.pdf` dan memakai path itu pada jawaban, sesuai
  kontrak `FormAnswerFileStorage`. Assertion href diarahkan ke URL
  `/form-answers/.../files/...` yang dihasilkan server, bukan `/storage/`.
  Seluruh 8 test spec tersebut passed.

### Toggle jadwal pertemuan

- Panel `Jadwal pertemuan` selalu tampil untuk form pendaftaran dan D-Kelas dengan
  `mt-6` yang sama seperti kartu referral di bawahnya.
- Header panel mengikuti pola kartu referral: judul dan penjelasan di kiri, kontrol di
  kanan. Form pendaftaran memakai switch `Check-in per pertemuan` yang selalu bisa
  diubah; saat mati, kontrol tanggal disembunyikan dan scan memakai satu check-in.
  Pilihan disimpan di `forms.metadata.meeting_schedule_enabled` dan dipakai
  `GlobalScanController`.
- Input tanggal memakai `DatePicker` bersama (`@/components/ui/date-picker`) dengan
  popover kalender, bukan input tanggal native browser, agar sama dengan form acara.
  Tanggal pada daftar memakai `formatDateTime` (`27 November 2026`), bukan ISO.
- Copy: pendaftaran `Aktifkan check-in per pertemuan bila acara memiliki lebih dari
  satu pertemuan.`; D-Kelas `Tentukan tanggal pertemuan tempat peserta check-in.`
  Tidak ada lagi gate panjang acara yang membuat switch tidak bisa dicentang.
- Bukti: E2E `form-schedule` 3 passed (D-Kelas tambah/duplikat/hapus, toggle
  pendaftaran mati-hidup plus persist setelah simpan); `ClassAttendanceScanTest`
  8 passed.

### CI hardening dan test jawaban

- `composer audit` gagal karena advisory Symfony 25-11 (`http-kernel`, `mailer`,
  `routing`). Ketiganya dinaikkan ke `7.4.24`, `composer.lock` ikut diperbarui, dan
  audit kembali bersih.
- `typecheck:unit` menutup fixture metadata yang belum punya `referral_position` dan
  `meeting_schedule_enabled`, serta mock cek referral yang belum membawa `price`.
- Prettier merapikan 15 file yang tertinggal dari aturan format CI.
- `form-answers-bulk-review`:
  - dipindah ke `*.desktop.spec.ts` karena seluruh assertion-nya membaca tabel desktop;
  - fixture otonom kini menerima `fixtureOwnerEmail`, sehingga sesi admin proyek tidak
    lagi mendapat 403 saat membuka event milik user marker;
  - assertion status di-scope ke tabel desktop karena daftar mobile dan live region
    `sr-only` juga ada di DOM;
  - select-all hanya memilih jawaban yang masih bisa direview, jadi skenario campuran
    memilih baris `Diterima` secara manual;
  - reload setelah aksi baris ditunggu sebelum klik baris berikutnya agar tidak
    mengirim review ke baris lama (409).
- `form-price` mereset cache sebelum setiap test karena kuota `form-referral-check`
  (60/menit per IP) tersimpan di cache; run berulang sebelumnya menghasilkan 429 dan
  UI menampilkan gagal cek.
