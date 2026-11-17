# DFORM-EVENT-EDIT-PUBLISH — Hapus aksi "Simpan & terbitkan" dari edit event

| Issue                                                 | Commit                                                              |
| ----------------------------------------------------- | ------------------------------------------------------------------- |
| DFORM-EVENT-EDIT-PUBLISH (direct request PM; no Jira) | Uncommitted at authoring; final hash recorded in the session report |

Klasifikasi: `refactor` (hapus aksi UI + jalur update yang tidak lagi dipakai).

## Ringkasan (TL;DR)

Tombol **Simpan & terbitkan** di halaman edit event dihapus beserta akarnya: flag `publish`
tidak lagi dikirim dari form edit, `UpdateEventRequest` tidak lagi menerima `publish`, dan
`EventService::update` tidak lagi mengubah status. Menyimpan edit kini selalu mempertahankan
status event; publish/draft hanya lewat aksi di halaman detail (`dashboard.events.status`).
Branch create di komponen tidak diubah. Halaman create saat ini memakai wizard (aksi header tersembunyi), sehingga publikasi event baru dilakukan dari aksi status di halaman detail setelah draft dibuat.

## Kondisi sebelum

| #   | Temuan                                                                                                  | Bukti                                                         |
| --- | ------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------- |
| 1   | Edit menampilkan dua aksi: "Simpan perubahan" dan "Simpan & terbitkan"                                  | `EventDashboardForm.vue` (primary action)                     |
| 2   | Tombol sekunder "Simpan perubahan" mengirim `publish=false`, sehingga event published bisa turun status | `submitForm(false)` + `EventService::update` branch `publish` |
| 3   | `publish` hanya dipakai halaman edit; create memakai aturan sendiri (`StoreEventRequest`)               | grep `publish` di requests/service                            |
| 4   | Status event sudah punya aksi khusus di halaman detail                                                  | `useEventStatusToggle`, `dashboard.events.status`             |

## Perubahan

### Frontend

| File                                                                      | Perubahan                                                                                                                                                                                                                                            |
| ------------------------------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `resources/js/components/modules/dashboard/events/EventDashboardForm.vue` | tombol primary hanya dirender untuk `variant === 'create'`; edit hanya "Simpan perubahan"; `primaryActionLabel` dihapus; `publish` tidak lagi disertakan pada payload edit; toast edit "Perubahan disimpan."; subtitle edit tidak menyebut terbitkan |

### Backend

| File                                       | Perubahan                                                                           |
| ------------------------------------------ | ----------------------------------------------------------------------------------- |
| `app/Http/Requests/UpdateEventRequest.php` | hapus rule `publish` dan pesan `publish.boolean`                                    |
| `app/Services/Event/EventService.php`      | hapus cabang `publish` → `status` di `update()`; status edit tidak berubah otomatis |

`StoreEventRequest` dan `EventService::create` tetap memakai `publish` untuk alur create.
Tidak ada route/controller baru; aksi status tetap `PATCH /admin/events/{event}/status`.

## Test

| Test                                                                      | Hasil                                                              |
| ------------------------------------------------------------------------- | ------------------------------------------------------------------ |
| `tests/Feature/Dashboard/EventUpdateStatusPreservationTest.php` (baru, 3) | 3 passed                                                           |
| `tests/Feature/Dashboard/EventStatusUpdateTest.php` (regresi)             | 7 passed                                                           |
| `tests/e2e/event-edit-save.desktop.spec.ts` (baru, 1)                     | edit tanpa tombol publish, simpan berhasil, status tetap published |
| PHPUnit penuh                                                             | 1635 passed, 12 skipped, 1 gagal pre-existing recruitment EXPLAIN  |
| Build                                                                     | entry 49,20 kB raw / 15,50 kB gzip                                 |

## Verifikasi

```bash
podman exec -i -w /app d_form_app php artisan test \
  tests/Feature/Dashboard/EventUpdateStatusPreservationTest.php \
  tests/Feature/Dashboard/EventStatusUpdateTest.php
E2E_BASE_URL=http://localhost:3000 E2E_ARTISAN='podman exec -i -w /app d_form_app php artisan' \
E2E_ADMIN_EMAIL=admin@gmail.com E2E_ADMIN_PASSWORD='admin password' \
npx playwright test tests/e2e/event-edit-save.desktop.spec.ts --project=chromium-desktop
```

## Checklist

- [x] Tombol "Simpan & terbitkan" hilang dari halaman edit.
- [x] Tidak ada `publish` pada payload edit, rule update, atau cabang service update.
- [x] Simpan edit tidak mengubah status published/draft.
- [x] Alur create (wizard) tidak diubah; publish event baru lewat aksi status di detail.
- [x] Feature test + E2E ditambahkan; lint/typecheck/prettier bersih.
- [ ] Commit.
