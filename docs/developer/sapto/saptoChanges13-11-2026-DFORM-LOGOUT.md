# DFORM-LOGOUT — Logout authenticated user

| Issue | Commit |
| --- | --- |
| DFORM-LOGOUT | Uncommitted |

## Perubahan

- Memperbaiki logout dashboard yang gagal sebelum request dikirim karena `inject()` dipanggil dari event handler.
- `DashboardTopbar` kini memakai context topbar yang sudah di-resolve saat setup, lalu mengirim `POST /auth/logout`.
- Menambahkan regression E2E untuk request POST, redirect, dan akses dashboard setelah logout.
- Menambahkan feature test backend untuk invalidasi sesi dan redirect ke home.

## Bukti

- Sebelum fix: klik tombol menghasilkan warning Vue `inject() can only be used inside setup()` dan tidak ada request `/auth/logout`.
- Sesudah fix: E2E logout desktop lulus; feature auth suite lulus 109 test; unit suite lulus 982 test; typecheck, E2E typecheck, lint, dan build container lulus.

## Keputusan

- Tidak mengubah route/controller/logout session behavior karena kontrak backend sudah benar dan feature test mengonfirmasinya.
- Tidak menambah adapter baru; bug berada pada penggunaan context Vue di event handler.
