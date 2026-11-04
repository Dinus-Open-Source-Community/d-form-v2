# AUDIT-P1-007 — Idempotency, retry, dan timeout email job

| Field | Nilai |
| --- | --- |
| Klasifikasi | bug (queue reliability / idempotency) |
| Prioritas | P1 |
| Evidence | Confirmed (source) → Reproduced (regression test) → Measured (suite + E2E) |
| Status | In Review |
| Baseline | `dev-zapp` setelah `464fed1` |
| Commit kode + test | `9a93c61` — `fix: make email jobs idempotent with delivery keys and timeouts` |
| Sumber tiket | `docs/big-changes/patching.md` §`AUDIT-P1-007`; `2026-10-30-agents-compliance-audit.md` §`AUDIT-P1-007`; `2026-10-30-backend-audit-batch-2.md` §`BE-P3-004` |

## Ringkasan

Sebelas job pengirim email menulis `EmailLog` **setelah** `Mail::send`, tidak punya
identitas delivery yang stabil antar-retry, dan tidak punya `$timeout`. Jika
transport menerima pesan lalu proses berhenti sebelum baris log tersimpan,
retry mengirim ulang email yang sama. `SendRecruitmentNotificationJob` juga
membuat baris `failed` baru pada setiap retry ketika tidak ada baris `queued`
yang bisa diperbarui.

Perbaikan: setiap job email memiliki `$tries`, `$backoff`, `$timeout`, dan
`delivery_key` unik yang dibuat sekali saat dispatch lalu ikut terserialisasi di
payload queue. Baris `EmailLog` direservasi sebagai `queued` **sebelum**
transport dipanggil; retry melihat status `sent`/`queued` dan berhenti, sehingga
satu delivery tidak pernah mengirim dua email. Retry setelah kegagalan
transport tetap berjalan karena baris `failed` dikembalikan ke `queued`.

## Akar masalah (sebelum)

1. **Tidak ada timeout.** Dari 14 job class, hanya 4 yang punya `$tries`/
   `$backoff`; tidak ada satu pun yang mendefinisikan `$timeout`. Worker dapat
   menggantung sampai `retry_after` (90 detik) tanpa batas kerja job.
2. **Log ditulis setelah send.** Pola di 11 job: `Mail::send()` → `EmailLog::create()`.
   Kegagalan setelah transport menerima pesan (crash, DB putus) membuat retry
   mengirim ulang karena tidak ada bukti delivery.
3. **Tidak ada identitas delivery.** Retry dan dispatch baru tidak dapat
   dibedakan. `SendRecruitmentNotificationJob` membuat baris `failed` baru pada
   setiap attempt ketika tidak ada baris `queued`, sehingga riwayat retry
   menumpuk.
4. **`failed()` tidak ada.** Kegagalan terminal meninggalkan baris `queued` yang
   menggantung atau tidak mencatat kegagalan sama sekali (selain broadcast).

## Keputusan desain

| Keputusan | Alasan / alternatif yang ditolak |
| --- | --- |
| Kolom `email_logs.delivery_key` nullable + unique | Identitas delivery yang eksplisit dan atomik. Alternatif "hanya log sebelum send" tidak dapat membedakan retry dari resend manual tanpa kolom; alternatif dedupe berdasarkan `(form_answer_id, notification_type)` akan memblokir resend yang memang disengaja. |
| `deliveryKey` UUID dibuat di constructor job | Payload queue dibuat dengan `serialize(clone $job)` (`vendor/laravel/framework/src/Illuminate/Queue/Queue.php:160`); release Redis/database memakai payload yang sama, jadi key stabil antar-retry. Alternatif memakai `$this->job->uuid()` tidak tersedia saat `handle()` dipanggil langsung di test. |
| Reservasi `EmailLog` sebelum send | Menutup jendela "transport sukses, log gagal". Status `queued` yang ditemukan pada retry dianggap delivery terputus: ditandai `failed` dan **tidak** dikirim ulang (at-most-once). |
| `failed` → boleh retry | Kegagalan transport yang jelas belum mengirim pesan; baris direset ke `queued` lalu dicoba lagi. |
| Adopsi baris `queued` milik `RecruitmentQrBulkService` | Bulk QR sengaja membuat baris `queued` sebelum job berjalan agar status UI langsung terlihat. Adopsi dibatasi pada baris `delivery_key IS NULL` agar dua job berbeda tidak saling menimpa. |
| Key per-recipient untuk job koreksi staff | Satu job mengirim ke beberapa staff. Saat satu penerima gagal, retry hanya mengirim ulang penerima yang belum `sent`, bukan seluruh daftar. |
| `failed()` hanya mencatat job, delivery key, notification type, exception class | Acceptance meminta kegagalan tanpa PII. Email penerima dan raw exception message tidak masuk ke context log. `error_message` tetap menyimpan class + pesan yang alamat emailnya diganti `[email]`. |
| Job non-email (Otp, Delete, Broadcast) hanya diberi `$timeout` + `failed()` PII-free | Idempotency-nya sudah berbeda bentuk (supersede OTP, delete bersyarat, status recipient broadcast). Mengubahnya akan menambah alasan berubah di luar tiket. |
| Timeout seragam 60 detik | `retry_after` queue 90 detik; 60 detik memberi ruang untuk jitter 5–10 detik + generasi QR + transport, tanpa membuat worker menggantung. |

## Perubahan

| File | Perubahan |
| --- | --- |
| `database/migrations/2026_11_04_000003_add_delivery_key_to_email_logs_table.php` | Tambah `delivery_key` nullable `varchar(64)` + unique index (expand-only). `down()` menghapus index dan kolom. |
| `app/Models/EmailLog.php` | `delivery_key` masuk `$fillable`. |
| `app/Jobs/Concerns/TracksOutgoingEmail.php` | Trait baru: `$tries=3`, `$backoff=[60,300,900]`, `$timeout=60`, `deliveryKey`, `reserveEmailLog()`, `markEmailLogSent()`, `markEmailLogFailed()`, `markEmailLogFailedWithReason()`, `markEmailLogUndeliverable()`, `failed()`, `safeDeliveryError()`. |
| 11 job email | `SendRegistrationConfirmationJob`, `SendRegistrationAcceptedJob`, `SendRegistrationRejectedJob`, `SendTeamInvitationJob`, `SendInviteeInvitationDeclinedNoticeJob`, `SendTeamInvitationLeaderNoticeJob`, `RecordAttendanceJob`, `SendRecruitmentApplicationConfirmationJob`, `SendRecruitmentCorrectionRequestStaffJob`, `SendRecruitmentInterviewerNotificationJob`, `SendRecruitmentNotificationJob`. Reservasi sebelum send; log setelah sukses; `failed` saat transport gagal. |
| `SendRecruitmentNotificationJob` | Baris `queued` dari bulk QR diadopsi lewat `latestQueuedLog()` yang kini memfilter `delivery_key IS NULL`; helper `markQueuedLogSent/Failed` dihapus. |
| `SendRegistrationOtpJob`, `DeleteDeclinedInvitationMemberSubmissionJob`, `SendBroadcastRecipientJob` | `$timeout=60`; `failed()` PII-free untuk Otp/Delete. Idempotency lama dipertahankan. |
| `tests/Feature/Jobs/EmailJobIdempotencyTest.php` | 10 regression test baru (lihat Verifikasi). |
| `tests/e2e/email-job-idempotency.desktop.spec.ts` | Dua E2E browser: happy path publik OpRec dan anomali validasi. |

## Perilaku sebelum vs sesudah

| Skenario | Sebelum | Sesudah |
| --- | --- | --- |
| Retry setelah send sukses + log gagal tersimpan | Kirim ulang (duplikat) | Baris `queued` ditemukan → tidak kirim ulang, ditandai `failed` dengan alasan terputus |
| Retry setelah transport gagal | Kirim ulang | Baris `failed` direset `queued` → kirim ulang sekali |
| `SendRecruitmentNotificationJob` retry tanpa baris queued | Baris `failed` baru setiap attempt | Satu baris delivery keyed, direset/ditandai sesuai status |
| Bulk QR interview | Job mengubah baris `queued` terbaru | Job mengadopsi baris `queued` yang belum diklaim, lalu `sent` |
| Koreksi staff: satu penerima gagal | Retry mengirim ulang semua penerima | Retry hanya menerima yang belum `sent` |
| Job menggantung | Menunggu `retry_after`/tanpa batas kerja | `$timeout=60` |
| `failed()` terminal | Tidak ada (kecuali broadcast) | Menandai semua log non-sent dan menulis log tanpa PII |
| Resend manual | Mengirim lagi | Tetap mengirim lagi: dispatch baru = delivery key baru |

## Verifikasi

| Pemeriksaan | Hasil |
| --- | --- |
| Regression test baru | **10 passed (65 assertions)** |
| Full backend suite | **895 passed, 7 skipped (5.742 assertions)** |
| Migration di MySQL dev (1.000.031 `email_logs`) | **DONE 6s** |
| Pint (18 file berubah) | **PASS** |
| Prettier (spec E2E) | **PASS** |
| `npm run test:e2e:types` | **PASS** |
| E2E browser desktop (happy + anomaly) | **2 passed (54,1s)** |
| Sisa data E2E | `applications=0`, `email_logs=0`, `oprec_forms=0` (temporary seed dihapus) |

### Red → green

```text
# sebelum implementasi
Tests: 9 failed, 1 passed (6 assertions)

# sesudah implementasi
Tests: 10 passed (65 assertions)
```

### E2E browser

Environment dev memakai SMTP Gmail. Agar tidak ada email nyata:

- `MAIL_MAILER` diubah sementara ke `log`; container app di-recreate supaya
  `env_file` dibaca ulang; setelah test `.env` dipulihkan dan container
  di-recreate kembali. `config('mail.default')` diverifikasi `smtp` lagi.
- Periode open yang tersedia (`Open Recruitment DOSCOM 2026 Batch 50`) sudah
  melewati `registration_closes_at`; nilainya diperpanjang sementara, lalu
  dikembalikan ke nilai mentah `2026-08-23 17:00:00`.
- `OprecFormSeeder` belum ada di DB dev (halaman `/recruitment` 503). Form dan
  field di-seed sementara, lalu di-`forceDelete` setelah test; `/recruitment`
  kembali 503.
- Folder heartbeat E2E (`storage/app/e2e`) dimiliki UID container 165617 dan
  tidak dapat ditulis user host; folder diberi mode 777 agar Playwright host
  dapat membuat heartbeat. Perubahan permission ini dibiarkan karena memang
  diperlukan untuk menjalankan E2E di environment ini.

Perintah:

```bash
E2E_BASE_URL=http://localhost:3000 \
E2E_ADMIN_EMAIL=stress-user-1@stress.test \
E2E_ADMIN_PASSWORD=password \
npx playwright test email-job-idempotency --project=chromium-desktop --no-deps --retries=0
```

- **Happy path:** isi 3 langkah form OpRec (identitas, dua divisi berbeda,
  upload CV PDF + bukti follow PNG + link twibbon), submit, halaman
  `/recruitment/success` tampil. Poll DB hingga tepat **satu** `email_logs`
  `recruitment_application_submitted` berstatus `sent` dan memiliki
  `delivery_key`. Tidak ada baris duplikat.
- **Anomali:** klik "Lanjut" pada langkah kosong diblokir di client dengan pesan
  "wajib diisi"; setelah identitas valid, memilih divisi cadangan yang sama dan
  twibbon `javascript:alert(1)` ditolak server dengan pesan
  "Divisi cadangan tidak boleh sama dengan divisi utama", halaman tidak pindah
  ke success, dan tidak ada application row.
- Retry queue tidak dapat diputar ulang dari browser tanpa payload job asli;
  idempotency retry dibuktikan oleh 10 feature test (termasuk serialization
  key, interrupted queued, dan `failed()`).

## Acceptance criteria

- [x] Setiap job punya `$tries`, `$backoff`, `$timeout`, dan strategi
      idempotency.
- [x] `EmailLog` dibuat sebelum send dan memakai unique delivery key.
- [x] Job `failed()` mencatat kegagalan tanpa PII (tanpa email penerima dan raw
      exception message di context).
- [x] Test retry tidak mengirim dua email (registration, recruitment
      confirmation, recruitment notification).
- [x] Resend manual tetap berfungsi (dispatch baru = key baru).
- [x] Partial retry koreksi staff tidak mengirim ulang penerima yang sudah
      `sent`.
- [x] Bulk QR tetap mengubah baris `queued` menjadi `sent`.

## Risiko, batasan, dan rollback

- **At-most-once saat crash setelah transport menerima pesan.** Baris tetap
  `queued`; retry tidak mengirim ulang dan menandainya `failed` dengan alasan
  "interrupted". Ini trade-off yang dipilih karena acceptance memprioritaskan
  tidak ada duplikat. Operator dapat melakukan resend manual (delivery key
  baru).
- **Index unik pada tabel besar.** Dev MySQL (1 juta baris `email_logs`) selesai
  dalam 6 detik. Di production, jalankan pada maintenance window dan pantau
  lock; migration bersifat expand-only dan tidak mengubah data lama.
- **Job lama yang sudah ada di queue saat deploy** masih memakai kode lama
  sampai habis; tidak ada baris yang rusak karena `delivery_key` nullable.
- **`BE-P1-003` (`after_commit`) tetap scope terpisah.** Delivery key tidak
  memperbaiki job yang di-dispatch dari dalam transaksi; itu tetap ditangani
  tiket tersebut.
- **`AUDIT-P2-006` tetap lebih luas.** `error_message` sudah mengganti alamat
  email dengan `[email]`, tetapi redaksi PII menyeluruh (termasuk broadcast
  `SendBroadcastRecipientJob` yang masih mencatat `email` di catch) belum
  dikerjakan di tiket ini.
- **`SendRegistrationOtpJob::$backoff` tetap `30`** (integer) karena perilaku
  OTP punya alasan sendiri; `$timeout` tetap 60 seperti job lain.
- **Rollback:** revert commit `9a93c61` lalu `php artisan migrate:rollback
  --step=1`. Tidak ada route, dependency, atau response contract yang berubah.

## Temuan di luar scope (dicatat, tidak diubah)

- `SendBroadcastRecipientJob` masih mencatat `email` mentah dan pesan exception
  di `Log::error` (AUDIT-P2-006).
- `SendRecruitmentNotificationJob` mengimpor `AppliesOutgoingEmailDelay` tetapi
  tidak pernah memanggil `applyOutgoingEmailJitter()`; perilaku lama
  dipertahankan agar tidak menambah perubahan timing di tiket ini.
- `RecruitmentEmailResendService` tetap membuat dispatch baru (delivery baru)
  sehingga resend tidak terblokir; tidak ada perubahan pada batas 3x/hari.
- `storage/app/e2e` kini mode 777 (lihat bagian E2E); ini perbaikan environment
  lokal, bukan perubahan aplikasi.

## Working tree

`boost.json` sudah modified sebelum tiket dan tidak disentuh. Perubahan tiket
ini adalah 19 file pada commit `9a93c61` ditambah dokumen ini, changelog, dan
baris README.
