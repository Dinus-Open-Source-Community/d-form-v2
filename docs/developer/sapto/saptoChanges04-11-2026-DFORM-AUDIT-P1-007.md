# Sapto Changes — 4 November 2026 (DFORM-AUDIT-P1-007)

## Ringkasan

Membuat seluruh job pengirim email idempotent dan menyeragamkan retry/timeout.
Setiap dispatch kini memiliki `delivery_key` unik yang stabil antar-retry, baris
`EmailLog` direservasi sebelum transport dipanggil, dan kegagalan terminal
dicatat tanpa PII.

| Issue | Commit |
| --- | --- |
| AUDIT-P1-007 — Idempotency/retry/timeout email job | `9a93c61` |

## Perubahan

- Menambah kolom `email_logs.delivery_key` nullable + unique index
  (expand-only) melalui migration `2026_11_04_000003`.
- Menambah trait `App\Jobs\Concerns\TracksOutgoingEmail` dengan
  `$tries=3`, `$backoff=[60,300,900]`, `$timeout=60`, reservasi `EmailLog`
  sebelum send, penanganan status `sent`/`queued`/`failed`, `failed()` PII-free,
  dan redaksi alamat email pada `error_message`.
- Memakai trait di 11 job email: registration confirmation/accepted/rejected,
  team invitation, invitee declined notice, leader notice, attendance, dan empat
  job recruitment.
- `SendRecruitmentNotificationJob` mengadopsi baris `queued` bulk QR yang belum
  diklaim (`delivery_key IS NULL`) agar status UI tetap sama.
- `SendRecruitmentCorrectionRequestStaffJob` memakai delivery key per penerima,
  sehingga retry hanya mengirim ulang staff yang belum `sent`.
- `SendRegistrationOtpJob`, `DeleteDeclinedInvitationMemberSubmissionJob`, dan
  `SendBroadcastRecipientJob` mendapat `$timeout=60`; Otp/Delete mendapat
  `failed()` PII-free.
- Menambah `tests/Feature/Jobs/EmailJobIdempotencyTest.php` (10 test) dan
  `tests/e2e/email-job-idempotency.desktop.spec.ts` (happy path + anomali).
- Report ticket: `docs/big-changes/patching-ticket/AUDIT-P1-007.md`.

## Keputusan dan alternatif

- **Delivery key kolom baru** dipilih daripada dedupe `(form_answer_id,
  notification_type)` karena resend manual harus tetap dapat mengirim ulang.
- **Key dibuat di constructor** dan ikut terserialisasi di payload queue;
  sudah diverifikasi pada `Queue.php` (`serialize(clone $job)`) dan mekanisme
  release Redis/database yang memakai payload yang sama.
- **At-most-once saat crash setelah send**: baris `queued` pada retry ditandai
  `failed` dan tidak dikirim ulang. Ini trade-off sadar untuk mencegah duplikat;
  resend manual tetap tersedia.
- **Tidak mengubah `after_commit`** karena itu scope `BE-P1-003`.
- **Tidak mengubah alur resend** (`RecruitmentEmailResendService`); dispatch baru
  menghasilkan delivery key baru.
- **Tidak memperluas redaksi PII** ke seluruh aplikasi; itu scope `AUDIT-P2-006`.

## Verifikasi

- Feature test baru: **10 passed, 65 assertions**.
- Full backend suite: **895 passed, 7 skipped, 5.742 assertions**.
- Migration di MySQL dev (1.000.031 `email_logs`): **DONE 6s**.
- E2E browser desktop (happy path + anomaly): **2 passed (54,1 s)**.
- Pint 18 file: **PASS**.
- Prettier spec E2E: **PASS**.
- `npm run test:e2e:types`: **PASS**.
- Sisa data E2E setelah cleanup: `applications=0`, `email_logs=0`,
  `oprec_forms=0`.

## Checklist

- [x] Setiap job punya `$tries`, `$backoff`, `$timeout`, dan strategi
      idempotency.
- [x] `EmailLog` dibuat sebelum send dengan unique delivery key.
- [x] Retry tidak mengirim dua email untuk delivery yang sama.
- [x] `failed()` mencatat kegagalan tanpa email penerima/raw exception message.
- [x] Resend manual tetap berfungsi (dispatch baru = key baru).
- [x] Bulk QR tetap mengubah baris `queued` menjadi `sent`.
- [x] Partial retry koreksi staff tidak mengirim ulang penerima yang sudah
      `sent`.
- [x] E2E browser happy path + anomali validasi lulus.
- [x] Tidak menyentuh `boost.json`, `Makefile`, atau `UserSeeder.php`.
