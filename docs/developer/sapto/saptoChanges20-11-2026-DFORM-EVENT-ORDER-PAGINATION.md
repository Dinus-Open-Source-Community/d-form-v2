# DFORM — Event list pagination and newest-first ordering

| Issue | Commit |
| --- | --- |
| DFORM-EVENT-ORDER-PAGINATION | Pending |

## Summary

`/browse` memakai paginasi bernomor seperti daftar admin: 8 baris per halaman, pilihan `per_page`, dan ringkasan "1-8 dari N". Urutan daftar acara diseragamkan menjadi acara yang paling baru dibuat di paling atas untuk `/browse`, `/joined`, dan default `/admin/events`.

## Perubahan

| File | Perubahan |
| --- | --- |
| `EventService::paginateForPublicBrowse` | Cursor pagination diganti `LengthAwarePaginator`; `orderByDesc('created_at')` lalu `id`; `PUBLIC_BROWSE_PER_PAGE` menjadi 8. |
| `UserBrowseEventsController` | Validasi `per_page` (1..100) dan `page`; payload `pagination` menjadi `currentPage`, `lastPage`, `total`, `perPage`. |
| `UserJoinedEventsController` | Urutan `created_at desc` lalu `id desc`; batas `USER_JOINED_LIMIT` tetap. |
| `EventService::paginateForAdminIndex` | Default sort `created_at desc` (sebelumnya `title asc`). |
| `IndexEventRequest` | Default sort di `prepareForValidation` menjadi `created_at desc`; `created_at` ditambahkan ke whitelist `sort.by`. |
| `pages/Dashboard/User/Events.vue` | Paginasi cursor diganti `AppPagination` bernomor dengan `goToPage` dan `changePerPage`; hanya tampil saat `lastPage > 1`. |

Urutan eksplisit tetap dihormati: `?sort[by]=title&sort[order]=asc` pada `/admin/events` masih mengembalikan urutan judul.

Halaman publik `/events` tidak berubah; paginasi simple di sana tetap memakai kontrak lama.

## Verifikasi

| Check | Command | Hasil |
| --- | --- | --- |
| PHP terfokus | `php artisan test tests/Feature/Dashboard/BrowsePaginationTest.php tests/Feature/Dashboard/EventListOrderTest.php tests/Feature/Dashboard/User/JoinedEventsPayloadTest.php tests/Feature/Dashboard/EventListPerPageTest.php tests/Feature/Dashboard/EventListFiltersTest.php` | 15 passed, 95 assertions |
| PHP area terdampak | `php artisan test tests/Feature/Dashboard tests/Feature/EventListPaginationTest.php tests/Feature/EventManagementTest.php` | 257 passed, 1529 assertions |
| Vitest | `podman exec d_form_app npx vitest run` | 118 file, 1225 test passed |
| Typecheck | `npm run typecheck` | exit 0 |
| ESLint | `npx eslint` pada file tersentuh | exit 0 |
| Pint | `pint --test` 7 file | PASS PSR 12 |

Bukti browser:

- `/browse` sebagai member: kartu pertama "Demo Rich Text: Semua Format" (acara terbaru dibuat), kontrol `8 per halaman`, ringkasan `1-8 dari 20`, dan tombol halaman `1 2 3` dengan prev/next.
- `/admin/events` sebagai super-admin: kartu pertama juga acara terbaru dibuat, paginasi bernomor tampil.

Test yang diperbarui:

- `BrowsePaginationTest`: dua test baru (`paginasi bernomor` dan `per_page`), assertion lama `hasMore` dihapus.
- `EventListOrderTest`: baru, menguji default `created_at desc` dan sort eksplisit.
- `JoinedEventsPayloadTest`: `events.0` sekarang acara yang terakhir dibuat.
- `EventManagementTest`: assertion props `/browse` mengikuti kontrak paginasi baru.

## Catatan

- Data lokal `/browse` memuat sisa fixture E2E (`e2e-joined-detail-*`) karena cleanup sebelumnya gagal FK; ini data uji, bukan perilaku halaman.
