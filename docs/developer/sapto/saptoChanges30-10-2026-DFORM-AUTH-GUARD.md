# Sapto Changes — 30 Oktober 2026 (DFORM-AUTH-GUARD)

## Ringkasan

Route root `/auth` sekarang melewati guard `guest` dan tidak lagi mengarahkan user yang sudah login ke `/auth/login`. Sesi aktif langsung diarahkan ke `/`. Login sukses mengganti entry auth dengan `replace: true`, dan snapshot history/BFCache divalidasi ulang sebelum halaman auth ditampilkan untuk sesi aktif.

| Issue | Commit |
| ----- | ------ |
| Auth root redirect guard | `fix(auth): guard root auth redirect for logged users` |

## Perubahan

- `routes/web/auth.php`: `/auth` memakai `PreventAuthPageCaching` dan middleware `guest`, konsisten dengan route login/register.
- User terautentikasi yang meminta `/auth` menerima redirect langsung ke `/`.
- Respons redirect tetap diberi `Cache-Control` no-store agar halaman auth tidak dipulihkan dari cache browser.
- Login Inertia memakai `replace: true`, sehingga entry `/auth/login` tidak tersisa setelah login sukses.
- `useAuthHistoryGuard` memvalidasi ulang route auth pada event Inertia `navigate`, browser `popstate`, dan `pageshow.persisted`, lalu mengganti URL ke `/` bila sesi aktif terdeteksi.
- Regression test backend dan E2E ditambahkan untuk memastikan user aktif tidak dapat membuka root auth atau memulihkan snapshot auth lewat tombol Back.

## Verifikasi

- `php artisan test tests/Feature/Auth/AuthPageAccessTest.php`: 4 passed, 26 assertions.
- `pint --test routes/web/auth.php tests/Feature/Auth/AuthPageAccessTest.php`: PASS.
- `npm run test:e2e:types`: PASS.
- E2E browser belum dapat dijalankan karena `E2E_BASE_URL=http://localhost:8000` tidak aktif pada environment ini.

## Dasar keputusan

`no-store` saja tidak menghapus entry history yang sudah ada dan BFCache dapat memulihkan snapshot tanpa request baru. Guard `pageshow`/`navigate` dan penggantian entry login dipilih sesuai rekomendasi platform browser dan opsi history Inertia.
