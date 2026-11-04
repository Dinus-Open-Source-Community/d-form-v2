# Sapto Changes — 04 November 2026 (BE-P1-002)

## Ringkasan

Memperketat OAuth account linking: provider subject ID menjadi identitas utama,
email hanya dipakai bila provider menyatakan email tersebut verified, dan email
sintetis `<nickname>@github.local` dihapus.

Perubahan behavior yang disengaja:

- Akun Google baru dengan `email_verified` false tidak lagi dibuat otomatis.
- Email Google unverified tidak lagi menautkan atau login ke akun lokal.
- GitHub tanpa primary+verified email tidak lagi membuat akun; user diarahkan
  ke login/registrasi email + OTP dengan pesan netral.

| Issue     | Commit    |
| --------- | --------- |
| BE-P1-002 | `f9443de` |

## Perubahan

- `app/Services/Auth/OAuthAccountLinker.php` (baru): resolve identitas OAuth
  dengan subject ID sebagai kunci utama; email hanya untuk akun yang cocok bila
  verified; menolak konflik provider ID dan email akun soft-deleted.
- `app/Http/Controllers/Auth/OAuthController.php`: controller tipis; parsing
  `email_verified` ketat; penolakan memakai toast netral; `catch` logging tetap
  di `BE-P3-007`.
- `resources/js/utils/composables/usePageFlashToast.ts`: tampilkan flash setelah
  `onMounted` agar toast redirect callback OAuth terlihat pada hard load.
- `tests/Feature/Auth/OAuthAccountLinkingTest.php`: 16 regression test Google +
  GitHub (rename dari `OAuthGoogleEmailVerificationTest.php`).
- `tests/e2e/oauth-linking.spec.ts`: 7 E2E test redirect scope dan callback
  anomaly.
- `docs/big-changes/patching-ticket/BE-P1-002.md`: report lengkap dan evidence.

## Verifikasi

- Feature test baru: 16 passed (89 assertions).
- `tests/Feature/Auth`: 103 passed (570 assertions).
- E2E OAuth: 7 passed (8.6 s).
- Pint, `npm run typecheck`, `npm run lint`, `npm run test:e2e:types`, dan
  Prettier: lulus.
- Full backend suite: 861 passed, 7 skipped. Satu test flaky berganti-ganti di
  dua run penuh (`RecruitmentEmailResendTest`, lalu
  `UserEventRegistrationPortalTest`); keduanya lulus saat dijalankan
  terisolasi (10/10 dan 12/12) dan tidak menyentuh OAuth.

Detail command dan bukti ada di report
[`BE-P1-002`](../../big-changes/patching-ticket/BE-P1-002.md).
