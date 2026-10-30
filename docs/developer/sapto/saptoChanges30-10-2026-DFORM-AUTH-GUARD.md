# Sapto Changes — 30 Oktober 2026 (DFORM-AUTH-GUARD)

## Ringkasan

Route root `/auth` sekarang melewati guard `guest` dan tidak lagi mengarahkan user yang sudah login ke `/auth/login`. Sesi aktif langsung diarahkan ke `/`, sehingga browser tidak mendapat jalur redirect tambahan menuju halaman auth.

| Issue | Commit |
| ----- | ------ |
| Auth root redirect guard | `fix(auth): guard root auth redirect for logged users` |

## Perubahan

- `routes/web/auth.php`: `/auth` memakai `PreventAuthPageCaching` dan middleware `guest`, konsisten dengan route login/register.
- User terautentikasi yang meminta `/auth` menerima redirect langsung ke `/`.
- Respons redirect tetap diberi `Cache-Control` no-store agar halaman auth tidak dipulihkan dari cache browser.
- Regression test backend dan E2E ditambahkan untuk memastikan user aktif tidak dapat membuka root auth.

## Verifikasi

- `php artisan test tests/Feature/Auth/AuthPageAccessTest.php`: 4 passed, 26 assertions.
- `pint --test routes/web/auth.php tests/Feature/Auth/AuthPageAccessTest.php`: PASS.
- `npm run test:e2e:types`: PASS.
- E2E browser belum dapat dijalankan karena `E2E_BASE_URL=http://localhost:8000` tidak aktif pada environment ini.
