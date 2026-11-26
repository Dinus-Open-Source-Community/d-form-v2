# DFORM-TBD — Verifikasi email via OTP + PIN sebagai langkah kedua saat login

| Field | Isi |
| --- | --- |
| Issue | DFORM-TBD (nomor menyusul dari Jira) |
| Klasifikasi | `feature` (auth: verifikasi email + faktor kedua) |
| Tanggal | 19-11-2026 |
| Commit | belum dibuat (menunggu review pemilik produk) |
| Spec | `docs/superpowers/specs/2026-11-18-email-verification-and-pin-2fa-design.md` |
| Plan | `docs/superpowers/plans/2026-11-18-email-verification-and-pin-2fa.md` |

## Ringkasan

Akun yang email-nya belum terverifikasi (legacy, buatan admin, atau setelah ganti email)
kini bisa memverifikasi lewat OTP yang dikirim ke email terdaftar, dengan banner
non-blocking. Pengguna juga bisa mengaktifkan PIN 6 angka yang diminta pada setiap login,
termasuk login Google/GitHub, dengan UI yang sama seperti OTP.

## Keputusan dan alasan

1. **Banner, bukan gate keras.** Akun buatan admin dan interviewer recruitment lahir tanpa
   `email_verified_at`; gate keras akan mengunci mereka sampai verifikasi. Gate disiapkan
   sebagai config dan default-nya mati.
2. **Tabel terpisah per alur.** `email_verification_codes` (tantangan per user) dan
   `user_pin_credentials` (kredensial PIN) tidak mencampur state rahasia ke `users` dan
   tidak menduplikasi PII ke `pending_registrations`.
3. **Kode OTP disimpan HMAC-SHA256, PIN disimpan bcrypt.** OTP cukup untuk jendela 5 menit
   dan dibandingkan waktu-konstan; PIN adalah memorized secret sehingga memakai KDF lambat.
4. **Kode dibuat di dalam job, bukan di request.** Hash HMAC satu arah, dan menaruh kode di
   payload queue atau menyimpannya terbaca sama-sama dilarang. Konsekuensinya `expires_at`
   ditulis lebih dulu di `issue()` dan hash menyusul; `status()` melaporkan `expiresAt`
   sejak awal agar kotak kode langsung tampil.
5. **Middleware, bukan logout paksa.** `EnsureTwoFactorVerified` menahan route `auth` sampai
   penanda sesi ada; pola ini menjaga `url.intended` dan menyatukan jalur password + OAuth.
6. **Recovery lewat OTP lalu wajib PIN baru.** OTP saja tidak meloloskan faktor kedua.
7. **Re-auth untuk perubahan faktor.** Aktivasi butuh kata sandi (bila ada), ubah/nonaktif
   butuh PIN saat ini.
8. **PIN 6 angka dengan lockout.** 5 percobaan lalu lock 15 menit, throttle per user,
   sesi dirotasi setelah lolos. Dinyatakan jujur di kode dan dokumen: ini langkah kedua
   berasuransi rendah, bukan MFA kuat (NIST/OWASP).

## Perubahan

Backend
- Migration: `2026_11_18_000010_create_email_verification_codes_table`,
  `2026_11_18_000011_create_user_pin_credentials_table`,
  `2026_11_18_000012_add_purpose_to_email_verification_codes_table`.
- Service: `EmailVerificationService` (issue/verify/status, purpose
  `email_verification`/`two_factor_recovery`, retry pengiriman macet 15 detik),
  `TwoFactorPinService` (enable/change/disable/verify/status, lockout 5).
- `app/Support/Otp/OtpCode.php` (generate/hash/matches), `app/Enums/TwoFactorVerifyResult`.
- Job + mail: `SendEmailVerificationOtpJob`, `EmailVerificationOtpMail` (copy berbeda untuk
  verifikasi vs pemulihan PIN), dua view mail.
- Middleware: `EnsureTwoFactorVerified` (hanya route ber-`auth`, allowlist `two-factor.*`,
  `auth.logout`, `verification.*`).
- Provider: subquery `has_pin_2fa` di `retrieveById`/`retrieveByToken`; kolom rahasia tidak
  masuk `AUTH_SESSION_COLUMNS`.
- Route: `/email/verify{,/send,/confirm}`, `/auth/two-factor{,/recovery,/recovery/confirm,/recovery/pin}`,
  `/profile/two-factor` (POST/PUT/DELETE).
- Rate limit: `verify_email_send` 5/10 menit, `verify_email_confirm` 10/menit,
  `two_factor_verify` 10/menit, `two_factor_recovery` 5/10 menit (per user).
- Command lokal/testing: `email:verification-code {email}`.

Frontend
- Halaman: `Auth/VerifyEmail`, `Auth/TwoFactorChallenge`, `Auth/TwoFactorRecovery`.
- Komponen: `core/EmailVerificationBanner` (dismiss per sesi),
  `modules/profile/ProfilePinCard` (satu baris: teks + switch).
- `OtpCodeInput` + `useOtpCodeInput` dipindah ke `components/core/` agar dipakai lintas
  modul (auth dan profil).
- Hook: `useEmailVerification`, `useTwoFactorChallenge`, `useTwoFactorSettings`.

## Perbaikan yang ditemukan saat pengujian

1. **Dialog PIN tidak pernah mengirim request.** `AlertDialogAction` (Reka) menutup dialog
   sinkron sebelum event `confirm` sampai, sehingga `submit()` melihat `mode === null`.
   Diperbaiki dengan menunda penutupan satu tick dan membiarkan hasil aksi yang menutup.
2. **Status PIN tidak dikirim server.** `/profile` tidak menyertakan prop `two_factor`;
   switch selalu tampak mati setelah reload.
3. **State basi.** "Sisa percobaan" di halaman tantangan dan countdown verifikasi email
   memakai snapshot props; keduanya kini mengikuti props terbaru.
4. **Ordering kirim/status.** Setelah `POST /email/verify/send`, halaman dirender sebelum
   job menulis hash sehingga `expiresAt` null dan kotak kode tidak muncul. Bookkeeping kini
   ditulis di `issue()`; `verify()` sebelum kode mendarat mengembalikan `Missing` tanpa
   menghabiskan percobaan; pengiriman macet >15 detik boleh dikirim ulang.

## Waiver

- **Aturan yang dilanggar:** `hooks/` disebut satu-satunya rumah logic stateful.
- **Alasan:** `OtpCodeInput` kini dipakai dua modul (auth dan profil), sedangkan guard
  repo melarang `components/core/` mengimpor `hooks/`. Satu-satunya jalan tanpa
  melemahkan guard adalah menempatkan composable bersama komponennya.
- **Risiko:** logic stateful berada di `core/`, bukan `hooks/`; pencarian logic jadi kurang
  seragam.
- **Alternatif yang ditolak:** membuat komponen stateless (menulis ulang fokus/paste/backspace
  di lima pemanggil), dan melonggarkan guard (dilarang).
- **Owner:** agent (sesi ini). **Review ulang:** saat `core/` berikutnya disentuh.

## Verifikasi

| Cek | Hasil |
| --- | --- |
| PHPUnit penuh | **1862 passed, 12 skipped, 1 failed** (gagal: `RecruitmentApplicationListQueryTest` EXPLAIN MySQL, pre-existing/environmental di SQLite) |
| PHPUnit Auth | 199 passed |
| PHPUnit TwoFactor + Profile | 67 passed |
| Vitest | **108 file / 1126 test passed** |
| Playwright verifikasi email | **6 passed** |
| Playwright 2FA (desktop + mobile) | **7 passed** |
| Playwright profil (desktop/mobile/breakpoints/connections) | 20 passed (sebelumnya) |
| Pint | 864 file PASS |
| Typecheck / ESLint / Prettier | exit 0 |
| Build | exit 0 |

Bundle: entry `app-*.js` **15,97 kB gzip**, `Profile` **31,42 kB / 9,39 kB gzip**,
`VerifyEmail` **7,47 kB / 3,08 kB gzip**; tidak ada chunk page >200 kB gzip.

## Batasan dan risiko residual

- **PIN bukan MFA kuat.** NIST/OWASP: password + PIN "does not constitute MFA". Fitur ini
  adalah langkah kedua berasuransi rendah; TOTP/WebAuthn adalah jalur yang benar bila
  asuransi lebih tinggi dibutuhkan.
- **Recovery memindahkan kepercayaan ke email.** Siapa pun yang menguasai email + kata sandi
  bisa mereset PIN lalu masuk. Ini konsekuensi recovery berbasis email, bukan cacat
  implementasi; alternatifnya recovery codes.
- Migration belum dieksekusi di MySQL (suite SQLite); runbook sudah mencatat konsekuensi
  rollback kolom `purpose`.
- Playwright dijalankan di host (image container tanpa lib Chromium).
- Browser zoom 200% tidak dapat diemulasikan; yang diuji lebar CSS efektif + skala teks.
- **Follow-up terpisah:** `ConfirmationModal` memakai `AlertDialogAction` yang selalu
  menutup sendiri; dialog yang harus tetap terbuka saat gagal perlu workaround. Memperbaiki
  perilaku dasarnya menyentuh dialog lain.
- Insiden singkat: PIN tidak sengaja aktif di akun seeded saat pengujian; baris
  `user_pin_credentials` dihapus dan `has_pin_2fa` diverifikasi `false` untuk
  `admin@gmail.com` dan `superadmin@gmail.com`.

## Rollback

- `php artisan migrate:rollback --step=3` melepas kolom `purpose` lalu kedua tabel. Tantangan
  verifikasi (TTL 5 menit) dan kredensial PIN hilang; pengguna perlu mengaktifkan PIN ulang.
- Menghapus middleware dari grup `web` mengembalikan perilaku login lama tanpa migrasi data.
