# AUDIT-P2-006 — PII dan raw exception di log

| Field | Nilai |
| --- | --- |
| Klasifikasi | security |
| Prioritas | P1 |
| Evidence | Confirmed (source) → Reproduced (regression test) → Measured (suite + E2E browser) |
| Status | In Review |
| Baseline | `dev-zapp` (working tree 08-11-2026) |
| Commit | `1bbbe04` — `security: redact PII and raw transport errors from email job logs` |
| Sumber tiket | `docs/big-changes/patching.md` §`AUDIT-P2-006`; `2026-10-30-agents-compliance-audit.md` §`AUDIT-P2-006`; `2026-10-30-backend-audit-batch-2.md` §`BE-P3-005` |
| File kode | `app/Support/LogRedaction.php`, `app/Jobs/Concerns/TracksOutgoingEmail.php`, `app/Jobs/Broadcasting/SendBroadcastRecipientJob.php`, `app/Jobs/SendRegistrationOtpJob.php` |
| File test | `tests/Unit/Support/LogRedactionTest.php`, `tests/Feature/Jobs/EmailLogRedactionTest.php`, `tests/e2e/pii-log-redaction.desktop.spec.ts` |

## Ringkasan

Tiga jalur masih menulis PII atau pesan exception mentah ke log/kolom error:

1. `SendBroadcastRecipientJob` mencatat `'email' => $recipient->email` dan
   `'exception' => $e->getMessage()` di `Log::error`, lalu menyimpan
   `mb_substr($e->getMessage(), 0, 1000)` ke `email_broadcast_recipients.error_message`
   (catch dan `failed()`).
2. `SendRegistrationOtpJob` mencatat `'error' => $exception->getMessage()`.
3. `TracksOutgoingEmail::safeDeliveryError()` punya regex email satu-off di dalam
   trait, sehingga tidak ada satu implementasi redaksi yang bisa dipakai ulang.

Perbaikan: satu implementasi `App\Support\LogRedaction` (mask email, redaksi
credential/bearer/basic/URL userinfo, format exception) dipakai oleh trait dan
kedua job. Log memakai konteks terstruktur: `exception_class`, `delivery_key` /
`job_id`, `attempt`, dan email yang sudah di-mask. `error_message` menjadi
`{ExceptionClass}: {pesan ter-redaksi}`.

Selain itu, exception transport **tidak lagi dilempar ulang apa adanya**: seluruh
job email (11 job yang memakai `TracksOutgoingEmail` + 2 job di luar trait)
melempar `App\Exceptions\RedactedMailDeliveryException`. Wrapper ini
menyerialisasi dirinya hanya sebagai `Class: Mail delivery failed.` + stack
trace miliknya sendiri, dan `report()`-nya mencatat satu baris generik lalu
menghentikan jalur default Laravel. Dengan begitu `laravel.log` dan
`failed_jobs.exception` tidak pernah menerima pesan transport mentah (termasuk
`[previous exception]` yang ditulis Monolog), sementara `getPrevious()` tetap
menyimpan exception asli untuk diagnostik kode.

Tidak ada perubahan schema, migration, route, response contract, atau behavior
pengiriman email.

## Akar masalah (sebelum)

```php
// SendBroadcastRecipientJob::handle() — catch
Log::error('[SendBroadcastRecipientJob] send failed.', [
    'email' => $recipient->email,          // PII mentah
    'exception' => $e->getMessage(),       // pesan transport mentah
]);
$recipient->forceFill([
    'error_message' => mb_substr($e->getMessage(), 0, 1000), // mentah + 1000 char
])->save();
```

```php
// SendRegistrationOtpJob::handle() — catch
Log::error('Gagal mengirim kode verifikasi OTP registrasi.', [
    'pending_id' => $pending->id,
    'error' => $exception->getMessage(),   // mentah
]);
```

```php
// TracksOutgoingEmail::safeDeliveryError() — regex privat, tidak reusable
$redacted = preg_replace('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', '[email]', ...);
```

Konsekuensi: alamat penerima dan detail transport masuk ke berkas log (dan
`error_message` yang dirender dashboard), sehingga retensi log menyimpan PII
tanpa batas dan pesan transport dapat memuat kredensial/path internal.

## Keputusan desain

| Keputusan | Alasan / alternatif yang ditolak |
| --- | --- |
| Satu `LogRedaction` dipakai trait + dua job | Aturan user 6 (dedup = satu implementasi). Alternatif menyalin regex di tiap job ditolak karena akan berbeda-beda dan tidak teruji. |
| Email di-mask, bukan dihapus (`a***@domain.tld`) | Operasional masih perlu membedakan penerima/domain saat mendiagnosis kegagalan; hash penuh menghilangkan sinyal itu. |
| `LogRedaction::text()` tetap menyimpan pesan diagnostik yang sudah ter-redaksi | Audit meminta "detail redacted", bukan "detail dihapus". Membuang seluruh pesan membuat kegagalan transport tidak dapat didiagnosis. |
| `email_logs.recipient_email` dan `email_broadcast_recipients.email` **tetap** plaintext | Keduanya adalah ledger pengiriman (dibaca untuk resend/audit) dan PII yang sama sudah tersimpan mentah di tabel sumber (`form_answers.answers`, `recruitment_applications.personal_email`). Mem-mask ledger menghilangkan nilai audit tanpa mengurangi paparan. Yang diperbaiki tiket ini adalah sink log/`error_message`. Lihat tiket lanjutan `AUDIT-P2-015` untuk kebijakan retensi PII di database. |
| `correlation ID` memakai `delivery_key` / `job_id` + `attempt` | `AUDIT-P2-005` (middleware correlation ID global) masih Open dan merupakan scope terpisah; job sudah punya identitas delivery yang stabil lintas retry. |
| Tidak menambah kolom/migration | Semua data yang dibutuhkan untuk diagnosis sudah tersedia (`delivery_key`, `job_id`, `exception_class`); menambah kolom hanya untuk menyimpan hash email akan menambah beban tanpa kebutuhan yang terbukti. |

## Perubahan per file

### `app/Support/LogRedaction.php` (baru)

Final class, tiga fungsi murni:

- `email(?string): ?string` — `applicant@example.com` → `a***@example.com`;
  nilai tanpa `@` → `[redacted]`; null/string kosong dikembalikan apa adanya.
- `text(string, int $limit = 500): string` — urutan redaksi: URL userinfo
  (dengan atau tanpa scheme, termasuk password yang mengandung `@`) →
  `Bearer <token>` → `Basic <base64>` → pasangan credential berkutip
  (`"password":"..."`, `password="my secret"`) → pasangan credential biasa →
  alamat email (unicode, termasuk local part berkutip) → `Str::limit`.
- `exception(Throwable, int $limit = 500): string` — `{Class}: {text()}`.

### `app/Exceptions/RedactedMailDeliveryException.php` (baru)

Wrapper exception untuk kegagalan transport email:

- `fromThrowable(Throwable): self` memakai `LogRedaction::exception(..., 1000)`.
- `__toString()` hanya mengembalikan `Class: Mail delivery failed.` + stack
  trace wrapper yang diformat eksplisit per frame (`#N class->method() at
  file:line`) sehingga **argumen frame tidak pernah dirender**, berapa pun nilai
  `zend.exception_ignore_args`. Ini menutup `failed_jobs.exception`, yang diisi
  `DatabaseFailedJobProvider` lewat `(string) $exception`
  (`vendor/.../DatabaseFailedJobProvider.php:59`), dari bagian `Next ...` milik
  PHP maupun argumen pemanggil.
- `report(): bool` mencatat `exception_class`, `original_class`, dan
  `error` = `LogRedaction::exception($previous, 1000)` lalu mengembalikan `true`,
  sehingga `Handler::reportThrowable()` tidak meneruskan objek ke Monolog (yang
  akan mencetak blok `[previous exception]`). Detail transport (host/port) tetap
  tersedia untuk triage, tanpa PII.
- `getPrevious()` tetap menyimpan exception asli.

### `app/Jobs/Concerns/TracksOutgoingEmail.php`

- `safeDeliveryError()` mendelegasikan ke `LogRedaction::exception()` (regex
  privat dihapus).
- `throwRedacted(Throwable): never` melempar wrapper; dipakai 11 job pemakai
  trait (`SendRegistration{Confirmation,Accepted,Rejected}Job`,
  `RecordAttendanceJob`, `SendTeamInvitationJob`,
  `SendTeamInvitationLeaderNoticeJob`, `SendInviteeInvitationDeclinedNoticeJob`,
  `Recruitment/SendRecruitment{Notification,ApplicationConfirmation,CorrectionRequestStaff,InterviewerNotification}Job`).
- `failed()` menambahkan `job_id` (`$this->job?->getJobId()`), `attempt`
  (`$this->attempts()`), dan `recipients` berisi daftar email ter-mask dari
  `EmailLog` milik delivery tersebut (query dibatasi `limit(100)`, wildcard
  `%_` di-escape). Key `job`, `delivery_key`, `exception_class` dipertahankan.
- Docblock mencatat bahwa `$this->job`/`attempts()` berasal dari
  `Illuminate\Foundation\Queue\Queueable`.

### `app/Jobs/Broadcasting/SendBroadcastRecipientJob.php`

- Catch: `'email'` mentah dihapus → `'recipient' => LogRedaction::email(...)`;
  `'exception' => getMessage()` diganti `'exception_class'` + `'error'`
  ter-redaksi + `job_id` tetap `recipient_id`/`broadcast_id`/`attempt`.
- `error_message` (catch dan `failed()`) memakai `LogRedaction::exception($e, 1000)`.
- Rethrow melalui `RedactedMailDeliveryException::fromThrowable()`.

### `app/Jobs/SendRegistrationOtpJob.php`

- Catch: `'error'` mentah diganti `exception_class` + `error` ter-redaksi, plus
  `job_id` dan `attempt`; rethrow melalui wrapper.
- Log sukses baru `[SendRegistrationOtpJob] OTP sent.` dengan `pending_id`,
  `job_id`, `attempt` saja (tanpa email/kode) sebagai anchor observability dan
  anchor test E2E.

## Testing

### Unit + feature (container `d_form_app`)

| Command | Hasil |
| --- | --- |
| `php artisan test tests/Unit/Exceptions tests/Unit/Support/LogRedactionTest.php tests/Feature/Jobs tests/Feature/Auth/SendRegistrationOtpJobTest.php tests/Feature/Recruitment/RecruitmentEmailDeliveryTest.php` | **58 passed** (185 assertions), exit 0 |
| `php artisan test tests/Feature/Jobs tests/Feature/Broadcasting` | **2 skipped, 27 passed** (117 assertions), exit 0 |
| `php artisan test tests/Feature/Recruitment` | **328 passed** (2.277 assertions), exit 0 (regression job recruitment yang memakai trait) |
| `php artisan test` (suite penuh, run final) | **1.049 passed**, 7 skipped (6.164 assertions), exit 0 |
| `./vendor/bin/pint --test <21 file scope>` / `<8 file scope final>` | PASS, exit 0 |
| `npx tsc -p tsconfig.e2e.json` + `eslint tests/e2e/pii-log-redaction.desktop.spec.ts` | exit 0 |

Test baru yang menahan regresi:

- `LogRedactionTest` — mask email (normal/kosong/null/malformed/unicode/local part
  berkutip), redaksi email, `password=`, `token=`, `"token":"x"`,
  `password="my secret"`, `Bearer`, `Authorization: Basic <base64>`, URL userinfo
  dengan/tanpa scheme dan password ber-`@`, truncation, format exception.
- `RedactedMailDeliveryExceptionTest` — `fromThrowable()` mempertahankan class,
  `getPrevious()` mengembalikan exception asli, `(string)` tidak memuat pesan
  mentah/alamat/`Next `, dan `report()` mencatat tanpa PII lalu mengembalikan
  `true`.
- `EmailLogRedactionTest` — kegagalan kirim broadcast dan OTP tidak menyimpan
  email/kredensial mentah di `error_message` maupun konteks log (dengan
  `array_key_exists('error', $context)` dan `JSON_THROW_ON_ERROR` agar tidak
  lolos secara vakum); `failed()` mencatat penerima ter-mask; job melempar
  wrapper dan `getPrevious()` adalah exception asli.
- Test-first: suite awal gagal lebih dulu (`Class "App\Support\LogRedaction" not
  found`; `Log::shouldHaveReceived('error') called 0 times`) sebelum implementasi.

### Verifikasi sink framework (F1)

| Bukti | Hasil |
| --- | --- |
| `(string) RedactedMailDeliveryException::fromThrowable(new RuntimeException('SMTP rejected applicant@example.com password=hunter2'))` | `ok_email\|ok_secret\|ok_raw\|ok_next\|prev=same` |
| `DatabaseFailedJobProvider::log()` | memakai `(string) mb_convert_encoding($exception, 'UTF-8')` (`vendor/.../DatabaseFailedJobProvider.php:59`) → hanya melihat `__toString()` wrapper |
| Log worker nyata saat E2E failure path | `local.ERROR: [RedactedMailDeliveryException] Mail delivery failed. {"exception_class":"App\\Exceptions\\RedactedMailDeliveryException"}` — tanpa `Next`, tanpa pesan transport, tanpa alamat |
| Log terstruktur job | `Gagal mengirim kode verifikasi OTP registrasi. {"pending_id":…,"job_id":…,"attempt":1,"exception_class":"Symfony\\…\\TransportException","error":"Connection could not be established …"}` — tanpa alamat/kredensial |
| Serialisasi wrapper dengan argumen pemanggil | `App\Exceptions\RedactedMailDeliveryException: Mail delivery failed.` + `#0 ...::fromThrowable() at …` / `#1 wrapIt() at …` — tanpa argumen, tanpa pesan mentah, tanpa `Next `; `getPrevious()` tetap asli |
| Regression handler (unit test) | `app(ExceptionHandler::class)->report($wrapper)` → konteks log tidak memuat key `exception`, memuat `original_class`, dan bebas pesan mentah |

### E2E browser (Playwright, `chromium-desktop`)

`tests/e2e/pii-log-redaction.desktop.spec.ts` menggerakkan alur pendaftaran OTP
sungguhan di browser:

| Skenario | Hasil | Bukti |
| --- | --- | --- |
| Happy path: daftar → halaman OTP → verifikasi → dashboard | **passed** (6,8 s; percobaan pertama gagal pada marker hidrasi `data-email-availability`, retry Playwright lulus) | `otp-email` = email marker, `users` = 1, log `[SendRegistrationOtpJob] OTP sent.` ditemukan (anchor positif) dan tidak memuat alamat mentah |
| Failure path: transport SMTP fail-fast (`127.0.0.1:1`) | **passed** (5,0 s; dijalankan ulang setelah follow-up review) | baris `Gagal mengirim kode verifikasi OTP registrasi.` dengan `pending_id` run ini memuat `exception_class`, `job_id`, `attempt`, tidak memuat alamat mentah; baris wrapper memuat `original_class` + `error` ter-redaksi tanpa `Next`; `pending_registrations` = 1, `users` = 0 |
| Anomali: password < 8, konfirmasi tidak sama, email sudah terdaftar, OTP salah | **passed** (12,7 s) | tidak ada `pending_registrations`/`users` baru untuk input yang ditolak; OTP salah menampilkan alert dan tetap `pending`; anchor positif `OTP sent.` ada sebelum assertion negatif |

Command:

```
E2E_BASE_URL=http://localhost:3000 \
E2E_ADMIN_EMAIL=stress-user-1@stress.test E2E_ADMIN_PASSWORD=password \
npx playwright test --project=chromium-desktop tests/e2e/pii-log-redaction.desktop.spec.ts
```

Catatan lingkungan (tidak masuk commit):

- `.env` dev memakai `MAIL_MAILER=smtp` dengan kredensial nyata. Untuk dua
  skenario yang memerlukan transport non-kirim, `MAIL_MAILER` di-override
  sementara (`log`, lalu `smtp 127.0.0.1:1`) dan container `app` di-recreate
  (`podman compose up -d --force-recreate app`) karena compose mem-bake `env_file`
  saat container dibuat. Setelah selesai `.env` dipulihkan dari backup dan
  diverifikasi: `config('mail.default') = smtp`, host `smtp.gmail.com`, queue
  `redis`, worker hidup, `/up` 200.
- `storage/app/e2e` perlu `chmod -R a+rwX` setiap container dibuat ulang
  (entrypoint mengembalikan 775) agar harness Playwright dapat menulis heartbeat.
- Data stress 1 juta baris membuat form Oprec hilang setelah `stress:seed`;
  `php artisan db:seed --class=OprecFormSeeder` (idempotent, aditif) dipakai untuk
  memulihkan `/recruitment`.

## Acceptance criteria

| Kriteria | Status | Bukti |
| --- | --- | --- |
| Log memakai konteks terstruktur tanpa email mentah (hash/mask) | Terpenuhi | `recipient`/`recipients` ter-mask; feature test + E2E failure path |
| `error_message` publik aman | Terpenuhi | `LogRedaction::exception()` di broadcast job; feature test menolak email/kredensial mentah |
| Exception class + correlation ID dicatat | Terpenuhi | `exception_class`, `delivery_key`, `job_id`, `attempt` |
| Test redaction | Terpenuhi | `LogRedactionTest`, `RedactedMailDeliveryExceptionTest`, `EmailLogRedactionTest`, E2E |
| Tidak menghapus data yang dibutuhkan operasional | Terpenuhi | ledger `recipient_email` dipertahankan; pesan diagnostik ter-redaksi tetap ada |
| Exception yang di-rethrow tidak bocor ke log framework/`failed_jobs` | Terpenuhi | wrapper `__toString()` + `report(): bool`; bukti `ok_email\|ok_secret\|ok_raw\|ok_next` dan baris log worker generik |

## Risiko, batasan, dan rollback

- **Pesan transport masih tersimpan (ter-redaksi) di `error_message` dan di
  konteks `error` milik log job.** Detail seperti host/port tetap terlihat oleh
  staf yang berwenang dan operator log. Ini disengaja agar kegagalan dapat
  didiagnosis; PII dan kredensial sudah dihapus. Batas ini dicatat sebagai
  `AUDIT-P2-015` (review surface).
- **Class exception yang tercatat berubah** menjadi
  `App\Exceptions\RedactedMailDeliveryException` dengan public message generik
  (`Mail delivery failed.`). `$tries`/`$backoff` tidak berubah, retry tetap
  berjalan, dan exception asli tetap tersedia lewat `getPrevious()` untuk kode
  serta test. Operator yang melihat `failed_jobs` kehilangan pesan transport asli
  secara sengaja; pesan ter-redaksi tetap ada di `email_logs.error_message` dan
  baris log job.
- **Satu baris `local.ERROR` generik tambahan per kegagalan** dari `report()`
  wrapper, di samping baris terstruktur milik job.
- **Tidak ada perubahan perilaku pengiriman.** Email tetap dikirim; hanya
  representasi log/DB yang berubah.
- **Rollback:** revert commit tiket ini. Tidak ada migration, route, atau
  dependency yang berubah.

## Temuan di luar scope (dicatat, tidak diubah)

| Temuan | Bukti | Tiket |
| --- | --- | --- |
| Kebijakan retensi/redaksi global untuk `failed_jobs` (semua job, bukan hanya email) dan exception non-email yang dilaporkan `report()` | `config/queue.php` driver `database-uuids`; `DatabaseFailedJobProvider.php:59` | `AUDIT-P2-014` (baru) |
| PII plaintext di `recruitment_activity_logs.new_values` (`personal_email`, `student_email`) dan dikembalikan verbatim oleh `RecruitmentActivityLogController` | `ApplicationEditor.php:188`, `RecruitmentQrBulkService.php:85`, `RecruitmentActivityLogController.php:42` | `AUDIT-P2-015` (baru) |
| `BroadcastRecipientController:32` dan `RecruitmentPeriodController:159,215` merender `error_message` apa adanya ke dashboard | Setelah tiket ini nilainya `Class: pesan ter-redaksi`; masih memuat detail transport | Digabung ke `AUDIT-P2-015` (review surface) |
| `bootstrap/app.php` sempat gagal boot selama sesi ini karena perubahan lane lain (`App::isProduction()` di dalam `withMiddleware`) | Transien; diperbaiki oleh lane tersebut; tidak disentuh tiket ini | — (dicatat, bukan tiket) |
| Log mailer `MAIL_MAILER=log` menulis seluruh MIME message (termasuk `To:`) ke `laravel.log` | `vendor/.../LogTransport.php`; hanya dipakai dev/E2E | — (dev-only, dicatat) |
| Mail **sinkron** di jalur request (reset password, test broadcast) melempar exception transport mentah ke handler/Monolog tanpa wrapper | `PasswordResetService.php:16`, `BroadcastTestController.php:27` | `AUDIT-P2-016` (baru) |

## Working tree

Perubahan tiket ini: 5 file kode (`LogRedaction`, `RedactedMailDeliveryException`,
trait, 2 job), 5 file test (2 baru, 3 diperbarui), dokumen laporan, dan changelog.
File milik tiket lain yang sedang berjalan (`bootstrap/app.php`, `config/session.php`,
`config/security.php`, `app/Providers/AppServiceProvider.php`,
`app/Http/Middleware/SecurityHeaders.php`, `app/Support/Security/**`,
`tests/Feature/Security/**`, `tests/Unit/Security/**`, `.env.example`,
`boost.json`) tidak disentuh dan tidak di-stage. Baris indeks di
`docs/developer/sapto/README.md` sudah ditulis di working tree tetapi tidak
ikut di-stage karena file itu juga memuat baris changelog lane `BE-P3-011` yang
belum di-commit.
