# DFORM-DASHBOARD-CALENDAR — Remove admin dashboard calendars

| Issue | Commit |
| --- | --- |
| DFORM-DASHBOARD-CALENDAR | Uncommitted |

## Perubahan

- Menghapus `MiniCalendar` dan `EventCalendar` dari dashboard admin `/dashboard`.
- Recent Events sekarang mengisi lebar penuh pada section `Aktivitas terbaru`.
- `EventCalendar` tetap dipertahankan untuk dashboard peserta.
- Menambahkan assertion E2E bahwa heading dan kontrol kalender tidak dirender di dashboard admin.

## Keputusan

- Komponen shared tidak dihapus karena `Dashboard/User/Index.vue` masih menggunakannya.
- Backend dan props dashboard tidak diubah karena kalender hanya dipakai sebagai presentasi frontend.

## Verifikasi

- Regression E2E: 2 passed (setup + dashboard assertion).
- Typecheck, E2E typecheck, dan build container berhasil.
- Lint berhasil tanpa error; satu warning unused variable pada test autosave adalah baseline yang sudah ada.
