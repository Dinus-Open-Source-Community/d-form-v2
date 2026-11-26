# DFORM-REGISTRANTS-REMOVAL — Hapus halaman daftar pendaftar event

| Issue                                                  | Commit                                                              |
| ------------------------------------------------------ | ------------------------------------------------------------------- |
| DFORM-REGISTRANTS-REMOVAL (direct request PM; no Jira) | Uncommitted at authoring; final hash recorded in the session report |

Klasifikasi: `refactor` + `migration` (penghapusan permukaan duplikat, tanpa perubahan data).

## Ringkasan (TL;DR)

Route `GET /admin/events/{event}/registrants`, controller, page Inertia, hook, empat komponen,
satu lib UI, dua tipe, dua export badge, satu aset Lottie, dua entry point navigasi, satu adapter
route, satu label breadcrumb, satu section dokumentasi UI, dua benchmark stress, dan seluruh test
yang menunjuk halaman itu dihapus. Data `form_answers` tidak disentuh dan tidak ada migration:
roster per form tetap dilayani tab **Jawaban** di detail form. URL lama kembali 404 tanpa redirect.

## Kondisi sebelum

| #   | Temuan                                                                | Bukti                                                                          |
| --- | --------------------------------------------------------------------- | ------------------------------------------------------------------------------ |
| 1   | Halaman roster lintas-form adalah permukaan kedua dari data yang sama | `EventRegistrantsController.php` vs tab Jawaban `FormController`               |
| 2   | Route hanya dipakai halaman itu dan dua tombol navigasi               | `routes/web/admin/index.php`, `EventShowAsideRail.vue`, `RecentEventsCard.vue` |
| 3   | Komponen/hook/lib/tipe/aset hanya melayani halaman ini                | `rg "registrant"` pada `resources/js`                                          |
| 4   | Dua benchmark stress mengukur query halaman ini                       | `QueryBenchmarkCommand::eventBenchmarks()`                                     |
| 5   | Tidak ada objek database khusus halaman ini                           | `rg -i "registrant" database/` = nol hasil                                     |

## Perubahan per lapisan

### Route dan backend

| File                                                                   | Perubahan                                                                                                            |
| ---------------------------------------------------------------------- | -------------------------------------------------------------------------------------------------------------------- |
| `app/Http/Controllers/Dashboard/Events/EventRegistrantsController.php` | dihapus                                                                                                              |
| `routes/web/admin/index.php`                                           | hapus import `EventRegistrantsController` dan `Route::get('/registrants')->name('registrants')`                      |
| `app/Console/Commands/Stress/QueryBenchmarkCommand.php`                | hapus benchmark `event_registrant_roster` dan `event_registrant_roster_count`; `events_list_published` dipertahankan |

Tanpa migration: tidak ada tabel, kolom, index, seeder, atau permission khusus halaman ini.
`EventCache::BUSTER_REGISTRANTS`, `totalRegistrants`, dan `whereListedForOrganizerParticipantRoster`
sengaja dipertahankan karena dipakai dashboard KPI, dataset broadcast, dan tab Jawaban.

### Frontend — dihapus

| File                                                                     | Alasan                               |
| ------------------------------------------------------------------------ | ------------------------------------ |
| `resources/js/pages/Dashboard/Events/Registrants.vue`                    | page route yang dihapus              |
| `resources/js/hooks/useEventRegistrantsPage.ts`                          | hanya dipakai page tersebut          |
| `resources/js/components/modules/dashboard/RegistrantsDataTable.vue`     | hanya dipakai page tersebut          |
| `resources/js/components/modules/dashboard/RegistrantsStatCards.vue`     | hanya dipakai page tersebut          |
| `resources/js/components/modules/dashboard/RegistrantsToolbar.vue`       | hanya dipakai page tersebut          |
| `resources/js/components/modules/dashboard/RegistrantsPendingBanner.vue` | hanya dipakai page tersebut          |
| `resources/js/lib/registrantsUi.ts`                                      | hanya dipakai page/komponen tersebut |

### Frontend — disesuaikan

| File                                                               | Perubahan                                                                                                                                                  |
| ------------------------------------------------------------------ | ---------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `resources/js/types/event.d.ts`                                    | hapus import `TRegistrantStatus` dan `interface IRegistrant`                                                                                               |
| `resources/js/types/registrationStatus.ts`                         | hapus komentar + `TRegistrantStatus`                                                                                                                       |
| `resources/js/lib/registrationStatusUi.ts`                         | hapus import `TRegistrantStatus`, `REGISTRANT_STATUS_BADGES`, `registrantStatusBadge`; badge review/member/group dan `registrationRoleLabel` dipertahankan |
| `resources/js/components/modules/dashboard/EventShowAsideRail.vue` | hapus tombol "Manage registrants" + import ikon `Users`; tombol Edit dan Laporan dipertahankan                                                             |
| `resources/js/components/modules/dashboard/RecentEventsCard.vue`   | hapus aksi "Pendaftar" (`data-testid="recent-event-action-registrants"`) + import `UsersRound`; aksi `Kelola` dipertahankan                                |
| `resources/js/lib/routes.ts`                                       | hapus import `registrants as eventRegistrants` dan getter `registrants:`                                                                                   |
| `resources/js/lib/breadcrumbs.ts`                                  | hapus label `registrants: 'Peserta'`                                                                                                                       |
| `resources/js/pages/Docs.vue`                                      | hapus item nav `registrants` dan `<section id="registrants">` beserta `<hr>` pemisahnya; import `Layers` tetap dipakai section lain                        |
| `CONTEXT.md`                                                       | jumlah file `pages/` 58 → 57 (57 → 56 page route Inertia) dan baseline bundle diperbarui ke hasil ukur commit ini                                          |

**Recent Events mobile:** setelah aksi "Pendaftar" dihapus, baris hanya punya satu aksi. `Kelola`
memakai `ACTION_BUTTON_CLASS` yang memang berisi `flex-1` di mobile sehingga tombol mengisi lebar
baris (perilaku dipertahankan secara sengaja, bukan sisa layout dua tombol); `gap-2` pada container
aksi dihapus karena dengan satu anak tidak lagi menghasilkan jarak apa pun sehingga hanya dead
styling, sementara `flex items-center md:shrink-0` dipertahankan.

### Aset

| File                                      | Perubahan                                                                                                         |
| ----------------------------------------- | ----------------------------------------------------------------------------------------------------------------- |
| `public/lotties/registrants-waiting.json` | dihapus                                                                                                           |
| `resources/js/lib/lotties.ts`             | hapus entri `registrantsWaiting`                                                                                  |
| `resources/js/types/lottie.ts`            | hapus `'registrantsWaiting'` dari union `TLottieName`                                                             |
| `public/lotties/README.md`                | bullet aset dihapus, diganti satu baris bahwa registry `resources/js/lib/lotties.ts` adalah sumber daftar animasi |

### Test

| File                                                      | Perubahan                                                                                                                                                                                                                                                                        |
| --------------------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `tests/Feature/Forms/FormDraftTest.php`                   | assertion halaman registrants dihapus, method menjadi `test_draft_is_hidden_from_the_registrations_export`; pemeriksaan export tetap                                                                                                                                             |
| `tests/Feature/Forms/FormRegistrationTest.php`            | hapus `test_admin_can_view_event_registrants_page` dan helper `registrantsPath`                                                                                                                                                                                                  |
| `resources/js/lib/__tests__/registrationStatusUi.test.ts` | hapus import/fixture/test registrant; test review/member/group/role dipertahankan                                                                                                                                                                                                |
| `resources/js/lib/__tests__/routes.test.ts`               | hapus `'admin.events.registrants'` dari `SURVIVING_ROUTE_KEYS`                                                                                                                                                                                                                   |
| `resources/js/lib/__tests__/breadcrumbs.test.ts`          | kasus path non-formulir memakai `/admin/events/{id}/laporan` dengan pageTitle `Laporan`                                                                                                                                                                                          |
| `tests/e2e/dashboard-recent-events.desktop.spec.ts`       | fokus aksi pindah ke `Kelola` (klik → detail event via regex pathname), konstanta registrants dihapus; handler `console` diekstrak ke `onConsole`, `consoleErrors` di-assert sebelum klik lalu `page.off('console', onConsole)` agar assertion tidak ikut menilai halaman tujuan |
| `tests/e2e/dashboard-recent-events.mobile.spec.ts`        | alias `ACTION_SELECTOR` dan loop satu elemen dihapus; per baris di-assert `Kelola` terlihat, bounding box ≥ 44×44, tidak ada overlap judul/kuota, dan tidak ada overflow horizontal                                                                                              |

Wayfinder diregenerasi (`php artisan wayfinder:generate`); `resources/js/routes`,
`resources/js/actions`, dan `resources/js/wayfinder` gitignored sehingga tidak ikut commit.

## Keputusan dan alternatif yang ditolak

- **Hapus kedua entry point navigasi.** Alternatif "pertahankan tombol lalu arahkan ke detail
  event" ditolak karena menghasilkan dua tombol dengan tujuan sama pada baris Recent Events
  (duplikat affordance), dan keputusan ini diambil eksplisit oleh user.
- **URL lama 404, tanpa redirect.** Alternatif redirect 301 ke detail form ditolak: permintaan
  eksplisit "hapus bersih tak bersisa", dan detail form wajib dipilih dulu sebelum tab Jawaban.
- **Tanpa migration.** Tidak ada objek database milik halaman ini; drop apa pun akan menghapus
  data yang justru dipertahankan.
- **Benchmark query dihapus, bukan dipindah.** Query roster per form tetap terwakili benchmark
  `form_submissions_page`, sehingga dua entri khusus halaman ini hanya menambah waktu stress run.
- **Spec E2E dipertahankan sebagai regression anatomi baris.** Menghapus spec akan menghilangkan
  penjaga overflow/touch target pada baris Recent Events yang masih dipakai dashboard.

## Bukti verifikasi

Semua perintah PHP/npm dijalankan di container (`podman exec -w /app d_form_app ...`) kecuali
Playwright yang memakai browser host.

| Gate              | Perintah                                                                                                                                                                                       | Exit | Hasil                                                                                                                |
| ----------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ---- | -------------------------------------------------------------------------------------------------------------------- |
| Route lama hilang | `php artisan route:list --path=registrants`                                                                                                                                                    | 0    | tidak ada baris route ("doesn't have any routes matching the given criteria")                                        |
| Wayfinder         | `php artisan wayfinder:generate`                                                                                                                                                               | 0    | actions + routes digenerasi; `rg -n "registrant" resources/js/routes resources/js/actions` = nol hasil               |
| Pint (scope diff) | `./vendor/bin/pint --test app/Console/Commands/Stress/QueryBenchmarkCommand.php routes/web/admin/index.php tests/Feature/Forms/FormDraftTest.php tests/Feature/Forms/FormRegistrationTest.php` | 0    | PASS 4 files                                                                                                         |
| Pint (penuh)      | `./vendor/bin/pint --test app routes tests`                                                                                                                                                    | 1    | **failed (pre-existing)**, lihat catatan di bawah                                                                    |
| PHPUnit terdampak | `php artisan test tests/Feature/Forms/FormDraftTest.php tests/Feature/Forms/FormRegistrationTest.php`                                                                                          | 0    | 121 passed (872 assertions)                                                                                          |
| PHPUnit penuh     | `php artisan test`                                                                                                                                                                             | 1    | 1 failed, 13 skipped, 1648 passed (10856 assertions)                                                                 |
| Typecheck         | `npm run typecheck`                                                                                                                                                                            | 0    | bersih                                                                                                               |
| Lint              | `npm run lint`                                                                                                                                                                                 | 0    | 0 error, 1 warning pre-existing (`resources/js/lib/__tests__/formBuilderAutosave.test.ts:27` `SAMPLE_FIELDS` unused) |
| Vitest penuh      | `npm run test:unit`                                                                                                                                                                            | 0    | 90 files, 987 tests passed                                                                                           |
| Vitest terdampak  | `npx vitest run routes/breadcrumbs/registrationStatusUi`                                                                                                                                       | 0    | 39 tests passed                                                                                                      |
| Build             | `npm run build`                                                                                                                                                                                | 0    | built in 14.25s                                                                                                      |
| Prettier (tiket)  | `npx prettier --check` pada 4 file yang diperbaiki di commit ini (lihat di bawah)                                                                                                              | 0    | semua file tiket lolos                                                                                               |
| Prettier (CI)     | `npx prettier --check "resources/js/**/*.{ts,vue,js}" "tests/e2e/**/*.ts" "docs/**/*.md" "public/lotties/README.md"`                                                                           | 1    | **failed (pre-existing)**, 96 file di luar diff tiket; daftar di bawah                                               |

### Bundle sebelum vs sesudah (`public/build/assets`, gzip)

Satu metode pengukuran untuk kedua sisi, dijalankan di container. Ukuran dihitung dengan `zlib`
dan dibagi 1024 (KiB), sehingga angka berbeda tipis dari output Vite yang memakai kB desimal.

Prosedur: tree bersih di commit ini → `git checkout --detach HEAD^` → `php artisan wayfinder:generate`
→ `npm run build` → ukur (**sebelum**) → `git checkout dev-zapp` → terapkan semua perbaikan →
`php artisan wayfinder:generate` → `npm run build` → ukur (**sesudah**) dengan perintah yang sama.

```bash
podman exec -w /app d_form_app node -e "const fs=require('fs'),z=require('zlib'),p='public/build/assets';const rows=fs.readdirSync(p).filter(f=>f.endsWith('.js')).map(f=>{const b=fs.readFileSync(p+'/'+f);return [f,b.length,z.gzipSync(b).length]}).sort((a,b)=>b[2]-a[2]);rows.slice(0,6).forEach(r=>console.log(r[0],(r[1]/1024).toFixed(2)+' kB',(r[2]/1024).toFixed(2)+' kB gzip'))"
```

Entry `app-*.js` dibaca dari baris `app-` pada keluaran yang sama (chunk itu tidak selalu masuk
enam teratas).

| Slot              | Sebelum (`HEAD^`, build ulang)                 | Sesudah (commit ini)                           |
| ----------------- | ---------------------------------------------- | ---------------------------------------------- |
| Entry `app-*.js`  | `app-DPSI_TF6.js` 50.00 kB / 15.67 kB gzip     | `app-CTHegGH0.js` 49.47 kB / 15.54 kB gzip     |
| Chunk terbesar #1 | `index-CgX2Sfgr.js` 451.51 kB / 142.17 kB gzip | `index-D6-5q852.js` 451.51 kB / 142.17 kB gzip |
| Chunk terbesar #2 | `index-DVGUZA9i.js` 326.80 kB / 97.07 kB gzip  | `index-DVGUZA9i.js` 326.80 kB / 97.07 kB gzip  |
| Chunk terbesar #3 | `vue3-lottie.es` 307.10 kB / 79.09 kB gzip     | `vue3-lottie.es` 307.10 kB / 79.10 kB gzip     |
| Page registrants  | `Registrants-Dj14hpqK.js` 19.99 kB / 6.54 kB   | tidak ada (chunk hilang)                       |

Budget: entry 15.54 kB gzip (≤ 350 kB) dan tidak ada chunk page > 200 kB gzip (maksimum
142.17 kB gzip, tidak berubah dari baseline).

### E2E

Perintah (host, Playwright + browser host):

```bash
E2E_BASE_URL=http://localhost:3000 \
E2E_ARTISAN="podman exec -i -w /app d_form_app php artisan" \
E2E_ADMIN_EMAIL=<seeded super-admin> \
E2E_ADMIN_PASSWORD=<password> \
npx playwright test tests/e2e/dashboard-recent-events.desktop.spec.ts tests/e2e/dashboard-recent-events.mobile.spec.ts
```

Hasil: **3 passed** (setup login admin + desktop + mobile), exit 0.

Penyimpangan environment yang dicatat, bukan kegagalan aplikasi:

1. Kredensial contoh di plan (`stress-user-1@stress.test`) tidak ada di DB dev saat verifikasi;
   akun yang dipakai adalah super-admin yang **sudah ada** di DB dev tersebut — bukan akun yang
   dibuat oleh seeder repo pada run ini. Password tidak dicatat di dokumen ini.
2. `E2E_ARTISAN` wajib diisi eksplisit (`tests/e2e/support/environment.ts` menolak nilai kosong),
   nilainya `podman exec -i -w /app d_form_app php artisan`.
3. `storage/app/e2e/heartbeats` dimiliki UID container sehingga host tidak bisa `mkdir`; izin
   sementara `chmod o+w` dilakukan lewat container untuk satu run, lalu dikembalikan ke `775`.
   Tidak ada file repo yang berubah karena langkah ini (`storage/*` gitignored).

### Kegagalan pre-existing (tidak diperbaiki di tiket ini)

- `./vendor/bin/pint --test app routes tests` → **failed (pre-existing)**, 4 file di luar diff tiket:
  `app/Http/Controllers/Dashboard/User/UserEventPortalController.php` (`no_unused_imports`),
  `app/Models/User.php` (`blank_line_after_namespace`),
  `app/Support/Export/KeysetMergeIterator.php` (`statement_indentation`),
  `routes/ai.php` (`no_unused_imports`, `single_blank_line_at_eof`).
- `php artisan test` → `RecruitmentApplicationListQueryTest::test_list_query_uses_composite_index_and_backward_scan_on_mysql`
  gagal di baris 384 karena index `rec_apps_period_submitted_idx` tidak ada di DB dev
  (sudah dicatat sebagai kegagalan pre-existing di `saptoChanges17-11-2026-DFORM-DASHBOARD-CHART-REMOVAL.md`).
- `npm run lint` → 1 warning `@typescript-eslint/no-unused-vars` pada
  `resources/js/lib/__tests__/formBuilderAutosave.test.ts` (file tidak disentuh tiket ini).

### Prettier: file tiket vs kegagalan pre-existing

Empat file yang sempat gagal Prettier karena commit ini sudah diperbaiki dan lolos
`npx prettier --check`:

- `public/lotties/README.md`
- `resources/js/components/modules/dashboard/RecentEventsCard.vue`
- `resources/js/lib/__tests__/registrationStatusUi.test.ts`
- `docs/developer/sapto/saptoChanges17-11-2026-DFORM-REGISTRANTS-REMOVAL.md`

Pemeriksaan CI-equivalent tetap gagal pada **96 file** yang seluruhnya berada di luar diff commit
ini (nol irisan dengan `git show --name-only HEAD`). Daftar persis hasil pengukuran:

```text
docs/developer/nafan/bundle-guest-registration-production-deploy.md
docs/developer/nafan/nafanChanges02-05-2026.md
docs/developer/nafan/nafanChanges11-06-2026.md
docs/developer/nafan/nafanChanges17-04-2026.md
docs/developer/nafan/nafanChanges20-04-2026.md
docs/developer/nafan/nafanChanges24-04-2026.md
docs/developer/sapto/member-dashboard-overview-realtime-requirements-2026-05-31.md
docs/developer/sapto/requires/README.md
docs/developer/sapto/requires/admin-bundling-submissions-requirements.md
docs/developer/sapto/requires/frontend-backend-requirements-prd.md
docs/developer/sapto/saptoBundleBaseline-DFORM-1487.md
docs/developer/sapto/saptoChanges01-11-2026-DFORM-ZBN.md
docs/developer/sapto/saptoChanges03-11-2026-DFORM-09W.md
docs/developer/sapto/saptoChanges03-11-2026-DFORM-216.md
docs/developer/sapto/saptoChanges04-05-2026.md
docs/developer/sapto/saptoChanges04-11-2026-DFORM-AUDIT-P1-007.md
docs/developer/sapto/saptoChanges04-11-2026-DFORM-BE-P1-001.md
docs/developer/sapto/saptoChanges04-11-2026-DFORM-BE-P1-005.md
docs/developer/sapto/saptoChanges04-11-2026-DFORM-BE-P3-007.md
docs/developer/sapto/saptoChanges07-11-2026-DFORM-AUDIT-P2-010.md
docs/developer/sapto/saptoChanges08-11-2026-DFORM-AUDIT-P1-009.md
docs/developer/sapto/saptoChanges08-11-2026-DFORM-AUDIT-P2-006.md
docs/developer/sapto/saptoChanges08-11-2026-DFORM-BE-P2-001.md
docs/developer/sapto/saptoChanges08-11-2026-DFORM-BE-P2-002.md
docs/developer/sapto/saptoChanges08-11-2026-DFORM-BE-P3-009.md
docs/developer/sapto/saptoChanges08-11-2026-DFORM-BE-P3-011.md
docs/developer/sapto/saptoChanges08-11-2026-DFORM-SEC-P3-012.md
docs/developer/sapto/saptoChanges09-11-2026-DFORM-AUDIT-P1-005.md
docs/developer/sapto/saptoChanges09-11-2026-DFORM-AUDIT-P1-012.md
docs/developer/sapto/saptoChanges09-11-2026-DFORM-AUDIT-P1-014.md
docs/developer/sapto/saptoChanges09-11-2026-DFORM-AUDIT-P1-015.md
docs/developer/sapto/saptoChanges09-11-2026-DFORM-BE-P2-003.md
docs/developer/sapto/saptoChanges09-11-2026-DFORM-BE-P2-004.md
docs/developer/sapto/saptoChanges09-11-2026-DFORM-BE-P2-005.md
docs/developer/sapto/saptoChanges09-11-2026-DFORM-BE-P2-006.md
docs/developer/sapto/saptoChanges09-11-2026-DFORM-BE-P2-008.md
docs/developer/sapto/saptoChanges09-11-2026-DFORM-BE-P2-009.md
docs/developer/sapto/saptoChanges09-11-2026-DFORM-EP-00.md
docs/developer/sapto/saptoChanges09-11-2026-DFORM-EP-01.md
docs/developer/sapto/saptoChanges09-11-2026-DFORM-FE-AUDIT-002.md
docs/developer/sapto/saptoChanges09-11-2026-DFORM-FE-AUDIT-003.md
docs/developer/sapto/saptoChanges09-11-2026-DFORM-FE-AUDIT-004.md
docs/developer/sapto/saptoChanges09-11-2026-DFORM-FE-AUDIT-005.md
docs/developer/sapto/saptoChanges09-11-2026-DFORM-FE-AUDIT-006.md
docs/developer/sapto/saptoChanges09-11-2026-DFORM-FE-AUDIT-009.md
docs/developer/sapto/saptoChanges09-11-2026-DFORM-FLOW-P1-001.md
docs/developer/sapto/saptoChanges09-11-2026-DFORM-FLOW-P1-002.md
docs/developer/sapto/saptoChanges10-06-2026.md
docs/developer/sapto/saptoChanges10-11-2026-DFORM-EP-02.md
docs/developer/sapto/saptoChanges10-11-2026-DFORM-EP-03.md
docs/developer/sapto/saptoChanges11-11-2026-DFORM-EP-04.md
docs/developer/sapto/saptoChanges11-11-2026-DFORM-EP-05.md
docs/developer/sapto/saptoChanges11-11-2026-DFORM-EP-06.md
docs/developer/sapto/saptoChanges11-11-2026-DFORM-EP-07.md
docs/developer/sapto/saptoChanges11-11-2026-DFORM-FORM-DRAFT.md
docs/developer/sapto/saptoChanges12-11-2026-DFORM-FORM-ANSWERS-BULK.md
docs/developer/sapto/saptoChanges12-11-2026-DFORM-FORM-SHARE.md
docs/developer/sapto/saptoChanges13-11-2026-DFORM-DASHBOARD-CALENDAR.md
docs/developer/sapto/saptoChanges13-11-2026-DFORM-LOGOUT.md
docs/developer/sapto/saptoChanges17-11-2026-DFORM-DASHBOARD-ACTIVITY.md
docs/developer/sapto/saptoChanges17-11-2026-DFORM-DASHBOARD-CHART-REMOVAL.md
docs/developer/sapto/saptoChanges19-04-2026.md
docs/developer/sapto/saptoChanges24-10-2026-DFORM-TBD.md
docs/developer/sapto/saptoChanges24-10-2026-events.md
docs/developer/sapto/saptoChanges27-10-2026-DFORM-201.md
docs/developer/sapto/saptoChanges27-10-2026-DFORM-202.md
docs/developer/sapto/saptoChanges27-10-2026-DFORM-203.md
docs/developer/sapto/saptoChanges27-10-2026-DFORM-204.md
docs/developer/sapto/saptoChanges27-10-2026-DFORM-TBD.md
docs/developer/sapto/saptoChanges28-10-2026-DFORM-009.md
docs/developer/sapto/saptoChanges28-10-2026-DFORM-207.md
docs/developer/sapto/saptoChanges28-10-2026-DFORM-208.md
docs/developer/sapto/saptoChanges28-10-2026-DFORM-212.md
docs/developer/sapto/saptoChanges29-10-2026-DFORM-036.md
docs/developer/sapto/saptoChanges29-10-2026-DFORM-039.md
docs/developer/sapto/saptoChanges29-10-2026-DFORM-215.md
docs/developer/sapto/saptoChanges29-10-2026-DFORM-SEC-UPLOAD.md
docs/developer/sapto/saptoChanges30-10-2026-DFORM-AUTH-GUARD.md
docs/developer/sapto/saptoChanges30-10-2026-DFORM-CI-EXTENSIONS.md
docs/developer/sapto/saptoChanges30-10-2026-DFORM-FORM-BUILDER-SCROLL.md
docs/developer/sapto/saptoChanges30-10-2026-DFORM-FORM-SECTION-ACTION.md
docs/developer/sapto/saptoChanges30-10-2026-DFORM-FORM-SECTIONS.md
docs/developer/sapto/saptoChanges30-10-2026-DFORM-FORM-SETTINGS-TAB.md
docs/developer/sapto/saptoChanges30-10-2026-DFORM-XXX.md
docs/external-redeem-api.md
docs/laporan-codebase.md
docs/laporan.md
docs/rules/back-end.md
docs/rules/front-end-structure.md
docs/rules/front-end.md
docs/runbook/database-migrations.md
resources/js/components/modules/dashboard/KpiCard.vue
resources/js/components/modules/dashboard/WeeklyKpiBarChart.vue
resources/js/lib/eventStatusUi.ts
tests/e2e/form-answers-bulk-review.spec.ts
tests/e2e/weekly-kpi-trends.desktop.spec.ts
```

Lima kegagalan di luar `docs/**` (`KpiCard.vue`, `WeeklyKpiBarChart.vue`, `lib/eventStatusUi.ts`,
`form-answers-bulk-review.spec.ts`, `weekly-kpi-trends.desktop.spec.ts`) juga sudah gagal di
`HEAD^` dan bukan file yang disentuh tiket ini.

## Risiko dan rollback

- Risiko utama: bookmark/link lama ke `/admin/events/{event}/registrants` menjadi 404. Mitigasi:
  roster tetap tersedia lewat Detail Event → pilih form → tab **Jawaban**.
- Tidak ada perubahan data: `form_answers`, `forms`, `event_attendances` tidak disentuh dan tidak
  ada migration, sehingga rollback tidak memerlukan restore database.
- Rollback: revert satu commit ini (`git revert <hash>`), lalu `php artisan wayfinder:generate`
  dan rebuild frontend untuk mengembalikan route adapter.
