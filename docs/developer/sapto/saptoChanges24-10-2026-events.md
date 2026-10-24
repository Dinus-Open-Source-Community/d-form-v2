# Sapto Changes — 24 Oktober 2026 — Pencarian & Paginasi Acara (review 5 temuan)

Nomor tiket: **belum tersedia** (file ini sengaja tanpa `-DFORM-<N>`; ganti namanya setelah nomor keluar).
Status: implementasi selesai, belum di-push. Scope: `/events` (publik) dan `/browse` (member).

---

## Ringkasan (TL;DR)

| Temuan review | Perbaikan | Bukti |
|---|---|---|
| Cache menyimpan objek `Paginator` (link bisa membawa `q` pengguna lain) | Cache payload mentah `{rows, hasMore}`; `Paginator` dibangun ulang per request + `withQueryString()` | Payload 11 baris array, 8,4 KB, tanpa URL/HTML; test: dua varian huruf besar/kecil berbagi entri, tiap respons membawa `q` sendiri |
| `q=stress` mengulang query ±1,5 s tiap request | Negative cache `too_broad` + `Cache::lock` per key (double-check) | Panggilan kedua = **0 query `MATCH`** (test MySQL), respons 200 + `searchError` |
| `searchError` tidak ikut partial reload | `searchError` sudah masuk `only`; state pindah ke hook; e2e | Dua e2e hijau (muncul → hilang; 429 ramah) |
| `throttle:30,1` inline, 429 memicu halaman Error Inertia | Limiter bernama `event-search` (60/menit, config); 429 untuk request Inertia dikirim sebagai JSON non-Inertia | `Retry-After` → cooldown klien; toast/panel ramah; `#inertia-error-dialog` tidak muncul |
| Migration stub kosong `…round2` | Diisi 3 index terbukti EXPLAIN lalu dijalankan | `users ORDER BY created_at`: ALL 979.704 + filesort → covering index **0,41 ms** |

Test: **673 lulus / 36 skipped** (SQLite), **5 lulus** (grup MySQL), **2 e2e lulus**, Pint bersih.

---

## 1. T1 — Cache payload mentah

**File**: `app/Services/Event/PublicEventSearch.php` (baru), `app/Services/Event/EventService.php`, `app/Http/Controllers/EventsController.php`

- Kelas baru `PublicEventSearch` (satu tanggung jawab pencarian + daftar publik) menampung `terms()`, `page()`, cache/lock, dan penyusunan query. `EventService` tinggal browse/highlight/`paginationLinks` (482 → 314 baris).
- Payload cache: `['rows' => list<array>, 'hasMore' => bool]` — atribut Eloquent mentah (`getAttributes()`), bukan model.
- Rekonstruksi: `Event::hydrate($rows)` → `new Paginator($rows, 10, $page, ['path' => …, 'query' => request()->query()])` → `withQueryString()`.
  `Paginator::__construct` memotong ke `perPage` dan menurunkan `hasMore` dari `count > perPage`, jadi 11 baris cache menghasilkan `items()` 10 + `hasMore` benar.
- `terms()` men-`mb_strtolower` token → key cache stabil untuk `Seminar`/`seminar` (FULLTEXT memang case-insensitive).

**Review keputusan**: menyimpan model ter-serialisasi ditolak (rapuh saat skema berubah, membawa query string request pertama); menyimpan hanya id ditolak (butuh query hidrasi tambahan + menjaga urutan). Payload array + rekonstruksi = satu query tetap, tanpa model di cache.

## 2. T2 — Negative cache + lock

- Timeout MySQL (3024) → `Cache::put($key, ['too_broad' => true], too_broad_ttl)`; request berikutnya mengembalikan `null` tanpa menyentuh DB.
- `Cache::lock(lockKey)->block(lock_wait, …)` + double-check cache di dalam lock. Lock timeout → `null` (pesan ramah yang sama, bukan 500).
- Error selain 3024 tetap di-`throw`.
- Config baru di `config/events.php`: `cache_ttl_seconds` (60), `too_broad_ttl_seconds` (60), `lock_ttl_seconds` (10), `lock_wait_seconds` (3), `rate_limit_per_minute` (60), `max_page` (20), plus `max_execution_ms` yang sudah ada.

**Review keputusan**: hanya `max_execution_time` (tanpa marker) ditolak karena timeout bukan dinding keras (terukur berhenti ±1,24 s pada limit 1 ms) dan biaya berulang tetap 1,1–1,4 s per request. Antrian job ditolak (menambah worker + latensi).

## 3. T3 — `searchError` + hook

- `only: ['events','pagination','filters','searchError']`; `highlight` tidak disertakan karena `publicListHighlight()` tidak menerima kata kunci (tidak berubah per pencarian).
- State pencarian dipindah ke `resources/js/hooks/useEventSearch.ts` (folder `hooks/` baru — alasan: aturan repo menempatkan logic stateful di `hooks/`, `utils/composables` adalah struktur lama).
- Hook: debounce 350 ms, guard ≥3 karakter, cooldown 429 dari `Retry-After` (fallback 60 s), hitung mundur 1 s, cleanup timer/listener.
- UI: baris cooldown `Terlalu banyak pencarian. Coba lagi dalam N detik.` (LandingLayout tidak punya `<Toaster>`, jadi pesan inline adalah satu-satunya yang terlihat).

**Review keputusan**: menambah listener `invalid` kedua di `app.js` ditolak — sudah ada `useGlobalHttpStatusToast` yang menangani 429; dua listener = dua toast.

## 4. T4 — Rate limit global + 429 + TrustProxies

- `RateLimiter::for('event-search', …)` di `AppServiceProvider` (pola yang sudah ada), nilai dari config; route memakai `->middlewareFor('index', 'throttle:event-search')` (inline `throttle:30,1` dihapus).
- Akar masalah 429: `bootstrap/app.php` merender 429 sebagai halaman Inertia `Error`, sehingga event `invalid` tidak pernah terpicu dan halaman ikut berganti. Ditambahkan cabang: bila `X-Inertia` dan status 429 → `response()->json([...], 429)` + header throttle (`Retry-After`) disalin. Hasil: klien `preventDefault()` (tanpa modal), cooldown berjalan, halaman tetap.
- Kuota: **60/menit untuk seluruh route index** (daftar + pencarian). Opsi kuota terpisah ditolak untuk sekarang — tidak ada data yang menunjukkan daftar biasa butuh kuota lebih besar.
- TrustProxies: **tidak ada** `trustProxies` di `bootstrap/app.php` → `$request->ip()` = `REMOTE_ADDR`. Di balik reverse proxy, semua klien berbagi IP proxy dan rate limit praktis global. Rekomendasi (belum dijalankan): set `$middleware->trustProxies(at: ['10.0.0.0/8'])` atau CIDR proxy internal setelah topologi produksi dipastikan.

## 5. T5 — Migration `2026_10_24_013527_add_performance_indexes_round2`

Diisi tiga index (opsi A yang disetujui) lalu dijalankan (12 s):

| Index | Sebelum | Sesudah |
|---|---|---|
| `users(created_at, id)` | `type=ALL rows=979.704 Extra=Using filesort` | `type=index`, covering, backward scan, **0,41 ms** |
| `recruitment_activity_logs(created_at, id)` | index scan 1.107.597 + filesort | covering backward scan, tanpa filesort |
| `recruitment_correction_requests(status, created_at)` | `type=ALL rows=186.027` | `type=ref`, covering backward scan |

**Catatan mekanisme**: `migrate:rollback --step=1` **tidak** dipakai karena batch terakhir adalah migrasi FULLTEXT (rollback akan menghapus index pencarian). Karena `up()` lama kosong, baris migration dihapus dari tabel `migrations` lalu `php artisan migrate` dijalankan ulang — efeknya identik tanpa menyentuh schema lain.

`recruitment_interviews(status)` sengaja tidak dibuat: kardinalitas rendah, index scan 894.959 baris tetap terpilih.

## 6. T6 — Verifikasi

| Item | Hasil |
|---|---|
| `start_date` nullable | **Tidak** (`date NOT NULL`) → cursor aman, tanpa perubahan skema |
| MySQL lokal | 9.0.1 (innovation), `innodb_ft_min_token_size=3`, `innodb_ft_enable_stopword=1` |
| Urutan hasil search | tetap `ORDER BY relevance` (keputusan: tanpa prioritas `start_date >= today`) |
| FULLTEXT rebuild produksi | build pertama memblokir DML pada `events` (lokal 28 s) → jalankan jam sepi / `gh-ost` / `pt-online-schema-change` |
| Cache tidak di-invalidate `EventObserver` | diterima (TTL 60 s); dicatat di sini |

Perintah untuk staging (dijalankan manual oleh pemilik lingkungan):

```bash
mysql -e "select @@version, @@innodb_ft_min_token_size, @@innodb_ft_enable_stopword;"
mysql d_form -e "explain analyze select id from events where status='published'
  and match(title, location) against ('+5387*' in boolean mode) and deleted_at is null
  order by match(title, location) against ('+5387*' in boolean mode) desc limit 11\G"
```

## 7. Benchmark

**Query langsung (1.000.000 baris)**

| Kasus | Sebelum | Sesudah |
|---|---|---|
| Daftar tanpa kata kunci | 0,26 ms | 0,13 ms (cursor, tanpa COUNT/OFFSET) |
| Daftar halaman 500.000 | 121 ms (OFFSET) | 0,03 ms (keyset) |
| `+5387*` | — | 0,4–2,2 ms |
| `+zzzzqqq*` | 4.300 ms (`LIKE`) | 0,009–0,023 ms |
| `+stress*` (cocok ±795 ribu) | — | 363–1.377 ms, **dibatasi** 1.500 ms + cache 60 s |

**k6 (20 VU, `q=stress`, cache aktif, debugbar dimatikan)**

| Skenario | Hasil |
|---|---|
| 20 VU × 30 s | 3.770 request, **0 gagal**, p95 **180 ms**, avg 160 ms |
| 5 VU × 20 s | p95 **53 ms**, avg 45 ms, max 1,33 s (satu query dingin) |
| 20 VU × 20 s, `q=5387` | p95 280 ms, 0 gagal (antrean 1 worker Octane, bukan DB) |

Catatan metodologi:
- `performance_schema` digest tidak merekam query `MATCH` di instance ini, jadi jumlah query berat diverifikasi lewat pola latensi (max 1,33 s lalu median 41 ms) + isi cache (11 baris tersimpan).
- Debugbar (dev) menyebabkan OOM 256 MB pada 20 VU — dimatikan selama uji, dikembalikan setelahnya.
- Rate limit dinaikkan sementara lewat `.env` + `octane:reload`, lalu `.env` dikembalikan (`cp /tmp/env.bak .env`).

## 8. Test

| Suite | Hasil |
|---|---|
| SQLite (default) | 673 lulus, 36 skipped, 5.187 assertions |
| Grup MySQL (`d_form_test`) | 5 lulus, 18 assertions (prefix, stopword, operator, timeout, negative cache) |
| E2E Playwright | 2 lulus (`tests/e2e/event-search.spec.ts`) |

Test baru: `tests/Unit/EventSearchTermsTest.php` (9), `tests/Feature/EventListPaginationTest.php` (cache key + link `q`, lock sibuk), `tests/Feature/EventSearchRateLimitTest.php`, `tests/Feature/EventListSearchMysqlTest.php` (+1), `tests/e2e/event-search.spec.ts`.

Menjalankan grup MySQL tanpa file konfigurasi duplikat:

```bash
podman exec -i -w /app d_form_app sh -lc '
  sed -e "s|value=\"sqlite\"|value=\"mysql\"|" -e "s|value=\":memory:\"|value=\"d_form_test\"|" phpunit.xml > phpunit.mysql.xml
  ./vendor/bin/phpunit -c phpunit.mysql.xml --group=mysql
  rm -f phpunit.mysql.xml'
```

E2E (dari host, Chromium glibc tidak bisa jalan di container Alpine):

```bash
npx playwright test tests/e2e/event-search.spec.ts
```

Catatan: run pertama setelah mengubah file `resources/js` bisa lambat (Vite rebuild) dan sempat membuat dua e2e gagal karena timeout 30 s; run berikutnya hijau.

## 9. Risiko & trade-off yang diterima

1. **Batas waktu bukan dinding keras** — MySQL memeriksa `max_execution_time` secara berkala; pada limit 1 ms query baru berhenti ±1,24 s. Nilai 1.500 ms adalah kebijakan.
2. **Kata umum bisa tidak menampilkan hasil** — jika `q=stress` melewati 1.500 ms, pengguna mendapat `searchError` (200), bukan daftar.
3. **Cache 60 s** bisa menyajikan hasil tertinggal maksimal satu menit; tidak di-invalidate `EventObserver`.
4. **Pencarian tetap OFFSET** — relevansi adalah nilai komputasi, keyset tidak mungkin; terukur 0,58 → 0,98 ms pada offset 5.000.
5. **FULLTEXT hanya prefix per kata**, bukan substring; token < 3 huruf diabaikan.
6. **Rate limit per IP** — tanpa TrustProxies, di balik proxy semua klien berbagi kuota.
7. **`searchTerms()` public static** — dipakai unit test tanpa container.
8. **`.env` sementara** — `EVENTS_SEARCH_RATE_LIMIT_PER_MINUTE` & `DEBUGBAR_ENABLED=false` hanya selama k6; sudah dikembalikan.

## 10. File

Backend: `app/Services/Event/PublicEventSearch.php` (baru), `app/Services/Event/EventService.php`, `app/Http/Controllers/EventsController.php`, `app/Providers/AppServiceProvider.php` (sudah ikut commit auth), `bootstrap/app.php`, `config/events.php` (baru), `routes/web/index.php`, `routes/web/admin/index.php`, `database/migrations/2026_10_24_010500_*` (FULLTEXT), `database/migrations/2026_10_24_013527_*` (round-2).

Frontend: `resources/js/hooks/useEventSearch.ts` (baru), `resources/js/components/modules/landing/events/EventList.vue`, `resources/js/pages/Event.vue`, `resources/js/pages/Dashboard/User/Events.vue`, `resources/js/components/core/AppPagination.vue` (pindahan), `resources/js/components/modules/dashboard/FormSubmissionsPagination.vue` (dihapus), `resources/js/lib/paginationLabel.ts` (baru), `resources/js/lib/formSubmissionsUi.ts`, `resources/js/components/modules/dashboard/EventReportingFocusPanel.vue`, `resources/js/pages/Dashboard/Events/Forms/Submissions.vue`.

Test: `tests/Unit/EventSearchTermsTest.php`, `tests/Feature/EventListPaginationTest.php`, `tests/Feature/EventListSearchMysqlTest.php`, `tests/Feature/EventSearchRateLimitTest.php`, `tests/Feature/Dashboard/BrowsePaginationTest.php`, `tests/e2e/event-search.spec.ts`.

## 11. Checklist

- [x] Link paginasi tidak membawa `q` pengguna lain
- [x] `q=stress` berulang tidak menghantam DB tiap request (negative cache + lock)
- [x] `searchError` muncul lalu hilang (e2e)
- [x] 429 tampil ramah, bukan modal/halaman Error Inertia (e2e)
- [x] Suite SQLite + grup MySQL + Pint hijau
- [x] Tidak ada filesort pada kedua jalur (EXPLAIN)
- [ ] Nomor tiket → rename file + update README
- [ ] Push & Jira In Review (setelah review manusia)

---

## Sesi lanjutan — audit performa & keamanan (lanjutan hari yang sama)

| Commit | Isi | Bukti |
|---|---|---|
| `cfe90ed` | IDOR: `EventController@update` memakai `authorize('update', $event)`; `sort=foo` tidak lagi 500 | 4 test |
| `8801fd6` | Tren registrasi dari `event_daily_registrations` (`SUM(count)`, bukan `count(*)`) + `FormAnswerObserver` + command rekonsiliasi | 12,05 s → 0,004 s |
| `bf3a0b7` | Filter daftar admin: predikat `created_by OR NULL` dikelompokkan, timeline memakai kolom DATE langsung, `page` dibatasi | 2 test |
| `c1e85b3` | Index `events(deleted_at, title)` untuk sort default | query daftar 7,7 s → 7,8 ms |
| `98aa226` | Statistik dipisah dari jumlah pendaftar (`BUSTER_REGISTRANTS`), `useIndex` untuk count closed, scan page tanpa `select *` | count closed 5,7 s → 0,14 s; 0 query `select *` |
| `6665e57` | Review submisi wajib pemilik event; event draft/terhapus tidak menerima submisi | 4 test |
| `aa45432` | Opsi filter kategori/sesi tanpa `DISTINCT` 1 juta baris (cache + observer + command rebuild) | `/admin/events` cold 13,3 s → 0,30 s |
| `14ee313` | `per_page` daftar event dihormati (default 8) | 3 test |
| `534ad7a`, `240ecd4` | CSV/formula injection: helper tunggal, termasuk export GlobalScan (CSV + XLSX) | 37 test |
| `2ccb1b4`, `498b662` | Stored XSS: sanitasi HTML saat simpan + command backfill HTML lama + restore state libxml + decode entity | 10 test |
| `f2522bc`, `3403e8a` | `/joined` dibatasi 60 + `totalJoined`/`truncated`, `globalAdminSummary` di-cache 300 s, `rememberList` tidak menelan error | 17 test |
| `12db4d3` | Satu pagination global: per-halaman (8/25/50/100), rentang `1–8 dari 1m`, window 10 nomor, mode cursor, hapus `ui/pagination/*` | eslint + build + browser |

Catatan operasional:
- `ANALYZE TABLE events, forms, form_answers, users, recruitment_applications, recruitment_interviews` dijalankan sekali (statistik optimizer basi membuat count closed 5,7 s).
- Jalankan `php artisan analytics:rebuild-event-options` setelah deploy/seed (satu-satunya jalur yang memindai `events` untuk opsi filter).
- Jalankan `php artisan security:sanitize-stored-html` (opsional `--dry-run`) sekali untuk membersihkan HTML lama.
- Flaky: `RecruitmentEmailResendTest` gagal bila seluruh suite berjalan (lulus saat dijalankan sendiri) — test isolation, bukan regresi.

---

## Sesi lanjutan — code splitting frontend & dashboard cepat

| Commit | Isi | Bukti |
|---|---|---|
| `7612d6c` | Vendor berat hanya di chunk pemakainya: chart dashboard & editor Tiptap/Broadcast lewat `defineAsyncComponent` + `DeferredRender` (IntersectionObserver); `vendor-ui` (reka-ui) tidak lagi dipaksa di first load | `/admin/events` 211,9 → 190,5 kB gzip; `/admin/dashboard` 260,6 → 187,6; `/admin/events/create` 393,8 → 270,8; entry 100,6 kB |
| `c491510` | Deret pagination per blok kelipatan 10 dengan elipsis di ujung; nomor halaman terakhir tidak pernah dirender | halaman 1 `1..10 …`, 14 `… 11..20 …`, 200 `… 191..200 …` |
| `1296d33` | Agregat dashboard dipindah ke cache + `dashboard:warm` (skip bila terisi, `--force` untuk paksa) | cold 9,12 s → 0,085 s; warm 0,28 → 0,062 s |
| `814d78b` | Tabel `event_category_counts` dijaga `EventObserver` (delta) + `analytics:rebuild-category-counts`; jalur baca kembali compute-on-miss (tanpa cron) | `/dashboard` dingin 9,12 s → 1,87 s; hangat 0,28 s → 0,025 s; `report:category-tokens` 6,3 s → 1–2 ms |

Catatan operasional:
- `php artisan migrate` (tabel `event_category_counts`) lalu `php artisan analytics:rebuild-category-counts` — wajib setelah `stress:seed`/bulk insert yang melewati observer. Rebuild memindai 1 juta baris ±7 s (sekali, offline).
- `php artisan dashboard:warm` opsional setelah deploy/seed; tanpa itu pengunjung pertama menghitung sendiri (±1,9 s) lalu hangat.
- Tidak ada cron/scheduler untuk cache dashboard (keputusan pemilik proyek): cache kosong dihitung saat request, bukan menampilkan nol.
- `vite.config.js` (hapus `manualChunks['vendor-ui']`, `VITE_DEV_HOST`, pekerjaan SSR) sengaja belum di-commit karena mencampur pekerjaan pengguna.
