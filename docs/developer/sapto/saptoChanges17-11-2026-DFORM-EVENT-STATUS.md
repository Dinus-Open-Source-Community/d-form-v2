# DFORM-EVENT-STATUS — Publish / draft dari kartu Manage event

| Issue                                           | Commit                                                              |
| ----------------------------------------------- | ------------------------------------------------------------------- |
| DFORM-EVENT-STATUS (direct request PM; no Jira) | Uncommitted at authoring; final hash recorded in the session report |

Klasifikasi: `feature` (aksi status event) + `refactor` UI (kartu Lifecycle dilebur).

## Ringkasan (TL;DR)

Kartu **Lifecycle** di rail detail event dihapus; isinya (Archive/Restore + penjelasan)
pindah ke kartu **Manage event**. Kartu yang sama kini punya aksi status kontekstual:
`Publish event` saat draft, `Move to draft` saat published, dengan modal konfirmasi.
Publish memakai endpoint khusus `PATCH /admin/events/{event}/status` dan ditolak bila
field wajib event belum lengkap. Draft selalu diizinkan.

## Perilaku

- Draft → Publish: tombol primary; konfirmasi; event tampil publik sesuai window registrasi.
- Published → Draft: tombol outline; konfirmasi; event disembunyikan dari publik, data pendaftar tetap.
- Event diarsipkan (`deleted_at` non-null): aksi status disembunyikan, hanya Restore.
- Satu primary per state: saat draft, `Edit details` menjadi outline agar `Publish event` menonjol.
- Sukses memakai flash toast server (tanpa toast ganda di client); gagal validasi `status`
  diteruskan ke toast form.

## Backend

| File                                                        | Perubahan                                                                                              |
| ----------------------------------------------------------- | ------------------------------------------------------------------------------------------------------ |
| `routes/web/admin/event.php`                                | `PATCH /events/{event}/status` → `dashboard.events.status` (middleware `auth`, `organizer`)            |
| `app/Http/Requests/UpdateEventStatusRequest.php`            | baru; `status` wajib, `Rule::enum(EventStatus::class)`                                                 |
| `app/Http/Controllers/Dashboard/Events/EventController.php` | `updateStatus()`: authorize policy `update`, panggil service, flash toast, redirect ke show            |
| `app/Services/Event/EventService.php`                       | `updateStatus()` + `PUBLISH_REQUIRED_FIELDS`; publish melempar `ValidationException` bila field kosong |
| `lang/{en,id}/messages.php`                                 | pesan sukses publish/draft dan pesan data belum lengkap                                                |

Field wajib publish: `title`, `location`, `description`, `registration_start`,
`registration_end`, `start_date`, `end_date`, `quota`, `session`, `category`.
Cache list event diinvalidasi otomatis oleh `EventObserver` karena `status` berubah.

## Frontend

| File                                                               | Perubahan                                                                                                                 |
| ------------------------------------------------------------------ | ------------------------------------------------------------------------------------------------------------------------- |
| `resources/js/hooks/useEventStatusToggle.ts`                       | baru; state target status, modal, `isSubmitting`, `router.patch`, error toast                                             |
| `resources/js/components/modules/dashboard/EventShowAsideRail.vue` | kartu Lifecycle dihapus; Edit details → Sep → status action → Sep → Archive/Restore + penjelasan; modal konfirmasi status |

Refinement spacing: separator dihapus, semua aksi memakai satu kelas (lebar penuh, min-height 44px, padding 12px); ikon per-baris form 32px dengan gap 4px dan aria-hidden, `CardContent gap-2` + `mt-2` sebelum Archive (16px) untuk memisahkan aksi destruktif tanpa garis; tanpa kalimat tambahan di bawah.

Catatan implementasi: `cancel()` tidak mengosongkan `targetStatus` karena dialog juga
tertutup saat tombol konfirmasi diklik; event close bisa berjalan sebelum `confirm()`
sehingga request tidak terkirim. Target dibersihkan setelah sukses.

## Test

| Test                                                               | Hasil                                         |
| ------------------------------------------------------------------ | --------------------------------------------- |
| `tests/Feature/Dashboard/EventStatusUpdateTest.php` (baru, 7 test) | **7 passed** (19 assertions)                  |
| `tests/e2e/event-status-toggle.desktop.spec.ts` (baru)             | **1 passed** (publish→draft→publish + reload) |
| Vitest seluruh suite                                               | **987 passed** (90 file)                      |
| `npm run lint`                                                     | 0 error, 1 warning pre-existing               |
| `npm run typecheck`                                                | pass                                          |
| Pint file yang diubah                                              | PASS                                          |
| Build                                                              | pass; entry 49,20 kB raw / 15,51 kB gzip      |

## Verifikasi

```bash
podman exec -i -w /app d_form_app php artisan test tests/Feature/Dashboard/EventStatusUpdateTest.php
E2E_BASE_URL=http://localhost:3000 E2E_ARTISAN='podman exec -i -w /app d_form_app php artisan' \
E2E_ADMIN_EMAIL=admin@gmail.com E2E_ADMIN_PASSWORD='admin password' \
npx playwright test tests/e2e/event-status-toggle.desktop.spec.ts --project=chromium-desktop
```

## Checklist

- [x] Publish/draft berfungsi dari detail event tanpa membuka form edit.
- [x] Konfirmasi sebelum perubahan status.
- [x] Publish ditolak bila data wajib belum lengkap; draft selalu boleh.
- [x] Otorisasi policy `update` + middleware organizer; member/guest ditolak.
- [x] Lifecycle menyatu ke Manage event; tidak ada kartu kosong/duplikat.
- [x] Feature test + E2E hijau; lint/typecheck/vitest/build hijau.
- [ ] Commit (digabung dengan tiket removal karena menyentuh file yang sama).
