# DFORM-USERS-REDESIGN: direktori pengguna, panel inline, pagination, detail baru

| Issue                                                                 | Commit                                                              |
| --------------------------------------------------------------------- | ------------------------------------------------------------------- |
| Direct request PM (redesign `/admin/users` + detail; no Jira ticket)   | Uncommitted at authoring; final hash recorded in the session report |

Klasifikasi: `feature` (rework UI direktori + alur create/edit inline) dengan satu
perbaikan `bug` (konfirmasi hapus tidak pernah mengirim DELETE).

## Ringkasan (TL;DR)

`/admin/users` dirombak menjadi satu surface direktori: toolbar dengan pencarian
ber-debounce dan chip role, tabel pada ≥1024px dan kartu pada <1024px, pagination yang
benar-benar berfungsi (`per_page` 10/20/50/100, default 20), serta panel samping untuk
create/edit sehingga superadmin tidak lagi berpindah halaman. Halaman `/create` dan
`/{user}/edit` dihapus dan menjawab 404 sesuai keputusan user. Halaman detail
di-redesign dengan header akun, empat kartu statistik dari server, dan tiga seksi data.
Otorisasi aksi per baris kini datang dari server (`can_edit`/`can_delete`), bukan
dihitung ulang di klien.

## Kondisi sebelum

| #   | Temuan                                                                                                  | Bukti                                                               |
| --- | ------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------- |
| 1   | Pencarian mengirim request pada setiap ketikan (tanpa debounce)                                          | `Users/Index.vue:92` sebelum perubahan                              |
| 2   | `per_page` dikirim klien tetapi diabaikan service (selalu 20)                                            | `UserManagementService.php:45` sebelum perubahan                    |
| 3   | Markup form create/edit duplikat hampir seluruhnya                                                       | `Users/Create.vue:67-108` vs `Users/Edit.vue:80-116`                |
| 4   | Aksi baris berupa ikon 36px tanpa label (target sentuh < 44px)                                            | `Index.vue:229-261`, `button/index.ts` (`icon-sm` = `size-9`)       |
| 5   | Otorisasi edit/hapus dihitung ulang di klien, berbeda dari halaman detail yang memakai `permissions`       | `Index.vue:104-118` vs `toDetailPayload()`                          |
| 6   | Tabel 4 kolom dengan sel `whitespace-nowrap` memaksa scroll horizontal di 360–768px                       | `Table/TableCell.vue`, screenshot sebelum redesign                  |
| 7   | Konfirmasi hapus tidak pernah mengirim DELETE: `@update:open(false)` menjalankan `cancelDelete()` lebih dulu | Reproduced di e2e (0 request DELETE), sebab di `useUserDeletion`     |
| 8   | Label role hardcoded di halaman detail, terpisah dari `roleOptions` server                                | `Users/Show.vue:112-118` sebelum perubahan                          |

## Perubahan

### Backend

| File                                                  | Perubahan                                                                                                                                                                                       |
| ----------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `app/Services/User/UserManagementService.php`          | `ALLOWED_PER_PAGE = [10,20,50,100]` + `DEFAULT_PER_PAGE = 20`; `paginateForAdminIndex()` memakai whitelist dan `max(1, page)`; `toInertiaArray()` diganti `toInertiaRow()` yang menambah `avatar_url`, `can_edit`, `can_delete` dari `Gate::forUser`; `roleOptions()` menambah `description` per role |
| `app/Http/Controllers/Dashboard/Users/UserManagementController.php` | method `create()` dan `edit()` dihapus; `index` mengirim baris direktori + `query.{search,role,per_page,edit,create}`; `show` menambah `roleOptions`; `store`/`update` mengembalikan filter asal lewat `returnFilters()` (whitelist search/role/page) |
| `app/Http/Requests/Users/IndexUserRequest.php`         | default `per_page` 20; rule `edit` (uuid) dan `create` (boolean); `max:100` dihapus karena whitelist ditegakkan service                                                                |
| `app/Http/Requests/Users/StoreUserRequest.php`         | rule `return_filters.{search,role,page}`                                                                                                                                                          |
| `app/Http/Requests/Users/UpdateUserRequest.php`        | rule `return_filters.{search,role,page}`                                                                                                                                                          |
| `routes/web/admin/users.php`                           | resource `->only(['index','store','show','update','destroy'])`                                                                                                                                   |

### Frontend

| File                                                                              | Perubahan                                                                                                                                        |
| --------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------ |
| `types/user-management.ts`                                                        | `IManagedUser`, `IUserRoleOption`, `IUsersPaginator`, `IUserIndexQuery`, `IUserAccountForm`, `TUserAccountFormMode`                              |
| `constants/userManagement.ts`                                                     | `USER_INDEX_PER_PAGE_OPTIONS`, `USER_PASSWORD_MIN_LENGTH`                                                                                        |
| `lib/buildUserIndexUrl.ts`                                                        | URL direktori dari filter; memakai `routes.admin.users.index` (bukan literal path)                                                               |
| `lib/userAccountFormValues.ts`                                                    | form kosong default `member` (hak paling kecil), form edit mempertahankan role yang ada, akun tanpa role dibiarkan kosong                         |
| `lib/userAccountFormValidation.ts`                                                | validasi wajib/min-8/konfirmasi/email dengan pesan identik Form Request server                                                                    |
| `lib/userRoleLabel.ts`                                                            | label role dari server + label `Super Admin` untuk role sistem                                                                                   |
| `adapters/browser/historyQuery.ts`                                                | `replaceHistoryQuery` untuk sinkron URL panel tanpa request tambahan                                                                             |
| `hooks/useUserDirectoryFilters.ts`                                                | debounce 350ms (satu request per jeda ketik), chip role langsung, `isLoading`, `resetFilters`                                                     |
| `hooks/useUserAccountPanel.ts`                                                    | satu form Inertia untuk create/edit, deep link `?create`/`?edit`, `return_filters`, validasi klien sebelum request                                |
| `hooks/useUserDeletion.ts`                                                        | target + status dialog terpisah; `dismissDelete()` tidak menghapus target sehingga konfirmasi tetap berjalan; error membuka dialog lagi            |
| `components/modules/users/UserDirectoryToolbar.vue`                               | pencarian berlabel, tombol bersihkan 44px, chip role `aria-pressed` (44px di mobile), live region hasil                                            |
| `components/modules/users/UserDirectoryTable.vue`                                 | tabel semantik ≥1024px: Akun (avatar + nama + email), Role, Bergabung (≥1280px), Aksi; sel `whitespace-normal`                                    |
| `components/modules/users/UserDirectoryCard.vue`                                  | kartu <1024px (1 kolom <640px, 2 kolom 640–1023px)                                                                                                |
| `components/modules/users/UserRowActions.vue`                                     | satu logika aksi, dua layout; tombol berlabel, 44px di kartu                                                                                      |
| `components/modules/users/UserAccountPanel.vue`                                   | sheet kanan (full-screen di mobile), field wajib bertanda bintang + legenda, helper+error `aria-describedby`, fokus ke field invalid pertama, footer sticky |
| `components/modules/users/UserAccountRoleCards.vue`                               | `fieldset`/`legend` + 4 kartu radio dengan deskripsi konsekuensi role                                                                             |
| `components/modules/users/UserDirectoryEmptyState.vue`                            | empty state ringkas tanpa ilustrasi 180px (tinggi konten efektif ±654px)                                                                          |
| `components/modules/users/UserDetailHeader.vue`                                   | header akun: avatar, nama, email, badge role + verifikasi email, umur akun, aksi Edit/Hapus                                                       |
| `components/modules/users/UserDetailStatCards.vue`                                | empat kartu statistik dari `stats` server, tanpa angka karangan                                                                                    |
| `components/modules/users/UserDetailRegistrationList.vue`                         | pendaftaran event: tabel ≥1024px, kartu <1024px, label status dipertahankan                                                                       |
| `components/modules/users/UserDetailRecruitmentList.vue`                          | lamaran OpRec: tabel ≥1024px, kartu <1024px, label tahap/hasil dipertahankan                                                                        |
| `pages/Dashboard/Users/Index.vue`                                                 | komposer: layout, `Head`, hook, props → komponen; full width tanpa `max-w`                                                                        |
| `pages/Dashboard/Users/Show.vue`                                                  | komposer detail; Edit → `/admin/users?edit=<id>`, hapus memakai hook bersama, tanpa back link ganda                                               |
| `pages/Dashboard/Users/Create.vue`, `Edit.vue`                                    | dihapus (URL 404, keputusan user)                                                                                                                  |
| `lib/routes.ts`                                                                   | entri `admin.users.create` dan `admin.users.edit` dihapus                                                                                         |

| `resources/css/app.css`                                                           | rule base `cursor: pointer`/`not-allowed` untuk kontrol (preflight Tailwind mengembalikan `default`), dan blok `prefers-reduced-motion` yang menghentikan animasi sheet/dialog/skeleton |

### Test

| File                                                                       | Isi                                                                                          |
| -------------------------------------------------------------------------- | -------------------------------------------------------------------------------------------- |
| `tests/Feature/Dashboard/UserManagementDirectoryTest.php` (baru, 8)        | per_page whitelist, avatar+permissions per baris, `?edit`/`?create`, route lama 404, `return_filters` store/update, `roleOptions` di detail |
| `tests/Feature/UserManagementTest.php`                                     | satu assertion: GET `/{user}/edit` kini 404 (PUT/DELETE 403 tetap diuji)                      |
| `lib/__tests__/{buildUserIndexUrl,userAccountFormValues,userAccountFormValidation,userRoleLabel}.test.ts` (baru, 22) | URL builder, mapper form, validasi wajib/min-8/konfirmasi, label role         |
| `hooks/__tests__/{useUserDirectoryFilters,useUserAccountPanel,useUserDeletion}.test.ts` (baru, 20) | debounce satu request, panel create/edit/deep link, race dialog-close vs confirm |
| `tests/e2e/admin-users-directory.desktop.spec.ts` (baru, 9)                | render, edit tanpa navigasi, simpan + filter bertahan, create, validasi klien, debounce, chip role, pagination, hapus, 404 route lama |
| `tests/e2e/admin-users-directory.mobile.spec.ts` (baru, 3)                 | kartu, tanpa overflow, target 44px, panel full width, Escape + fokus kembali                  |
| `tests/e2e/admin-users-breakpoints.desktop.spec.ts` (baru, 1)              | loop 360/768/1024/1280/1920: overflow, tabel vs kartu, label aksi, kolom Bergabung            |
| `tests/e2e/admin-users-detail.desktop.spec.ts` (baru, 4)                   | 4 kartu statistik, edit membuka panel, hapus sesuai `can_delete`, tanpa overflow              |

## Review keputusan

| Keputusan | Alasan | Alternatif yang ditolak |
| --- | --- | --- |
| Sheet kanan untuk create/edit | Form 5 field + 4 kartu role butuh tinggi; daftar tetap terlihat | Modal tengah (scroll internal), baris mengembang (tidak muat di 1024px), tetap halaman terpisah (bertentangan dengan permintaan) |
| Satu surface direktori | Menghemat ±90px vertikal dari tiga kartu bertumpuk; satu hierarki | Kartu filter + kartu tabel + pagination terpisah |
| Tabel ≥1024px, kartu di bawahnya | Lebar konten <1024px bukan ruang yang cukup untuk 4 kolom + aksi | Tabel dengan scroll horizontal (kondisi lama) |
| Tombol aksi berlabel | Pengguna non-teknis tidak perlu menebak ikon | Ikon saja (kondisi lama), menu titik-tiga |
| Kartu role radio, bukan dropdown | Konsekuensi tiap role terbaca sebelum dipilih | `SimpleSelect` role (jargon tanpa konteks) |
| Chip role, bukan select | Lima pilihan tetap lebih cepat dipindai | Dropdown filter role |
| `per_page` ditegakkan server | Klien tidak menentukan batas query | Mempercayai nilai dari URL |
| `permissions` dari server | Policy satu-satunya sumber aturan akses | Duplikasi logika `isSuperAdminTarget`/`isSelf` di klien |
| Route lama 404 | Satu UI kanonik (keputusan user) | Redirect ke panel |
| `return_filters` | Simpan tidak membuang pencarian/halaman pengguna | Redirect polos tanpa query |
| Validasi wajib di klien | Bintang wajib tanpa penegakan membuat form terasa rusak; menghindari 422 untuk field kosong | Hanya validasi server |
| Skeleton baris saat filter | Mempertahankan posisi dan tinggi baris | Spinner/Lottie |
| Empty state ringkas | Ilustrasi 180px memakan hampir sepertiga tinggi layar | `EmptyState` Lottie |

## Test & verifikasi

| Pemeriksaan | Hasil |
| --- | --- |
| `php artisan test tests/Feature/UserManagementTest.php tests/Feature/Dashboard/UserManagementDirectoryTest.php` | **25 passed** (151 assertions) |
| `npm run test:unit` | **99 file, 1040 test passed** |
| `npm run typecheck` | exit 0 |
| `npm run test:e2e:types` | exit 0 |
| `npx eslint` (semua file baru/ubah + 4 spec baru) | exit 0 |
| `pint --test` (30 file terkait) | PASS |
| `playwright test` 4 spec baru | **18 passed** (setup 1, direktori desktop 9, breakpoints 1, detail 4, mobile 3) |
| `npm run build` (container) | sukses, 13,38 s |

Catatan: `pint --test` repo-wide masih menandai 4 file lama tidak terkait
(`UserEventPortalController.php`, `User.php`, `KeysetMergeIterator.php`, `routes/ai.php`);
tidak disentuh karena di luar scope.

### Build

| Artefak | Sebelum (CONTEXT §11) | Sesudah |
| --- | --- | --- |
| Entry `app-*.js` | 49,32 kB raw / 15,54 kB gzip | **49,98 kB raw / 15,86 kB gzip** |
| Chunk terbesar `index-*.js` | 451,51 kB raw / 142,17 kB gzip | 462,60 kB raw / 145,66 kB gzip |
| `Dashboard/Users/Index` chunk | (page lama, satu chunk dengan tabel) | **26 kB raw / 8 kB gzip** |
| `Dashboard/Users/Show` chunk | tidak ada (halaman lama satu chunk) | **23 kB raw / 6 kB gzip** |

Entry masih di bawah budget 350 kB gzip; tidak ada page chunk > 200 kB gzip. Kenaikan
chunk bersama berasal dari perubahan ticket lain yang sedang berjalan di working tree
(form-answer mobile), bukan dari halaman pengguna.

## Verifikasi manual yang dilakukan

1. Login super-admin, buka `/admin/users` pada 360, 390, 768, 1024, 1280, 1440, 1920 px:
   `scrollWidth <= clientWidth` pada `document.scrollingElement` dan `dashboard-content`.
2. Panel create: tanda bintang + legenda tampil; submit kosong menampilkan 4 pesan,
   fokus pindah ke Nama lengkap, dan **0 request** POST terkirim.
3. Klik Edit pada baris: URL berubah ke `?edit=<id>` tanpa memuat halaman baru; simpan
   memperbarui baris dan mempertahankan `search`.
4. Hapus akun: dialog konfirmasi mengirim satu DELETE, toast "Akun berhasil dihapus.",
   dan baris hilang (bug lama diperbaiki).
5. Halaman detail: empat kartu statistik, Edit membuka panel di direktori, tombol Hapus
   hanya muncul saat `can_delete`, tanpa overflow di 360/1440 px.
6. Kontras diukur dari warna komputasi (oklch -> linear sRGB): teks muted 7,12:1,
   nama akun 18,8:1, tombol primary 5,42:1, chip role idle 7,12:1. Semua di atas 4,5:1.
7. `cursor` terukur: tombol `pointer`, link `pointer`, kontrol disabled `not-allowed`.
8. `prefers-reduced-motion: reduce` (emulasi Playwright): `animation-duration` sheet
   menjadi 1e-05s dan panel tetap terbuka penuh, jadi gerak berhenti tanpa merusak fungsi.

## Risiko dan rollback

- Tanpa migration dan tanpa perubahan data. Rollback = revert commit.
- Route `create`/`edit` 404 bersifat sengaja; tautan lama di luar aplikasi akan 404.
- `can_edit`/`can_delete` dihitung per baris lewat Gate tanpa query tambahan (policy
  in-memory), 20 baris per halaman.
- Changelog ini tidak menutup tiket Jira; status In Review diserahkan ke manusia.
