# DFORM-DASHBOARD-CHART-REMOVAL — Hapus "Analitik singkat" dan pipeline agregat kategori

| Issue | Commit |
| --- | --- |
| DFORM-DASHBOARD-CHART-REMOVAL | Uncommitted (staged) |

Klasifikasi: `refactor` (penghapusan fitur + dead code).

## Ringkasan (TL;DR)

Section "Analitik singkat" di `/dashboard` dihapus seluruhnya: dua kartu ("Tren pengajuan",
"Acara per kategori"), dua komponen chart, lima method query yang hanya melayaninya, agregat
`event_category_counts` beserta service/command/observer hook-nya, dan migration drop untuk tabel
tersebut. Tidak ada sisa referensi di `app/`, `resources/js`, `database/`, `routes/`, `config/`.

## Yang dihapus

### Frontend

| File | Alasan |
| --- | --- |
| `resources/js/components/modules/dashboard/RegistrationChart.vue` | hanya dipakai section yang dihapus |
| `resources/js/components/modules/dashboard/CategoryChart.vue` | hanya dipakai section yang dihapus |

`Dashboard/Index.vue` kehilangan: prop `adminCharts`, computed `registrationPoints` dan
`categoryBreakdown`, dua `defineAsyncComponent`, section `<section v-if="adminCharts">`, serta
import `defineAsyncComponent` dan `DeferredRender` yang jadi tidak terpakai.

### Backend

| File | Perubahan |
| --- | --- |
| `app/Services/Reporting/EventReportingQuery.php` | hapus `registrationTrendOverMonths()`, `registrationCountsFromAggregate()`, `registrationCountsFromSource()`, `computeRegistrationCountsFromSource()`, `eventsCountByCategoryToken()`, `computeCategoryTokens()`, constructor `EventCategoryCounter`, 5 konstanta cache, dan step kategori di `warmDashboardCache()` |
| `app/Services/Event/EventCategoryCounter.php` | dihapus (tidak ada pembaca tersisa) |
| `app/Console/Commands/Analytics/RebuildCategoryCountsCommand.php` | dihapus (hanya mengisi agregat yang sudah tidak dibaca) |
| `app/Observers/EventObserver.php` | hapus injection counter, 3 `applyDelta`, `liveCategoryTokens()`, `previousCategoryTokens()`, bump `BUSTER_CATEGORY`; `invalidateEventCache()` disederhanakan |
| `app/Services/Event/EventCache.php` | hapus konstanta `BUSTER_CATEGORY` |
| `database/seeders/EventSeeder.php` | hapus `EventCategoryCounter::rebuild()` dan bump kategori |
| `app/Console/Commands/Analytics/WarmDashboardCacheCommand.php` | docblock/description tidak lagi menyebut kategori |
| `app/Http/Controllers/Dashboard/HomeController.php` | hapus array `$adminCharts` dan key payload `adminCharts` |

### Migration

- Baru: `database/migrations/2026_11_17_000000_drop_event_category_counts_table.php`
  - `up()`: `dropIfExists('event_category_counts')`
  - `down()`: membuat ulang tabel dengan bentuk identik (PK `category` 191, `total` unsigned, collation `utf8mb4_bin`) sehingga rollback tidak menipu
- Migration pembuat tabel **tetap disimpan**. Fresh install akan create lalu drop; menghapus riwayat migration tidak dilakukan.

### Test

Dihapus karena menguji fungsi yang sudah tidak ada:

- `tests/Feature/Reporting/RegistrationTrendTest.php`
- `tests/Feature/Reporting/RegistrationTrendFallbackTest.php`
- `tests/Feature/Reporting/EventCategoryCounterTest.php`
- `tests/Feature/Reporting/DashboardAnalyticsSourceTest.php`

Disesuaikan agar tetap menguji perilaku nyata:

- `DashboardCacheWarmTest`: `test_command_mengisi_tiga_cache_dashboard` → `test_command_mengisi_dua_cache_dashboard`; assertion key/isi cache kategori dan `adminCharts.categoryBreakdown` dihapus; assertion stats, registrants, dan "tidak ada query berat" dipertahankan.
- `DashboardRolesTest`: assertion `adminCharts.*` dihapus; test diganti nama menjadi `test_admin_dashboard_returns_stats_and_recent_events`.
- `RebuildDailyRegistrationsCommandTest`: assertion lewat `registrationTrendOverMonths()` diganti assertion langsung pada isi `event_daily_registrations`; assertion buster fallback dihapus; idempotensi tetap dibuktikan lewat snapshot tabel identik.
- `EventObserverCascadeQueryTest`: fixture `EventCategoryCounter::rebuild()` dihapus; `MAX_QUERIES_PER_OPERATION` diturunkan dari 8 ke 4 sesuai hasil pengukuran (delete 3, restore 4).

## Yang sengaja dipertahankan

| Hal | Alasan |
| --- | --- |
| `event_daily_registrations` + `FormAnswerObserver` + `RebuildDailyRegistrationsCommand` + `rebuildDailyRegistrationAggregate()` | `dashboardWeeklyKpiTrends()` membaca agregat harian untuk tren mingguan di KPI card |
| `chart.js` dan `vue-chartjs` | dipakai `WeeklyKpiBarChart.vue` di dalam `KpiCard` |
| `Event::tokensFromCsv()` | masih dipakai service opsi kategori/sesi |
| Migration pembuat `event_category_counts` | riwayat schema; drop ditangani migration baru |

## Keputusan dan alternatif yang ditolak

- **Hapus backend, bukan hanya menyembunyikan chart.** Menyembunyikan section akan meninggalkan lima method query, satu service, satu command, dan satu tabel tanpa pemakai. Alternatif "UI only" ditolak karena justru menambah tech debt.
- **Tabel di-drop lewat migration baru.** Isi tabel 100% turunan dari `events.category`, jadi tidak ada data yang hilang secara semantik; rollback mengembalikan bentuk tabel. Alternatif "biarkan tabel" ditolak karena menyisakan dead weight di schema.
- **Preserve `event_daily_registrations`.** Sempat terlihat seperti kandidat hapus, tetapi `dashboardWeeklyKpiTrends()` memakainya; menghapusnya akan mematikan tren mingguan di KPI card.
- **Bound query test diturunkan ke angka terukur (4), bukan dibiarkan di 8.** Batas lama memasukkan biaya update agregat kategori yang kini tidak ada; membiarkan 8 akan membuat test berbohong tentang biaya sebenarnya.

## Verifikasi

| Cek | Perintah | Hasil |
| --- | --- | --- |
| Pint (file yang diubah) | `pint --test` pada 7 file | PASS, exit 0 |
| Pint (tests) | `pint --test tests` | PASS, 213 file, exit 0 |
| Feature suites terdampak | `phpunit tests/Feature/Reporting tests/Feature/Analytics tests/Feature/Events/EventObserverCascadeQueryTest.php tests/Feature/Dashboard` | 129 test, 1076 assertion, 0 gagal, exit 0 |
| Full PHPUnit | `phpunit` | 1663 test, 10793 assertion, **2 gagal pre-existing** (lihat di bawah), 13 skip, exit 1 |
| Typecheck | `npm run typecheck` | exit 0 |
| E2E typecheck | `npm run test:e2e:types` | exit 0 |
| Lint | `npm run lint` | 0 error, 1 warning pre-existing, exit 0 |
| Unit | `npm run test:unit` | 90 file / 992 test, exit 0 |
| Build | `npm run build` (container) | exit 0, 15.98 s |
| E2E desktop | recent-events + KPI + weekly trends | 4 lulus, exit 0 |
| E2E mobile | recent-events | 2 lulus, exit 0 |
| Migration | `migrate` → `migrate:rollback --step=1` → `migrate` | semua DONE; `Schema::hasTable('event_category_counts')` = false |
| Cache warm | `php artisan dashboard:warm --force` | 2 kunci (`dashboard:stats:events`, `dashboard:stats:registrants`) tanpa error |
| Sisa referensi | grep `EventCategoryCounter\|BUSTER_CATEGORY\|registrationTrendOverMonths\|adminCharts\|event_category_counts` di `app tests database routes config` | hanya dua migration tabel (diharapkan) |

### Bukti ukuran bundel

| Metrik | Sebelum | Sesudah |
| --- | --- | --- |
| Entry `app-*.js` | 51.24 kB / 16.07 kB gzip | 51.20 kB / 15.92 kB gzip |
| Chunk terbesar 1 | 462.34 kB / 145.58 kB gzip | 462.34 kB / 145.58 kB gzip |
| Chunk terbesar 2 | 334.65 kB / 99.40 kB gzip | 334.65 kB / 99.40 kB gzip |
| Chunk terbesar 3 | 314.47 kB / 80.99 kB gzip | 314.47 kB / 80.99 kB gzip |
| Chunk chart | chart.js dibagi tiga komponen | `WeeklyKpiBarChart` 135.07 kB / 48.12 kB gzip |

Dashboard tidak lagi mengunduh chunk `RegistrationChart`/`CategoryChart`. `chart.js` tetap dimuat
lewat KPI card karena tren mingguan masih dipakai.

## Known issues (pre-existing, tidak disebabkan perubahan ini)

- `RecruitmentApplicationListQueryTest::test_list_query_uses_composite_index_and_backward_scan_on_mysql`
  gagal karena index `rec_apps_period_submitted_idx` sudah tidak ada di DB dev, walau migration
  pembuatnya tercatat `Ran`. Gagal juga saat dijalankan sendiri; file test tidak diubah.
- `RecruitmentFinalSelectionTest::test_reject_email_shows_new_copy` gagal hanya di full suite dan
  lulus saat dijalankan sendiri (order-dependent).
- `pint --test` seluruh repo masih melaporkan 4 file yang tidak disentuh perubahan ini:
  `app/Http/Controllers/Dashboard/User/UserEventPortalController.php`, `app/Models/User.php`,
  `app/Support/Export/KeysetMergeIterator.php`, dan `routes/ai.php`.

## Catatan operasional

- `bootstrap/ssr/assets/Index-*.js` adalah artefak build SSR lama dan masih memuat string
  `adminCharts`; file ini generated dan akan tergantikan saat build SSR berikutnya.
- Seluruh perubahan (dua tiket: `DFORM-DASHBOARD-ACTIVITY` dan `DFORM-DASHBOARD-CHART-REMOVAL`)
  berada di index git dan **belum di-commit**.
