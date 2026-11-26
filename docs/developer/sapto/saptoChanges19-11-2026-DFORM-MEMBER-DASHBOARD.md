# Sapto Changes — 19 November 2026 (DFORM-MEMBER-DASHBOARD)

## Ringkasan

Menghapus varian dashboard peserta (`/dashboard` untuk non-organizer) sampai ke
akar dan menstabilkan CI GitHub Actions pada penyebabnya. `/dashboard` kini
hanya milik penyelenggara (`events.list`); user lain menerima **404 tanpa
redirect**. Landing pasca-autentikasi dipusatkan di satu helper role-aware
(`/joined` untuk peserta). Seluruh jalur CI (Pint, ESLint, Prettier, PHPUnit,
Vitest, typecheck, build, SSR, bundle budget, audit dependency) diverifikasi
ulang di working tree.

| Issue | Commit |
| ----- | ------ |
| Dashboard peserta tidak dipakai lagi; CI quality gate merah | _(belum di-commit)_ |

## Perubahan — backend

- `routes/web/admin/index.php`: route `/dashboard` tetap `auth`, tetapi
  `abort_if` 404 bila user tidak punya `events.list`.
- `app/Http/Controllers/Dashboard/User/MemberDashboardController.php` dihapus.
- `app/Support/PostAuthDestination.php` (baru): satu pintu tujuan pasca-auth
  (organizer → `dashboard`, lainnya → `dashboard.user.events`).
- 5 controller auth (`VerifyOtp`, `EmailVerification`, `TwoFactorChallenge`,
  `Login`, `OAuth`) dan 4 middleware (`EnsureOrganizerDashboardAccess`,
  `EnsureBroadcastAccess`, `EnsureRecruitmentAccess`, `EnsureMemberPortalAccess`)
  memakai helper tersebut, sehingga tidak ada lagi peserta yang dilempar ke 404
  setelah login.

## Perubahan — frontend

- Dihapus: `pages/Dashboard/User/Index.vue`, `EventCalendar.vue`,
  `MiniCalendar.vue`, `MemberDashboardSkeleton.vue`, `views/welcome.blade.php`
  (file tanpa route, dead code).
- `member-dashboard` dihapus dari `skeletonRegistry`, `TSkeletonKey`, dan
  `constants/inertiaSkeletons`; aturan `/dashboard` tersisa hanya untuk
  `canManageEvents`.
- Sidebar: item "Beranda" hanya dirender untuk organizer. Peserta tidak melihat
  item itu (bukan dialihkan ke `/joined`); grup menu utama disembunyikan saat
  kosong.
- Logo sidebar, tombol back topbar, dan tautan "Dasbor" di landing Navbar/Footer
  memakai `resolveDashboardHomeHref`/`resolveNavbarFallbackBackHref` yang sadar
  role, sehingga tidak ada tautan mati.

## Perubahan — test

- `DashboardRolesTest`: dua test dashboard peserta diganti satu test 404.
- `PostAuthDestinationTest` (baru): login member → `/joined`, admin → `/dashboard`.
- Assertion redirect peserta di Auth, Recruitment, Dashboard, Events, Forms
  diselaraskan (hanya target redirect; tidak ada test dihapus/dilemahkan).
- E2E: helper login memvalidasi sesi lewat `/profile` (netral role), spec
  `navigation-skeleton-member` ditulis ulang, spec baru
  `member-dashboard-404.desktop.spec.ts` (404 tanpa pindah URL), dan target
  redirect `/dashboard` → `/joined` pada `otp-verification`,
  `email-verification`, `two-factor`, `pii-log-redaction`,
  `event-registrations-export`, `recruitment-qr-eligibility`.

## Akar masalah CI dan perbaikannya

| Gejala (run 44471749018) | Akar masalah | Perbaikan |
| --- | --- | --- |
| 241× `Vite manifest not found` | Test merender blade `@vite` tanpa artefak build | `withoutVite()` di `tests/TestCase.php` |
| 577× `cannot start a transaction within a transaction` | Test queue Redis gagal, `tearDown` melempar sebelum rollback RefreshDatabase | Queue test after-commit pindah ke driver `database` + `try/finally` |
| 10× `Cannot access private constant DIRECTORY_PREFIX` | Konstanta private dipakai test purge storage | Konstanta dijadikan `public` |
| 3× `Route [dashboard.broadcasts.*] not defined` | Flag fitur di-set lewat `$_SERVER`/`putenv` per-class, rapuh terhadap boot | `FEATURE_BROADCAST=true` di `phpunit.xml`, hack per-class dihapus |
| Prettier 6 file + 1 warning ESLint | Format drift + konstanta test tak terpakai | `prettier --write` (termasuk 18 file yang sudah gagal di HEAD) + hapus `SAMPLE_FIELDS` |
| Audit: `laravel/framework <12.71.0`, `phpseclib <4.0.3` | Advisory keamanan | `composer update` rantai dependensi; lockfile ikut |

## Keputusan dan alternatif yang ditolak (Gate 2)

- **404, bukan redirect.** Route tetap `auth` dan memakai `abort_if(..., 404)`,
  bukan middleware `organizer` (middleware menghasilkan 302).
- **Helper terpusat** menggantikan 9 salinan `route('dashboard')`; ditolak
  menduplikasi ternary role di tiap call site.
- **Queue `database`** untuk test after-commit: ditolak menambah service Redis
  di CI karena properti yang diuji (`afterCommit`) bersifat framework-level dan
  sudah dibuktikan 4/4 lulus dengan driver `database` (tanpa service eksternal).
- **`/profile` di helper E2E**: ditolak mempertahankan `/dashboard` karena
  helper itu dipakai juga untuk akun peserta.
- **Tidak** memakai `continue-on-error`, `skip`, atau penurunan gate.

## Verifikasi

- `./vendor/bin/pint --test`: PASS, 865 file.
- `npm run lint`: PASS tanpa temuan. `prettier --check`: PASS.
- `php artisan test` (env CI-like, `DB_DATABASE` kosong): lihat hasil akhir di
  ringkasan sesi; kegagalan flaky `UserEventRegistrationPortalTest` (apostrof
  faker vs escape Blade) diperbaiki dengan membandingkan `e($member->name)`.
- `npm run test:unit`: 1210 passed, 1 failed — **bukan dari perubahan ini**:
  `AppStatePanel.test.ts` gagal karena working tree milik sesi lain menghapus
  kredit Storyset; `HEAD` masih memuat kredit tersebut.
- `npm run typecheck:unit`, `npm run typecheck`, `npm run build`,
  `npm run build:ssr`, `node scripts/build/check-bundle-budget.mjs`: PASS
  (entry 15,8 kB gzip; chunk terbesar 142,2 kB; 265 chunk ≤ 200 kB).
- `composer validate --strict`: PASS. `composer audit --locked`: PASS
  ("No security vulnerability advisories found") saat jaringan container
  tersedia; percobaan ulang gagal karena DNS (`repo.packagist.org`).
- `npm audit --omit=dev --audit-level=critical`: belum terbukti hijau karena DNS
  container (`EAI_AGAIN registry.npmjs.org`); harus divalidasi di runner.
- E2E (8 spec terdampak, `chromium-desktop`): `navigation-skeleton-member`,
  `member-dashboard-404`, `two-factor` (5/5), `pii-log-redaction` (2 passed,
  2 skipped), `otp-verification`, `email-verification`,
  `event-registrations-export`, `recruitment-qr-eligibility`.
  `pii-log-redaction` butuh fixture `stress-user-1@stress.test`; user fixture
  dibuat aditif (tidak menjalankan `stress:seed` yang destruktif).

## Catatan / waiver

- Gate unit lokal merah karena `resources/js/components/core/AppStatePanel.vue`
  (perubahan tak-ter-commit sesi lain) menghapus atribusi Storyset, sementara
  aset yang dipakai tetap Storyset (`public/illustrations/state-*.svg`, commit
  `55d87894`). Atas keputusan user, file itu tidak disentuh; commit `HEAD`
  tetap memiliki atribusi sehingga CI pada kode ter-commit tidak terpengaruh.
- `docs/prd.md` dan `docs/external-redeem-api.md` adalah file untracked milik
  sesi lain; tidak diubah.

## Checklist

- [x] Perilaku: peserta 404 di `/dashboard`, admin tetap `Dashboard/Index`.
- [x] Tidak ada referensi `MemberDashboard`/`Dashboard/User/Index`/`member-dashboard`.
- [x] Test PHP terdampak hijau; E2E terdampak hijau.
- [x] Pint, ESLint, Prettier, typecheck, build, SSR, bundle budget hijau.
- [x] Dependency advisory ditutup lewat upgrade.
- [ ] `npm audit` diverifikasi di runner berjaringan.
- [ ] Gate unit lokal menunggu sesi lain menyelesaikan `AppStatePanel.vue`.
