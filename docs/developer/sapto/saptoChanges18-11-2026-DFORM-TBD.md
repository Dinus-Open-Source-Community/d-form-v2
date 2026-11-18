# Sapto Changes 18-11-2026 — Halaman Acara Arsip (Restore + Hapus Permanen)

**Issue:** DFORM-TBD
**Commit:** TBD (satu commit `feat:` setelah persetujuan)
**Klasifikasi:** `feature` + `security` (write-block pada event arsip)

## Ringkasan (TL;DR)

Menambah halaman `/admin/events/archived` untuk acara yang sudah diarsipkan (soft delete) dengan dua aksi: **Pulihkan** dan **Hapus permanen**. Halaman hanya menampilkan event `onlyTrashed()` yang boleh dilihat user (super-admin semua, admin lain hanya miliknya / `created_by` NULL), memakai ulang `EventCard`, `EventFilterBar`, `AppPagination`, `EmptyState`, dan modal konfirmasi yang sudah ada. Sidebar "Acara" berubah menjadi grup dengan child "Semua acara" dan "Arsip acara".

Hapus permanen menghapus event beserta seluruh data peserta, file, dan log email dalam satu transaksi, dengan konfirmasi wajib mengetik judul event. Event yang diarsipkan sekarang **read-only**: semua endpoint tulis event-scoped ditolak **HTTP 409** oleh middleware server-side.

Tab bar "Semua acara | Arsip acara" dihapus (navigasi lewat sidebar). Pagination hanya dirender bila `last_page > 1`.

## Perubahan backend

- Route baru: `GET /admin/events/archived` (`dashboard.events.archived`, didaftarkan sebelum resource agar tidak tertangkap `{event}`), `DELETE /admin/events/{event}/force` (`dashboard.events.force-delete`, throttle 5/menit per user).
- `ArchivedEventController` (`index`, `destroy`), `IndexArchivedEventRequest`, `RestoreEventRequest` (`return_to` = `show|index|archived`, default `show`).
- `EventService::paginateArchivedForAdminIndex()`: `onlyTrashed()` + `visibleTo()` + filter kategori/sesi/search + cache `BUSTER_LIST` dengan scope kunci sendiri. Filter kategori/sesi diekstrak ke `applyListFilters()` tanpa mengubah perilaku list aktif.
- `EventService::forceDelete()`: transaksi berurutan `email_logs` → `form_answers` → `form_fields` (`forceDelete`) → `forms` (`forceDelete`) → `event_attendances` → koreksi `event_daily_registrations` per tanggal → `forceDelete` event; pembersihan storage setelah commit (`events/banners`, `form-uploads` di local **dan** public, `form-images`, `form-banners`); `$formIds` di-chunk 500; baris event dikunci `lockForUpdate` agar double-delete tidak men-decrement agregat dua kali.
- `EventPolicy::forceDelete` (super-admin bypass; selain itu `events.delete` + ownership). `update`/`delete` sekarang menolak event `trashed()`.
- Security: middleware `EnsureEventIsNotArchived` (alias `event.not-archived`) — default-deny berbasis nama route + model terikat, memindai seluruh parameter route untuk `Event`, allowlist `index/create/store/show/archived/restore/force-delete/registration-status`; selain itu **409**. Diterapkan di grup event admin, grup `/admin/events/{event}` (export/scan), dan route peserta `forms.fill`/`forms.submitted`. Test registry memastikan setiap route `{event}` di namespace `dashboard.*` memakai middleware atau terdaftar sebagai pengecualian eksplisit.
- `bootstrap/app.php`: 409 ikut jalur error Inertia/HTML. Pesan `messages.event.archived.read_only` + `messages.event.force_delete.*` (en + id).

## Perubahan frontend

- `pages/Dashboard/Events/Archived.vue` — struktur identik dengan Index; tanpa tombol buat acara; empty state khusus arsip.
- `EventCard` varian `archived` (badge "Diarsipkan"; menu hanya Pulihkan + Hapus permanen), varian default tidak berubah.
- Sidebar "Acara" menjadi grup dengan `aria-expanded`/`aria-controls` (termasuk memperbaiki grup Rekrutmen).
- `useEventListQueryFilters` (dipakai Index dan Archived), `useArchivedEventsPage` (restore/force-delete, gate konfirmasi, `dismiss*` untuk menghindari race `AlertDialogAction`), `TypeToConfirmDialog` di `components/core/` sebagai wrapper tipis `ConfirmationModal` (`confirmDisabled`, `inputLabel` netral domain).
- `isSidebarNavActive`: "Semua acara" tidak aktif di `/admin/events/archived`, tetap aktif di detail/edit/form. Breadcrumb `archived` → "Arsip".
- Tab bar dihapus; pagination hanya muncul saat `last_page > 1` di kedua halaman.

## Keputusan dan trade-off

1. **Purge total termasuk `email_logs`** (keputusan PM). Alternatif: audit email dipertahankan dengan `event_id` di-null-kan — ditolak PM.
2. **Agregat harian dikoreksi di transaksi**, bukan dibiarkan basi; `EventReportingQuery::weeklyRegistrationCounts()` memilih agregat selama ada baris di window, sehingga tanpa koreksi KPI menggelembung permanen.
3. **409 Conflict**, bukan 403/404: resource ada dan boleh dilihat untuk di-restore, tetapi aksi tulis konflik dengan status arsip; 404 akan memutus halaman detail arsip yang dibutuhkan untuk restore.
4. **Job antrean tidak dibersihkan** pada tiket ini. Job yang menyerialkan `FormAnswer`/`Event` dan sudah terlanjur di antrean bisa masuk `failed_jobs` setelah purge. Follow-up ticket.
5. **Refactor filter bersama** (`applyListFilters`, `listCacheHash`) menjaga cache key list aktif byte-identik.
6. **Tab bar dihapus** dan **pagination digate** atas permintaan user; navigasi arsip lewat sidebar.

## Bukti test

Dijalankan di container (`podman compose exec -T app ...`) kecuali E2E (host, karena image app tidak memuat browser).

| Perintah | Hasil | Exit |
| --- | --- | --- |
| `php artisan test --filter='ArchivedEventsIndexTest\|EventForceDeleteTest\|EventRestoreRedirectTest\|ArchivedEventWriteBlockTest\|JoinedEventFormFillTest'` | PASS — 55 test, 205 assertion | 0 |
| `php artisan test --filter='EventManagementTest\|EventListFiltersTest\|EventListPaginationTest\|EventObserverCascadeQueryTest\|EventUpdateAuthorizationTest\|EventStatusUpdateTest'` | PASS — 42 test, 229 assertion | 0 |
| `npm run test:unit` | PASS — 105 file, 1092 test | 0 |
| `npm run typecheck` | PASS — bersih | 0 |
| `npm run lint` | PASS — 0 error (1 warning pre-existing `SAMPLE_FIELDS`) | 0 |
| `pint --test` (18 file PHP) | PASS — PSR-12 | 0 |
| `prettier --check` (file fitur) | PASS setelah perbaikan; `resources/js/lib/routes.ts` masih gagal karena hunk lane profile/OAuth yang belum diformat (bukan fitur ini) | 0 / lane lain |
| `npx playwright test archived-events.desktop` | PASS — 6 test | 0 |
| `npx playwright test archived-events.mobile` | PASS — 2 test | 0 |
| `npx tsc -p tsconfig.e2e.json` | PASS — bersih | 0 |

TDD: setiap task backend dimulai dari test merah (route belum ada / 200-302 alih-alih 409), lalu hijau. Bug write-block memiliki test matriks 24 endpoint + test registry yang gagal bila middleware dicabut.

## Bundle

`npm run build` (3996 modul, 14,98 s), diukur dengan `zlib`:

- Entry `app-*.js`: **49,33 kB raw / 15,64 kB gzip** (baseline 17-11-2026: 49,32 / 15,54) — masih jauh di bawah budget 350 kB gzip.
- Chunk terbesar: 451,75 / 142,39 gzip; 326,80 / 97,41 gzip; 307,10 / 78,93 gzip — tidak ada yang melewati 200 kB gzip.
- Chunk page baru `Archived-*.js`: **8,12 kB raw / 3,29 kB gzip**.

Kenaikan entry 0,10 kB gzip berasal dari hook bersama + komponen dialog, masih dalam budget.

## Risiko dan rollback

- Hapus permanen tidak bisa dibatalkan. Mitigasi: hanya untuk event terarsip (409 untuk event aktif), policy owner/super-admin, wajib ketik judul event, throttle 5/menit, transaksi + lock baris.
- Restore tetap merupakan kebalikan soft delete; tidak ada migration baru.
- Rollback kode: revert commit; data yang sudah dipurge tidak dapat dikembalikan (cadangan DB tetap tanggung jawab operasional).

## Follow-up

- Pembersihan job antrean untuk event yang dipurge (`deleteWhenMissingModels` atau sapu payload).
- `dashboard.forms.submission` sengaja tidak memakai 409; 404 dipertahankan agar status arsip tidak bocor ke peserta (dipin oleh test organizer).
- Registry test masih mencocokkan literal `{event}`; perluas ke `Route::signatureParameters()` bila route `{eventId}` ditambahkan.
- Salinan helper lama di `event-cascade-archive.desktop.spec.ts` / `non-superadmin-403.desktop.spec.ts` dapat dimigrasikan ke `support/eventAdmin.ts`.
- Salinan `useDashboardEventShowPage` yang masih menampilkan toast sukses ganda (pre-existing) dapat diseragamkan.
- `Error.vue` belum punya copy khusus 409.
