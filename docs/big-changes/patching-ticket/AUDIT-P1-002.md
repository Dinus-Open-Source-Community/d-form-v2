# AUDIT-P1-002 — Upload form pada public disk

| Field | Nilai |
| --- | --- |
| Klasifikasi | security (confidentiality / access control) |
| Prioritas | P1 |
| Evidence | Confirmed (source) → Reproduced (regression test) → Measured (suite + E2E) |
| Status | In Review |
| Baseline | `dev-zapp` setelah `464fed1` |
| Commit kode + test | `6e0d262` — `security: move form uploads to private disk with authorized access` |
| Sumber tiket | `docs/big-changes/patching.md` §`AUDIT-P1-002`; `2026-10-30-agents-compliance-audit.md` §`AUDIT-P1-002` |

## Ringkasan

Upload jawaban form dan lampiran tim sekarang disimpan pada disk privat
(`local`) di `form-uploads/{formId}`. Tidak ada lagi URL publik yang dapat
dibuka hanya dengan mengetahui path. Akses melewati satu route
`/form-answers/{formAnswer}/files/{field}/{filename}` yang memeriksa signature
sementara atau user yang berwenang (pemilik submission, organizer event, atau
ketua tim untuk anggota satu grup).

File lama yang masih berada di disk `public` tetap dapat dibaca lewat route yang
sama sebagai fallback legacy. Perpindahannya disediakan sebagai command
`forms:move-uploads-to-private` dengan mode dry-run default; command tidak
dijalankan otomatis dan tidak menghapus file tanpa `--write`.

Tidak ada perubahan schema database. Nilai `form_answers.answers` tetap menyimpan
path relatif, sehingga data lama dan kontrak database tidak berubah.

## Akar masalah (sebelum)

1. **Dua jalur upload menulis ke disk publik.**
   `FormSubmissionController::extractFieldAnswer()` dan
   `TeamInvitationController::update()` memakai
   `UploadStorageTarget::public("form-uploads/{$form->id}")`.
2. **Ringkasan membuat URL publik.**
   `RegistrationAnswersSummarizer` membangun URL dengan
   `Storage::disk('public')->url($path)`.
3. **Disk `public` memang dapat dibaca tanpa otorisasi.**
   `config/filesystems.php` menetapkan `visibility => public` dan symlink
   `public/storage`; siapa pun yang mengetahui path dapat membaca file.

Dampaknya adalah possession of URL cukup untuk membaca dokumen peserta (CV,
identitas, bukti pembayaran, dan sejenisnya).

## Keputusan desain

| Keputusan | Alasan / alternatif yang ditolak |
| --- | --- |
| Simpan di disk `local` privat | Menutup akses berbasis path. Alternatif "tetap public + nama acak" ditolak karena URL yang bocor tetap permanen. |
| Satu route dengan dua jalur otorisasi: signed URL **atau** user berwenang | Halaman admin/portal memakai route relatif tanpa kedaluwarsa; email/CSV memakai signed URL karena penerima bundle guest tidak punya akun. Alternatif auth-only membuat link email guest tidak dapat dibuka; alternatif signed-only membuat halaman admin bergantung pada TTL. |
| Signed URL TTL 7 hari (`FORMS_UPLOAD_URL_TTL_MINUTES`) | Cukup untuk email yang dibuka belakangan tanpa memberi akses permanen. Nilai dapat diubah lewat config. |
| Validasi field harus `fileUpload` + path harus di bawah `form-uploads/{formId}/` | Mencegah field teks berisi path karangan dipakai untuk membaca file peserta lain di direktori form yang sama. |
| Policy: owner, organizer yang boleh `view` event, atau leader grup yang sama | Mengikuti pola `RecruitmentApplicationPolicy::downloadDocument`; tidak memberi akses ke member lain atau user login sembarang. |
| Tetap memakai `SafeUploadStorage` | Validasi MIME/struktur dan re-encode gambar yang sudah ada tidak diduplikasi; perubahan hanya pada target disk. |
| Fallback baca disk `public` + command migrasi dry-run | File lama tidak langsung hilang dan tidak dipindahkan otomatis. Command menyalin ke `local` lebih dulu, baru menghapus salinan `public` setelah salinan privat terbukti ada. |
| Rate limit `form-answer-file` 120/menit per user/IP | Signed URL yang bocor tetap dibatasi; angka di config, bukan hardcode tersebar. |
| Tanpa perubahan schema / format `answers` | Menghindari migrasi data dan menjaga kompatibilitas; path relatif tetap dipakai, URL dibentuk saat render. |

## Perubahan

| File | Perubahan |
| --- | --- |
| `app/Services/Registration/FormAnswerFileStorage.php` | Service baru: simpan upload ke `local`, resolve disk/path (local lalu legacy public), bentuk relative URL dan temporary signed URL, map answers ke URL, validasi prefix + tipe field. |
| `app/Http/Controllers/Dashboard/Events/Forms/FormAnswerFileController.php` | Controller download/preview: signature valid **atau** `viewFile` policy; `404` bila field/path tidak valid; `inline` default, `?download=1` untuk attachment; header `X-Content-Type-Options: nosniff`. |
| `app/Policies/FormAnswerPolicy.php` | Method `viewFile()` untuk owner, organizer event, dan leader grup. |
| `routes/web/admin/event.php` | Route `dashboard.form-answers.files` di luar group auth-only agar signed URL guest tetap resolve; throttle `form-answer-file`. |
| `app/Providers/AppServiceProvider.php` | Named limiter `form-answer-file`. |
| `config/events.php` | `upload_temporary_url_ttl_minutes` dan `file_download_rate_limit_per_minute`. |
| `app/Http/Controllers/Dashboard/Events/Forms/FormSubmissionController.php` | Upload jawaban memakai `FormAnswerFileStorage::store()` (disk privat). |
| `app/Http/Controllers/Dashboard/User/TeamInvitationController.php` | Upload lampiran tim memakai service yang sama; render `answers` memakai URL berotorisasi. |
| `app/Http/Controllers/Dashboard/Events/Forms/FormController.php` | Prop `submissions[].answers` memakai URL berotorisasi untuk field file. |
| `app/Services/Registration/RegistrationAnswersSummarizer.php` | Portal memakai relative URL; email/CSV memakai temporary signed URL. |
| `app/Console/Commands/MoveFormUploadsToPrivateCommand.php` | Migrasi sekali jalan file legacy `public` → `local`; dry-run default, `--write` untuk eksekusi, `--form=` untuk scope satu form. |
| `tests/Feature/Forms/FormUploadPrivacyTest.php` | 11 regression test akses (guest, user lain, owner, organizer, leader, signed, tampered, expired, field non-file, unknown field, legacy public). |
| `tests/Feature/Console/MoveFormUploadsToPrivateCommandTest.php` | 2 test command: dry-run tidak memindahkan; `--write` memindahkan dan mempertahankan path answers. |
| `tests/Feature/Forms/FormRegistrationTest.php` | Assertion upload diarahkan ke disk `local`, bukan `public`. |
| `tests/Unit/Services/RegistrationAnswersSummarizerTest.php` | Assertion URL signed/relative menggantikan URL `/storage/`. |
| `tests/e2e/form-upload-privacy.desktop.spec.ts` | 2 E2E browser: happy path unggah + akses, dan anomali validasi. |

## Alur akses berkas

```text
Upload (FormSubmissionController / TeamInvitationController)
  -> FormAnswerFileStorage::store()
  -> SafeUploadStorage (validasi + re-encode)
  -> disk local: form-uploads/{formId}/{random}.{ext}
  -> form_answers.answers[field] = path relatif

Render admin/portal
  -> FormAnswerFileStorage::answerUrls() / relativeUrl()
  -> /form-answers/{answerId}/files/{field}/{filename}

Email / CSV
  -> FormAnswerFileStorage::temporaryUrl()
  -> /form-answers/{answerId}/files/{field}/{filename}?expires=...&signature=...

Download
  -> FormAnswerFileController
  -> valid signature OR FormAnswerPolicy::viewFile
  -> locate(): field harus fileUpload + path harus form-uploads/{formId}/
  -> disk local (fallback public untuk file legacy)
  -> inline (default) / attachment (?download=1) + nosniff
```

## Perilaku sebelum vs sesudah

| Skenario | Sebelum | Sesudah |
| --- | --- | --- |
| Upload baru | Tersimpan di `storage/app/public/form-uploads/...` | Tersimpan di `storage/app/private/form-uploads/...` |
| Guest yang mengetahui URL file | Dapat membaca file | `403` tanpa signature valid |
| User login yang tidak berwenang | Dapat membaca file | `403` |
| Pemilik submission | Dapat membaca file | `200` lewat route berotorisasi |
| Organizer event | Dapat membaca file | `200` lewat policy `view` event |
| Ketua tim melihat lampiran anggota | Dapat membaca file | `200` lewat aturan leader grup |
| Link file di email/CSV | URL publik permanen | Signed URL sementara (default 7 hari) |
| File legacy di disk public | URL publik permanen | Tetap terbaca lewat route berotorisasi; dapat dipindahkan dengan command |
| Field teks berisi path file | Path dapat dipakai untuk membuka file | `404`; field harus terdaftar sebagai `fileUpload` |
| Direct `/storage/form-uploads/...` | `200` | `403/404`; setelah `--write`, file tidak ada lagi di public |

## Verifikasi

### Backend

| Pemeriksaan | Perintah | Hasil |
| --- | --- | --- |
| Regression test tiket | `php artisan test --filter='FormUploadPrivacyTest\|RegistrationAnswersSummarizerTest\|MoveFormUploadsToPrivateCommandTest\|test_file_upload_field_stores_path\|test_file_upload_rejects_non_matching_mime'` | **18 passed (45 assertions)** |
| Full suite | `php artisan test` | **909 passed, 7 skipped (5.749 assertions)** pada run final |
| Flake tercatat | run full suite sebelumnya | 1 failure `RecruitmentEmailResendTest` (area recruitment, bukan file yang diubah); test yang sama lulus isolasi (10 passed) dan full suite run berikutnya 909 passed |
| Pint | `./vendor/bin/pint --test` pada 15 file berubah | **PASS** |
| Route registry | `php artisan route:list --json` | route `dashboard.form-answers.files` terdaftar; tidak ada nama duplikat baru |
| MySQL migration | tidak ada migration/schema change | tidak berlaku |

### E2E browser

Perintah:

```bash
E2E_BASE_URL=http://localhost:3000 \
E2E_ADMIN_EMAIL=stress-user-1@stress.test \
E2E_ADMIN_PASSWORD=password \
npx playwright test form-upload-privacy --project=chromium-desktop --no-deps --retries=0
```

Hasil: **2 passed** (desktop, ±25 detik).

- **Happy path:** login admin, buka form fixture, unggah `cv.pdf`, submit.
  Assertion database: path berawalan `form-uploads/{formId}/`, ada di disk
  `local`, **tidak** ada di disk `public`. Halaman form `?tab=jawaban` dibuka,
  drawer detail menampilkan iframe PDF dengan `src` route
  `/form-answers/{answerId}/files/cv/...`; `fetch` dari sesi admin → `200`;
  request anonim → `403`; direct `/storage/{path}` → `403/404`.
- **Anomali:** file `.exe` ditolak preflight client dengan pesan
  “Format file tidak didukung.”; PDF dengan `/JavaScript` ditolak validasi
  server; keduanya tidak membuat baris `form_answers` (count `0`).
- Fixture dibersihkan di `finally`; verifikasi akhir `forms=0`, `events=0`,
  `fields=0`.
- Catatan environment: folder runtime `storage/app/e2e` diberi akses tulis
  (`a+rwX`) agar heartbeat Playwright dapat dibuat. Ini bukan perubahan
  aplikasi, sama seperti catatan pada `AUDIT-P1-007`.

### Frontend / format

| Pemeriksaan | Hasil |
| --- | --- |
| `npm run typecheck` (`vue-tsc --noEmit`) | **exit 0** |
| `npm run lint` (ESLint) | **exit 0** |
| Vitest / frontend unit test | belum tersedia di repo (FE-AUDIT-002 masih Open); cakupan frontend lewat typecheck + ESLint + E2E |
| `npx tsc -p tsconfig.e2e.json --noEmit` | **exit 0** |
| `npx prettier --check tests/e2e/form-upload-privacy.desktop.spec.ts` | **PASS** |
| `npm run build` | tidak dijalankan: tidak ada perubahan kode produksi frontend/bundle, hanya spec E2E |

## Acceptance criteria

- [x] File disimpan di disk privat; tidak ada lagi `UploadStorageTarget::public`
      pada jalur upload jawaban form/lampiran tim.
- [x] Akses melalui controller yang mengotorisasi (`FormAnswerFileController` +
      `FormAnswerPolicy::viewFile`).
- [x] URL sementara (signed, email/CSV) atau terautentikasi (halaman admin/portal).
- [x] Test akses anonim ditolak (`403`), akses pemilik `200`.
- [x] Test user login tidak berwenang `403`.
- [x] Test organizer event dan ketua tim `200`.
- [x] Test signature tampered/expired `403`.
- [x] Test field non-file tidak dapat dipakai membaca path tersimpan (`404`).
- [x] File legacy pada disk public tetap terbaca lewat route berotorisasi.
- [x] Tidak ada file yang dihapus tanpa rencana: command migrasi dry-run default
      dan menyalin sebelum menghapus.

## Risiko, batasan, dan rollback

- **Signed URL tetap berbasis possession.** Siapa pun yang memegang link dapat
  membukanya sampai TTL habis. Ini trade-off yang dipilih agar penerima guest
  tanpa akun tetap dapat membuka lampiran email. TTL dapat diperpendek lewat
  `FORMS_UPLOAD_URL_TTL_MINUTES`; route juga dibatasi 120 request/menit per
  user/IP.
- **File legacy masih dapat diakses langsung dari `/storage/...` sampai migrasi
  dijalankan.** Command `forms:move-uploads-to-private` disediakan, tetapi tidak
  dijalankan otomatis. Operator harus menjalankan `--dry-run` lalu `--write`
  pada maintenance window. Setelah `--write`, salinan public dihapus dan akses
  hanya lewat route berotorisasi.
- **Soft-deleted field.** Pengecekan tipe field memakai `withTrashed()` supaya
  file dari field yang sudah dihapus tetap dapat diakses lewat route, selama
  field tersebut tercatat sebagai `fileUpload`.
- **Konten PDF/gambar.** File tetap harus divalidasi saat upload oleh
  `SafeUploadFile`/`SafeUploadStorage`; route ini tidak menggantikan validasi
  konten. PDF yang lolos validasi tetap tidak boleh dieksekusi di server.
- **Biaya query.** Setiap download menambah satu query `FormField::exists()`.
  Render daftar submission tidak menambah query per baris karena nama field file
  sudah dihitung sekali dari `$form->formFields`.
- **Rollback.** Tidak ada migration/schema yang perlu di-rollback. Revert commit
  `6e0d262` akan mengembalikan kode ke URL public, sementara file baru sudah
  berada di disk `local`, sehingga file baru menjadi tidak terbaca. Sebelum
  revert, salin `storage/app/private/form-uploads` ke
  `storage/app/public/form-uploads` atau pertahankan route/controller ini
  (forward-fix lebih disarankan). File lama di public tidak disentuh oleh
  rollback.

## Temuan di luar scope (dicatat, tidak diubah)

- **Confirmation dialog menutupi tombolnya sendiri.** Saat E2E, overlay
  `AlertDialog` berada di atas `AlertDialogContent` (keduanya `z-50`, overlay
  menang pada `elementFromPoint`), sehingga klik pointer ke tombol “Submit”
  terhalang. E2E memakai fokus + `Enter` sebagai jalur keyboard yang sah.
  Perbaikan stacking dialog adalah tiket UI terpisah, bukan scope
  `AUDIT-P1-002`.
- **`AUDIT-P2-010`** (`max_size` per field belum diterapkan) tetap terbuka;
  tiket ini hanya memindahkan disk dan menambah otorisasi.
- **`AUDIT-P1-004`** (raw request + inline validation submit) tetap terbuka;
  controller submission belum dipecah ke Form Request/DTO.
- **`storage/app/e2e` mode writable** hanya untuk menjalankan Playwright di
  environment ini; bukan perubahan aplikasi.
- **`boost.json`** sudah modified sebelum tiket dan tidak disentuh.

## Working tree

Commit kode + test: `6e0d262`. Dokumen ini masuk pada commit
`docs: record AUDIT-P1-002 private upload remediation`.
