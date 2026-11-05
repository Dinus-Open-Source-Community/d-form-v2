# Sapto Changes — 05 November 2026 (DFORM-AUDIT-P1-002)

## Ringkasan

Upload jawaban form dan lampiran tim dipindahkan dari disk `public` ke disk
privat `local`. Akses file sekarang melewati route berotorisasi dan signed URL
sementara untuk email/CSV. File legacy tetap terbaca lewat route yang sama dan
dapat dimigrasikan dengan command dry-run default.

| Issue                                       | Commit                                                               |
| ------------------------------------------- | -------------------------------------------------------------------- |
| AUDIT-P1-002 — Upload form pada public disk | `security: move form uploads to private disk with authorized access` |

## Perubahan

- `FormAnswerFileStorage` menjadi satu pintu simpan/akses upload: tulis ke
  `local`, resolve path lokal dengan fallback public legacy, bentuk relative URL
  dan temporary signed URL, serta validasi bahwa field benar-benar `fileUpload`
  dan path berada di `form-uploads/{formId}/`.
- `FormAnswerFileController` + route `dashboard.form-answers.files` melayani
  inline/attachment hanya untuk signature valid atau user yang lolos
  `FormAnswerPolicy::viewFile`. Route diberi throttle `form-answer-file`
  (120/menit per user/IP) dan header `X-Content-Type-Options: nosniff`.
- `FormAnswerPolicy::viewFile` mengizinkan pemilik submission, organizer yang
  boleh `view` event, dan ketua tim untuk anggota satu grup.
- `FormSubmissionController` dan `TeamInvitationController` memakai service yang
  sama untuk menyimpan upload; tidak ada lagi `UploadStorageTarget::public` pada
  jalur jawaban form/lampiran tim.
- `RegistrationAnswersSummarizer` memakai relative URL untuk portal dan temporary
  signed URL untuk email/CSV. `FormController` dan `TeamInvitationController`
  memetakan nilai file pada prop Inertia ke URL berotorisasi.
- Command `forms:move-uploads-to-private` memindahkan file legacy dari disk
  public ke local. Default dry-run; `--write` menyalin lebih dulu lalu menghapus
  salinan public; `--form=` membatasi satu form.
- Config baru: `events.forms.upload_temporary_url_ttl_minutes` (default 10080)
  dan `events.forms.file_download_rate_limit_per_minute` (default 120).
- Tidak ada perubahan schema database; `form_answers.answers` tetap menyimpan
  path relatif.

## Keputusan desain

- Disk privat dipilih karena URL publik permanen tidak dapat dicabut setelah
  bocor. Nama file acak saja tidak cukup.
- Satu route menerima dua bentuk otorisasi. Halaman admin/portal memakai route
  relatif tanpa kedaluwarsa; email/CSV memakai signed URL agar penerima bundle
  guest tanpa akun tetap dapat membuka lampiran.
- Validasi tipe field dan prefix path mencegah field teks berisi path karangan
  dipakai membaca file peserta lain di direktori form yang sama.
- Migrasi legacy tidak dijalankan otomatis. Command menyalin ke privat lebih
  dulu dan hanya menghapus salinan public setelah salinan privat terbukti ada.

## Verifikasi

- Regression test tiket: **18 passed (45 assertions)**.
- Full backend suite: **909 passed, 7 skipped (5.749 assertions)**.
- E2E browser desktop: **2 passed** — happy path upload + akses, dan anomali
  `.exe` (client) serta PDF ber-`/JavaScript` (server); fixture dibersihkan
  (`forms=0`, `events=0`, `fields=0`).
- Pint 15 file berubah: **PASS**.
- `npm run typecheck`: **exit 0**.
- `npm run lint`: **exit 0**.
- `npx tsc -p tsconfig.e2e.json --noEmit`: **exit 0**.
- Prettier spec E2E: **PASS**.
- `npm run build` tidak dijalankan karena tidak ada perubahan kode produksi
  frontend/bundle.

## Catatan operasional

- Jalankan `php artisan forms:move-uploads-to-private --dry-run` lalu `--write`
  pada maintenance window untuk memindahkan file legacy. Command tidak
  dijalankan otomatis dan tidak menghapus file tanpa `--write`.
- Setelah `--write`, akses langsung `/storage/form-uploads/...` tidak lagi
  tersedia; akses hanya lewat route berotorisasi.
- Rollback kode tanpa memindahkan file baru kembali ke public membuat file baru
  tidak terbaca. Forward-fix atau salin `storage/app/private/form-uploads` ke
  `storage/app/public/form-uploads` sebelum revert.
- Ditemukan di luar scope: overlay `AlertDialog` menutupi tombol konfirmasi
  submit sehingga klik pointer terhalang; E2E memakai fokus + `Enter`. Perbaikan
  dialog adalah tiket UI terpisah.
