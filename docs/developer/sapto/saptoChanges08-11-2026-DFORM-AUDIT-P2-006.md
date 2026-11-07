# Sapto Changes — 08 November 2026 (AUDIT-P2-006)

## Ringkasan

Menutup kebocoran PII dan pesan exception mentah pada log/kolom error job email.
Satu implementasi redaksi (`App\Support\LogRedaction`) dipakai ulang oleh
`TracksOutgoingEmail`, `SendBroadcastRecipientJob`, dan
`SendRegistrationOtpJob`: email di-mask, credential/bearer/basic/URL userinfo
dihilangkan, dan log memakai konteks terstruktur (`exception_class`,
`delivery_key`/`job_id`, `attempt`). Exception transport tidak lagi dilempar
ulang apa adanya: seluruh job email memakai `RedactedMailDeliveryException` yang
menyerialisasi dirinya hanya sebagai `Class: Mail delivery failed.` + stack
trace sendiri dan menghentikan jalur `report()` default Laravel, sehingga
`laravel.log` dan `failed_jobs.exception` tidak menerima pesan mentah. Tidak ada
perubahan schema, migration, route, response contract, atau perilaku pengiriman
email.

| Issue        | Commit                                                     |
| ------------ | ---------------------------------------------------------- |
| AUDIT-P2-006 | `1bbbe04` — `security: redact PII and raw transport errors from email job logs` |

## Perubahan

- `app/Support/LogRedaction.php` (baru): `email()`, `text()`, `exception()` —
  fungsi murni tanpa efek samping (unicode email, quoted local part,
  credential berkutip/JSON, `Bearer`, `Basic`, URL userinfo dengan/tanpa scheme).
- `app/Exceptions/RedactedMailDeliveryException.php` (baru): wrapper dengan
  `fromThrowable()`, `__toString()` bersih (class + `Mail delivery failed.` +
  trace sendiri), dan `report(): bool` yang mencatat `exception_class` lalu
  menghentikan jalur default. `getPrevious()` mempertahankan exception asli.
- `app/Jobs/Concerns/TracksOutgoingEmail.php`: `safeDeliveryError()` memakai
  `LogRedaction::exception()`; `throwRedacted()` dipakai 11 job; `failed()`
  menambah `job_id`, `attempt`, dan daftar penerima ter-mask (`limit(100)`,
  wildcard di-escape).
- `app/Jobs/Broadcasting/SendBroadcastRecipientJob.php`: hapus `email` mentah
  dan `getMessage()` mentah dari log; `error_message` memakai format ter-redaksi
  di catch dan `failed()`; rethrow lewat wrapper.
- `app/Jobs/SendRegistrationOtpJob.php`: log kegagalan memakai `exception_class`
  + pesan ter-redaksi + `job_id`/`attempt`; log sukses `OTP sent.` sebagai
  anchor observability; rethrow lewat wrapper.
- `tests/Unit/Support/LogRedactionTest.php` (baru),
  `tests/Unit/Exceptions/RedactedMailDeliveryExceptionTest.php` (baru),
  `tests/Feature/Jobs/EmailLogRedactionTest.php` (baru),
  `tests/e2e/pii-log-redaction.desktop.spec.ts` (baru).
- `tests/Feature/Auth/SendRegistrationOtpJobTest.php`,
  `tests/Feature/Recruitment/RecruitmentEmailDeliveryTest.php`: menyesuaikan
  class wrapper + `getPrevious()`.
- `docs/big-changes/patching-ticket/AUDIT-P2-006.md` (baru): report + evidence.
- `docs/big-changes/patching.md`: status tiket `In Review`, resolusi, dan dua
  tiket lanjutan (`AUDIT-P2-014`, `AUDIT-P2-015`).

## Verifikasi

- `php artisan test tests/Unit/Support/LogRedactionTest.php tests/Unit/Exceptions tests/Feature/Jobs tests/Feature/Auth/SendRegistrationOtpJobTest.php tests/Feature/Recruitment/RecruitmentEmailDeliveryTest.php`
  → 58 passed (185 assertions).
- `php artisan test tests/Feature/Jobs tests/Feature/Broadcasting` → 27 passed,
  2 skipped (117 assertions).
- `php artisan test tests/Feature/Recruitment` → 328 passed (2.277 assertions).
- Full backend suite → 1.049 passed, 7 skipped (6.164 assertions).
- `./vendor/bin/pint --test` pada 21 file scope → PASS.
- `(string) RedactedMailDeliveryException::fromThrowable(...)` →
  `ok_email|ok_secret|ok_raw|ok_next|prev=same`; log worker nyata mencatat
  `[RedactedMailDeliveryException] Mail delivery failed.` tanpa pesan transport.
- Playwright `chromium-desktop` `tests/e2e/pii-log-redaction.desktop.spec.ts` →
  3 passed: happy path OTP, failure transport fail-fast (log ter-redaksi,
  `pending` tetap ada, user belum dibuat), dan anomali validasi
  (password lemah, konfirmasi tidak sama, email terdaftar, OTP salah).

Detail command, batasan lingkungan, dan temuan lanjutan ada di report
[`AUDIT-P2-006`](../../big-changes/patching-ticket/AUDIT-P2-006.md).
