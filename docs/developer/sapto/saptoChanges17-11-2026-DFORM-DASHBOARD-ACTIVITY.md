# DFORM-DASHBOARD-ACTIVITY — Dashboard "Aktivitas terbaru" rework

| Issue | Commit |
| --- | --- |
| DFORM-DASHBOARD-ACTIVITY | Uncommitted |

Klasifikasi: `refactor` + `feature` (UI dashboard admin, scope terbatas).

## Ringkasan (TL;DR)

Section "Aktivitas terbaru" di `/dashboard` sekarang punya satu judul, satu baris per acara dengan
status dan isi kuota, serta aksi `Kelola` / `Pendaftar` yang bisa dipakai tanpa membuka halaman lain.
Query dashboard memakai aturan kepemilikan yang sama dengan halaman Acara, sehingga role `admin`
tidak lagi menerima baris milik admin lain.

## Masalah yang diperbaiki

1. Thumbnail rusak: `placeholder.jpg` tidak ada di `storage/app/public/events/banners`, dan kartu
   memakai `<img>` mentah tanpa fallback. Sekarang memakai `EventBannerImage`.
2. Judul berlapis: `h2` "Aktivitas terbaru" + `CardTitle` "Acara terbaru" + subjudul.
3. `status` dan `registration_status` sudah dikirim server tetapi tidak pernah ditampilkan.
4. Kuota `0/150` tanpa konteks dan tanpa indikator keterisian.
5. Meta 11px; lokasi dan kategori hilang di mobile.
6. Baris hanya bisa diklik, tanpa affordance dan tanpa aksi; empty state satu kalimat.
7. Baris berujung 403 untuk role `admin` karena query dashboard tidak dibatasi pemilik,
   sementara `EventPolicy::view` membatasinya.

## Perubahan

### Backend

- `app/Models/Event.php`: scope baru `visibleTo(Builder, User)` — super-admin melihat semua, lainnya
  `created_by = user` atau `created_by IS NULL`, dikelompokkan dalam satu closure.
- `app/Services/Event/EventService.php`: blok kepemilikan inline di `paginateForAdminIndex` diganti
  `$query->visibleTo($user)`. Isi cache key tidak berubah.
- `app/Http/Controllers/Dashboard/HomeController.php`: guard `instanceof User` (401), query recent
  events memakai `visibleTo`, dan prop baru `canCreateEvent` dari `Gate::allows('create', Event::class)`.
- `tests/Feature/Dashboard/DashboardRecentEventsTest.php` (baru): 5 test / 64 assertion.

### Frontend

- `resources/js/lib/eventStatusUi.ts` (baru, pure): `eventPublicationBadge`,
  `eventRegistrationBadge`, `eventQuotaFillPercent`. Satu sumber label + kelas untuk dua permukaan.
- `resources/js/pages/Dashboard/Index.vue`: pemilik header section (h2, subtitle jumlah nyata,
  `Lihat semua`); menerima `canCreateEvent` dan menurunkan `createEventHref`.
- `resources/js/components/modules/dashboard/RecentEventsCard.vue`: ditulis ulang sebagai `<ul>/<li>`
  semantik. Thumbnail selalu tampil (56x42, `md` 80x60), judul `h3`, maksimal dua chip status,
  meta satu baris, blok kuota dengan `Progress` 4px dan `aria-label`, aksi sebagai link saudara
  (bukan anchor bersarang), empty state dengan CTA yang hanya muncul bila user boleh membuat acara.
- `resources/js/components/modules/dashboard/events/EventCard.vue`: `registrationUi()` lokal dihapus
  dan diganti lib bersama. Satu perubahan copy: `Buka` -> `Dibuka`, `Tutup` -> `Ditutup`.

## Keputusan dan alternatif yang ditolak

- **Opsi B dipilih** (perbaikan baris + aksi cepat + scoping owner). Opsi A (visual saja) ditolak
  karena tidak menjawab kebutuhan memilah-lalu-melangkah; opsi C (strip "perlu tindakan") ditolak
  karena menambah tinggi dan aturan bisnis baru yang tumpang tindih dengan KPI.
- **Server yang menentukan urutan dan jumlah** (`created_at DESC`, `limit 5`). Sorting dan `slice`
  di klien dihapus supaya hanya ada satu sumber kebenaran.
- **Tombol `Pendaftar` disembunyikan untuk acara draf** (keputusan user). Halaman pendaftar untuk
  draf kosong, jadi baris draf hanya menampilkan `Kelola` plus chip `Draf`.
- **44px ditulis literal**, bukan `h-11`: `resources/css/app.css` memakai `html { font-size: 15px }`
  di bawah 640px, sehingga `h-11` hanya 41.25px dan gagal target sentuh. Nilai yang sama dipakai
  untuk `Lihat semua` dan `Buat acara` di mobile.
- **Test E2E men-stub `https://seeyu.doscom.org/script.js`**, mengikuti preseden
  `tests/e2e/form-answers-mobile.spec.ts:178`. Script itu hardcoded di `app.blade.php` dan tidak
  resolve offline; assertion console error tetap dipertahankan apa adanya.
- **Tidak ada dependency baru**, tidak ada perubahan `resources/js/components/ui/**`.

## Verifikasi

Semua dijalankan pada 17-11-2026 di working tree ini.

| Cek | Hasil |
| --- | --- |
| `phpunit tests/Feature/Dashboard/DashboardRecentEventsTest.php` | OK, 5 test / 64 assertion (RED lebih dulu: 3 gagal) |
| `phpunit tests/Feature/Dashboard` | 115 test, 1002 assertion, 1 gagal (pre-existing, lihat Known issues), 1 skip |
| `phpunit EventManagementTest + EventListCacheTest` (regresi consumer `EventService`) | OK, 19 test / 101 assertion |
| `pint --test` (4 file tiket) | PASS |
| `npm run typecheck` | exit 0 |
| `npm run test:e2e:types` | exit 0 |
| `npm run lint` | 0 error, 1 warning pre-existing (`formBuilderAutosave.test.ts` unused `SAMPLE_FIELDS`) |
| `npm run test:unit` | 90 file, 992 test lulus |
| E2E `dashboard-recent-events.desktop.spec.ts` | 2 lulus (setup + spec) |
| E2E `dashboard-recent-events.mobile.spec.ts` | 2 lulus (setup + spec) |
| E2E regresi `kpi-card.desktop.spec.ts` | 2 lulus |
| `npm run build` (container) | sukses, 12.76 s |

### Bukti ukuran bundel

| Metrik | Baseline | Sesudah |
| --- | --- | --- |
| Entry `app-*.js` | 51.19 kB / 16.06 kB gzip | 51.24 kB / 16.07 kB gzip |
| Chunk terbesar 1 | 462.34 kB / 145.58 kB gzip | 462.34 kB / 145.58 kB gzip |
| Chunk terbesar 2 | 334.65 kB / 99.40 kB gzip | 334.65 kB / 99.40 kB gzip |
| Chunk terbesar 3 | 314.47 kB / 80.99 kB gzip | 314.47 kB / 80.99 kB gzip |

Entry naik 0.05 kB raw / 0.01 kB gzip. Budget entry (<= 350 kB gzip) terpenuhi. Chunk terbesar
1 dan 2 sudah melewati 200 kB gzip sebelum perubahan ini (pre-existing, bukan akibat tiket ini).

## Known issues dan follow-up

- **Pre-existing, terkonfirmasi**: `DashboardCacheWarmTest::test_request_setelah_warm_tidak_menjalankan_query_berat`
  gagal karena `EventReportingQuery::registrationCountsFromAggregate()` memutasi `Illuminate\Support\Carbon`
  (`$end = $start->addMonths($months)`), sehingga filter rentang agregat kosong dan query selalu jatuh
  ke scan `form_answers`. Dibuktikan tetap gagal saat perubahan tiket ini di-stash. Perlu tiket sendiri.
- **Pre-existing**: `pint --test` seluruh repo melaporkan 4 file ( `UserEventPortalController.php`,
  `User.php`, `KeysetMergeIterator.php`, `routes/ai.php` ) — tidak disentuh tiket ini.
- **Pre-existing**: fatal `laravel-debugbar` `QueryCollector` memicu 500 intermiten di environment
  lokal (1 dari 5 run E2E; log sudah memuat fatal serupa sejak 14-11-2026). Assertion spec tidak
  dilonggarkan; perbaikan ada di sisi debugbar/config, bukan di test.
- **Follow-up**: konsolidasi badge status di halaman landing (`EventList`, `EventHighlight`) ke
  `eventStatusUi` belum dilakukan karena audiens dan copy-nya berbeda.
- **Follow-up**: `stats` dashboard tetap agregat global (tidak di-scope per user), sementara daftar
  recent events sudah di-scope. Inkonsistensi ini sudah ada sebelumnya dan dicatat, bukan diubah.
- **Environment**: `storage/app/e2e` diberi izin tulis (`chmod -R a+rwX`) agar harness E2E bisa
  membuat direktori heartbeat; tidak ada data yang dihapus.
- Artefak recon `before-desktop.png` dan `before-mobile.png` sudah dihapus dari root repo
  (artefak sesi, untracked); tidak pernah di-stage.

## Checklist

- [x] Test relevan hijau (unit, feature, E2E desktop + mobile)
- [x] Typecheck, E2E typecheck, lint, build
- [x] Changelog dan README diperbarui
- [x] Tidak ada dependency baru dan tidak ada perubahan `components/ui/**`
- [x] Tidak ada secret atau dataset lokal di perubahan
- [x] Perubahan kode selesai dan terverifikasi; commit sengaja tidak dilakukan atas keputusan user
