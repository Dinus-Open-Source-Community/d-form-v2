# DFORM-BE-P3-009 — Cookie secure dan security header

| Field | Nilai |
| --- | --- |
| Issue | `BE-P3-009` (`docs/big-changes/patching.md`) |
| Tanggal | 08-11-2026 |
| Klasifikasi | security |
| Status | In Review |
| Commit | `dab4eab` |
| Report | [`docs/big-changes/patching-ticket/BE-P3-009.md`](../../big-changes/patching-ticket/BE-P3-009.md) |

## Ringkasan (TL;DR)

Cookie sesi sekarang HTTPS-only secara default di production dan salah
konfigurasi eksplisit menggagalkan boot, bukan login diam-diam rusak. Seluruh
response membawa CSP, HSTS, `X-Frame-Options: SAMEORIGIN`,
`X-Content-Type-Options: nosniff`, `Referrer-Policy`, dan `Permissions-Policy`.

## Perubahan

| File | Perubahan |
| --- | --- |
| `config/security.php` (baru) | Konfigurasi header, HSTS, dan CSP (mode `enforce`/`report-only`/`off`). |
| `app/Http/Middleware/SecurityHeaders.php` (baru) | Middleware global header keamanan. |
| `app/Support/Security/SessionCookiePolicy.php` (baru) | Guard production untuk `session.secure` dan `session.same_site`. |
| `bootstrap/app.php` | Registrasi middleware global. |
| `config/session.php` | Default `secure` per environment, nilai kosong tidak mematikan keamanan. |
| `app/Providers/AppServiceProvider.php` | Guard dijalankan pada `boot()`. |
| `.env.example` | Dokumentasi `SESSION_SECURE_COOKIE`, `SESSION_SAME_SITE`, `SECURITY_*`. |

## Test

| Command | Hasil |
| --- | --- |
| `php artisan test tests/Feature/Security/SecurityHeadersTest.php tests/Unit/Security` | 77 passed (203 assertions) |
| `php artisan test` (full suite) | 1018 passed, 7 skipped, 0 failed (6099 assertions) |
| `./vendor/bin/pint --test` (file tersentuh) | PASS |
| `npx playwright test tests/e2e/security-headers.desktop.spec.ts --project=chromium-desktop` | 9 passed (29,3 s) |
| `npx tsc -p tsconfig.e2e.json` | exit 0 |
| `npm run typecheck` | exit 0 |

## Trade-off yang ditolak

- `X-Frame-Options: DENY` — mematahkan preview PDF/CV same-origin; dipakai
  `SAMEORIGIN` + `frame-ancestors 'self'`.
- HSTS bergantung `isSecure()` — tidak pernah aktif karena `TrustProxies` belum
  diset; gap itu dicatat sebagai tiket `BE-P3-011`.
- Mewajibkan `SESSION_SECURE_COOKIE` diisi env — menggantungkan keamanan pada
  file yang tidak tracked.

## Catatan operasional

Production dengan `SESSION_SECURE_COOKIE=false` akan gagal saat boot, termasuk
saat entrypoint menjalankan `migrate --force`. Ini perilaku yang diinginkan;
perbaiki env, bukan menghapus guard.
