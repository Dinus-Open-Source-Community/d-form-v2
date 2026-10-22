# Sapto Changes — 21 Oktober 2026

Perubahan sesi ini tidak menyentuh alur produk; isinya **aturan kerja proyek**, **konteks proyek**, dan **harness stress test + dataset uji**. Semua artefak masih di working tree (belum di-commit).

---

## Ringkasan (TL;DR)

| Area                                   | Perubahan utama                                                                                                                                                                                                                                                                                                                               |
| -------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **Aturan proyek (`AGENTS.md`)**        | 14 aturan baru diintegrasikan (best practice, reuse lintas-potong, konsistensi, baca `CONTEXT.md`, ukuran optimal ≠ jumlah baris, subagent per capability, tanya daripada menebak, commit per fitur, security back-end, stress test k6, seeder factory 1 juta baris). 2 aturan dihapus, 4 pasang duplikasi dirapikan, 3 rujukan mati dibuang. |
| **Konteks proyek (`CONTEXT.md`)**      | Dokumen konteks baru di root: produk, stack, topologi, peta direktori, glosarium 26 enum, 9 diagram mermaid (alur registrasi, scan, autosave, OpRec, broadcast, ERD), route map, permission, storage, testing, 12 jebakan terverifikasi.                                                                                                      |
| **Stress harness (`scripts/stress/`)** | `run.sh` (seed → generate → k6 → queries), `generate-targets.php` (50 endpoint GET dari 156 route), 4 skenario k6 (smoke, flood 0→500 VU, spike 800 VU), `summary.php`, README.                                                                                                                                                               |
| **Dataset stress**                     | `php artisan stress:seed` — 12 tabel event-domain + 16 tabel recruitment, UUID5 deterministik, bulk insert per chunk, manifest kredensial.                                                                                                                                                                                                    |
| **Benchmark query**                    | `php artisan stress:queries` — 20 query produksi (roster, submission, scan feed, reporting, recruitment, broadcast) dengan median 3×, EXPLAIN ANALYZE, deteksi full scan, probe N+1.                                                                                                                                                          |

---

## 1. Aturan proyek — `AGENTS.md`

### 1.1 Dihapus

| Sebelum                                                                       | Alasan                                                                    |
| ----------------------------------------------------------------------------- | ------------------------------------------------------------------------- |
| Rujukan `docs/rules/front-end.md` di Aturan wajib                             | Digantikan oleh blok Typing + Penempatan berbasis folder yang lebih tegas |
| Pengecualian builder berbasis "spec migrasi §2 MENANG atas `front-end.md` §2" | Digantikan aturan pengecualian eksplisit (lihat 1.3)                      |

### 1.2 Duplikasi yang dirapikan

| Duplikat                                             | Tindakan                                                                  |
| ---------------------------------------------------- | ------------------------------------------------------------------------- |
| Aturan 3 (tipe longgar) ≈ Typing butir 2             | Butir Typing dihapus; aturan 3 jadi satu sumber                           |
| Aturan 2 (utility TS) ≈ Aturan 14 (tanpa redundansi) | Digabung ke aturan 2; daftar user menjadi 13 butir                        |
| `components/ui/` di Arsitektur ≈ di tabel folder     | Butir Arsitektur dihapus; hanya tersisa di tabel + catatan utang historis |
| `.vue` UI-only ≈ baris `pages/`                      | Baris `pages/` diubah menjadi larangan spesifik (komponen reusable)       |
| `Lihat docs/agents/*.md` (3 tempat)                  | Rujukan mati dihapus; substansi tetap inline                              |

### 1.3 Aturan baru

- **Prinsip engineering:** best practice + optimal; ukuran keberhasilan bukan jumlah baris (performance, optimalisasi, konsistensi, maintainability, scalability); konsisten dengan pola yang ada; dedup bukan rename; bebas if hell/N+1/kompleksitas yang menciptakan tech debt.
- **Aturan user 6 diperluas:** sebelum menulis, cari implementasi serupa; satu duplikasi → satu hook/fungsi `lib/`/layer lintas-potong (contoh: rate limiter global, sanitasi input, sanitasi query).
- **Alur kerja:** pecah task lewat subagent sesuai capability; tidak tahu → bertanya (dilarang menebak/mengarang); posisi agent = team lead; commit setelah fitur selesai utuh (bukan atomik per langkah).
- **Gate:** urutan e2e merah → review keputusan → implementasi → stress test setelah fitur (back-end pakai k6).
- **Back-end:** security wajib (validasi Form Request, otorisasi per endpoint, parameter binding, escape output, CSRF, whitelist upload, secret dari config), rate limit untuk endpoint publik, sanitasi dipusatkan, cek N+1 + indeks + rencana eksekusi sebelum menambah query.
- **Seeder & data uji:** `stress:seed` sebagai satu pintu; 1 juta baris per tabel domain; tabel referensi/infra kecil dengan alasan; manifest untuk harness; **pengecualian bulk query builder** untuk `database/seeders/Stress/**`.
- **Arsitektur front-end:** pengecualian builder menjadi aturan eksplisit (factory + guard murni boleh co-locate di `components/modules/builder/`).
- **Code splitting (frontend):** route-level lazy (`import.meta.glob` tanpa `eager`), vendor berat (Tiptap, chart.js, html5-qrcode, lottie) hanya di chunk pemakainya, komponen berat dimuat async, vendor bersama lewat `manualChunks`, prefetch selektif. Budget gate: entry ≤ 350 kB gzip, chunk page ≤ 200 kB gzip; ukuran before/after wajib dicatat.

---

## 2. Konteks proyek — `CONTEXT.md` (baru)

Satu dokumen di root, 16 bagian: produk, stack, topologi runtime, peta direktori (68 controller, 77 service, 57 page), glosarium enum per domain, alur utama, skema data, route map, permission/middleware, storage/queue/mail, kontrak frontend, testing, CI/CD, jebakan terverifikasi, indeks dokumen, ringkasan aturan.

Bagian yang paling sering dibutuhkan sesi baru:

- §6.2 alur pendaftaran (guard 5 lapis → single/tim/bundle → email QR → attendance).
- §6.3 scan QR + feed lintas meja (cooldown 2 s, rate limit 60–240/menit).
- §7 ERD + catatan unique/kardinalitas.
- §14 dua belas jebakan (dua modul broadcast menumpuk, `email_logs.broadcast_id` tanpa migrasi aktif, partial unique hanya di SQLite/PG, tidak ada worker/scheduler di compose, dst.).

---

## 3. Stress harness — `scripts/stress/`

| File                   | Fungsi                                                                                                                                                                                                                             |
| ---------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `run.sh`               | Orkestrator `--seed/--generate/--k6/--queries/--all/--yes`; seluruh PHP via `docker compose exec -T app php`; guard `APP_ENV=production` + guard service `app` harus berjalan; report ke `storage/app/stress/reports/<timestamp>/` |
| `generate-targets.php` | `route:list --json` + manifest → `targets.json`; hanya GET; write dipisah dan default tidak dijalankan                                                                                                                             |
| `k6/lib.js`            | Login per VU (cookie `XSRF-TOKEN` → header `X-XSRF-TOKEN`), `http.batch`, metrik `http_429_rate`/`http_5xx_rate`, threshold `http_req_failed<5%`, `p(95)<2000`, `checks>95%`                                                       |
| `k6/smoke.js`          | 1 VU / 1 iterasi sebagai gate sebelum beban besar                                                                                                                                                                                  |
| `k6/api-flood.js`      | Ramping 0→50→200→500→0, ±6 menit, menyapu seluruh target GET                                                                                                                                                                       |
| `k6/spike.js`          | 0→800 VU dalam 30 detik, tahan 1 menit                                                                                                                                                                                             |
| `summary.php`          | Tabel ringkasan dari summary-export k6                                                                                                                                                                                             |
| `README.md`            | Prasyarat, urutan pakai, arti metrik, 429 sebagai sinyal, peringatan seed                                                                                                                                                          |

Default **read-only**. Endpoint tulis hanya jalan dengan `STRESS_ENABLE_WRITES=1` dan sengaja tidak menyiapkan payload valid (hanya menguji ketahanan middleware/throttle).

### 3.1 Runner PHP di container (podman)

`run.sh` tidak memanggil `php` host. Deteksi runtime: **podman** (default proyek) → docker, lalu semua perintah dijalankan sebagai `podman exec -i -w /app d_form_app php <perintah>`. Nama container bisa di-override lewat `STRESS_CONTAINER` (mis. `prod_d-form-app-core`). Container diverifikasi berjalan (`podman inspect -f '{{.State.Running}}'`) dengan pesan error yang menyertakan perintah perbaikannya. Path report diterjemahkan ke path container (`/app/scripts/stress/reports/<timestamp>`) karena `summary.php` juga dieksekusi di container. `k6` tetap di host karena tidak ada di image. Aturan proyeknya masuk `AGENTS.md`: semua perintah PHP lewat container.

Report ditulis ke `scripts/stress/reports/` (bukan `storage/app`) karena direktori `storage/` milik uid lain di mesin dev sehingga host tidak bisa `mkdir`; `scripts/stress/reports/` di-ignore lewat `scripts/stress/.gitignore`.

Verifikasi nyata di mesin dev: `--generate` → 50 GET / 28 write / 79 dilewati; `--queries` → 18 query jalan di MySQL dengan `EXPLAIN ANALYZE` (2 broadcast di-skip karena `FEATURE_BROADCAST=false`), `events_list_published` dan `recruitment_report_by_division` terdeteksi `access=ALL`.

---

## 4. Dataset stress — `stress:seed`

| Aspek                | Nilai                                                                                                                                                                                                                                                             |
| -------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Signature            | `stress:seed {--scale=1000000} {--force}`; menolak production; berhenti bila tabel terisi tanpa `--force`                                                                                                                                                         |
| Skala                | `--scale` 1..5.000.000, default 1.000.000 baris per tabel domain                                                                                                                                                                                                  |
| Relasi               | UUID deterministik `uuid5('dform:<entity>:<i>')` — anak menunjuk induk tanpa menyimpan 1 juta ID di memori                                                                                                                                                        |
| Insert               | Bulk query builder per chunk (3.000 MySQL / 500 SQLite; recruitment 2.000), tanpa Eloquent per baris, tanpa faker                                                                                                                                                 |
| Cakupan event-domain | `users`, `events`, `forms`, `form_fields`, `form_answers`, `event_attendances`, `email_logs`, `email_datasets`, `email_dataset_recipients`, `email_broadcasts`, `email_broadcast_recipients`, `email_broadcast_attachments`                                       |
| Cakupan recruitment  | `recruitment_applications`, `documents`, `screenings`, `interviews`, `activity_logs` @1jt; `attendances` 700rb, `evaluations` 500rb, `final_decisions` 400rb, `feedbacks` 300rb, `correction_requests` 200rb; referensi kecil (50 periode, 10 divisi, 5.000 sesi) |
| Manifest             | `storage/app/stress/manifest.json` — `admin_email`, `admin_password`, `event_slug`, `event_id`, `form_id`, `form_answer_id`, `registration_code`, `scan_desk_id`, `broadcast_id`, `scale`, `targets[]`                                                            |
| `--force`            | TRUNCATE 28 tabel (12 event-domain + 16 recruitment) dengan FK check dimatikan sementara, dipulihkan di `finally`                                                                                                                                                 |
| Estimasi             | ±20 juta baris, 6–8 GB, 20–50 menit (belum diukur nyata)                                                                                                                                                                                                          |

---

### 4.1 Perbaikan pasca-uji pertama

Uji pertama `run.sh --seed` gagal dengan `TypeError: Command::call(): Argument #2 must be of type array, false given`. Penyebab: `SeedStressDatasetCommand` memakai signature `Seeder::call($class, $silent, $params)`, padahal di dalam Command signature-nya `call(string $command, array $arguments)`. Perbaikan: helper `runSeeder()` yang me-resolve seeder dari container lalu `setContainer()/setCommand()/run($scale)`.

Bukti setelah perbaikan (`--scale 2000`, 6,4 detik):

| Tabel                                                                                                                      | Baris                     |
| -------------------------------------------------------------------------------------------------------------------------- | ------------------------- |
| users, events, forms, form_fields, form_answers, event_attendances, email_logs, email_datasets, email_broadcast_recipients | 2.000 masing-masing       |
| recruitment_applications, documents, screenings, interviews, activity_logs                                                 | 2.000 masing-masing       |
| recruitment_attendances / evaluations / final_decisions / feedbacks                                                        | 1.400 / 1.000 / 800 / 600 |

Integritas: 9 pemeriksaan relasi (forms→events, fields→forms, answers→forms, answers→users, attendance→event/answer, interviews→applications, evaluations→interviews, attendance→applications, final_decisions→applications, feedbacks→applications, screenings→applications) semuanya **0 baris yatim**; `registration_code` dan `registration_number` tanpa duplikat. HTTP: `/`, `/events`, `/auth/login` → 200, `/admin/scan` → 302 (auth).

### 4.2 Akar masalah seed skala 1 juta "hang" (bukan hang)

Saat menjalankan skala 1.000.000, progres tampak berhenti. Investigasi (bukan tebakan):

| Gejala terukur                                | Nilai                                                                                                                 |
| --------------------------------------------- | --------------------------------------------------------------------------------------------------------------------- |
| Laju `recruitment_applications`               | 348.000 → 352.000 baris / 20 detik = **200 baris/detik**                                                              |
| Proyeksi sisa ±7,6 juta baris                 | **±10 jam**                                                                                                           |
| `innodb_buffer_pool_size`                     | **128 MB** (default MySQL), sementara `recruitment_applications` saja 611 MB (239 MB data + 372 MB indeks, 11 indeks) |
| `Innodb_buffer_pool_reads` (fisik)            | +21.712 per 15 detik (12,3% miss)                                                                                     |
| `Innodb_data_fsyncs` + `Innodb_os_log_fsyncs` | +7.278 + 6.696 per 15 detik (`flush_log_at_trx_commit=1`, `sync_binlog=1`)                                            |
| Lock wait / CPU PHP                           | 0 / ±2% → **I/O bound**, bukan deadlock atau CPU                                                                      |

Penyebab: setiap insert 2.000–3.000 baris menyentuh ±22.000 entri indeks; dengan buffer pool 128 MB
halaman indeks selalu di luar cache sehingga tiap baris memicu physical read, ditambah fsync per commit.

Perbaikan (di `stress:seed`, dipulihkan otomatis setelah selesai):

- `innodb_flush_log_at_trx_commit=2`, `sync_binlog=0` (durability diturunkan selama seed saja).
- `foreign_key_checks=0`, `unique_checks=0` untuk sesi seed (urutan insert sudah induk→anak; sudah diverifikasi 0 baris yatim).
- `innodb_buffer_pool_size=2G` selama seed.
- Flag `--no-tune` untuk melewatinya; kegagalan menaikkan buffer pool hanya memunculkan warning, seed tetap jalan.
- Progres dicetak maksimal tiap 2 detik dengan **baris/detik + ETA** (sebelumnya tiap 10% → terlihat hang).

Hasil sementara (skala 50.000, 12 tabel domain + recruitment, 90,2 detik ≈ 11.000 baris/detik) — 9× lebih cepat dari laju 1.220 baris/detik pada run yang dihentikan.

### 4.3 Dua seed serentak = `Duplicate entry ... PRIMARY`

Uji 1 juta baris kedua gagal di chunk `516001` dengan
`SQLSTATE[23000]: Duplicate entry 'c3434c4f-b0a4-50f0-ba54-ea7f0a5677ed' for key 'users.PRIMARY'`.

Bukti: id yang bentrok identik dengan `StressDatasetSeeder::uuidFor('user', 516001)` — tepat baris pertama
sebuah chunk (516001 = 1 + 172×3000) — dan saat itu ada proses kedua `stress:seed --scale=1000000 --force`
berjalan bersamaan. Dua penulis memakai UUID5 deterministik yang sama, jadi chunk yang sama ditulis dua kali;
`--force` masing-masing juga saling menghapus data. Bukan bug generator id.

Perbaikan: `stress:seed` memegang lock Redis `stress:seed` (TTL 6 jam) sebelum validasi `--scale` dan sebelum
truncate; seed kedua berhenti dengan pesan jelas. Kalau proses mati tanpa melepas lock, TTL atau
`Cache::lock('stress:seed')->forceRelease()` mengatasinya.

Verifikasi guard order (tanpa menyentuh data): dengan lock dipegang → `stress:seed --scale=0` keluar dengan
pesan "Seed stress lain masih berjalan" (exit 1); setelah lock dilepas → jatuh ke validasi scale. Artinya
lock menahan sebelum jalur destruktif.

Bukti tuning pada skala 1 juta (fase users sebelum insiden): **14.778–18.358 baris/detik** dibanding
±1.220 baris/detik pada run default — 12–15× lebih cepat.

### 4.4 Cleanup tuning bisa menimpa seed lain (race `SET GLOBAL`)

Setelah seed pertama gagal di fase users, seed lain yang sudah berjalan turun lagi ke ±266 baris/detik:
proses yang mati menjalankan `finally` dan memulihkan `innodb_flush_log_at_trx_commit=1`, `sync_binlog=1`,
`innodb_buffer_pool_size=128M` — padahal proses kedua masih menulis dengan setelan tersebut.

Perbaikan: closure restore kini membandingkan nilai saat ini dengan nilai yang dia pasang; kalau pihak lain
sudah mengubahnya, nilai itu **tidak** ditimpa. Ditambah lock `stress:seed`, dua seed tidak bisa lagi
berbagi setelan global secara bersamaan.

Bukti live pada seed yang sedang berjalan (skala 1 juta, tabel `recruitment_applications`):

| Kondisi                                                        | Laju              |
| -------------------------------------------------------------- | ----------------- |
| Sebelum boost (`flush=1`, `sync_binlog=1`, buffer pool 128 MB) | 266 baris/detik   |
| Sesudah `SET GLOBAL` flush=2, sync_binlog=0, buffer pool 2 GB  | 2.700 baris/detik |

---

## 5. Benchmark query — `stress:queries`

`stress:queries {--limit=50} {--json} {--only=}` menjalankan 20 query yang mencerminkan service aktif: daftar event, roster registran (+count), paginasi submission, cek duplikat `FormAccessGuard`, `ScanStreamFeed::since` (event & recruitment), empat query `EventReportingQuery`, daftar applicant, funnel + per-divisi recruitment, interview hari ini per interviewer, waiting pool, dataset broadcast (otomatis masuk `skipped` bila `FEATURE_BROADCAST=false`).

Output: tabel `Query | SQL ringkas | Median ms | Rows examined | Access | Warning`, tabel N+1 (`n_plus_one`), dan daftar `skipped`. `EXPLAIN ANALYZE` dipakai bila driver mendukung, fallback `EXPLAIN` tabular. Read-only ditegakkan lewat `assertReadOnly()`.

---

## 6. Verifikasi yang dijalankan

| Pemeriksaan                                                     | Hasil                                                                                                                                                         |
| --------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `php -l` (9 file PHP baru)                                      | Bersih                                                                                                                                                        |
| `vendor/bin/pint --test` (8 file)                               | PASS (PSR-12)                                                                                                                                                 |
| `bash -n scripts/stress/run.sh`                                 | Bersih                                                                                                                                                        |
| Guard container (dijalankan)                                    | Tanpa service `app` → `ERROR: service 'app' belum berjalan — jalankan: docker compose up -d` (bukan crash)                                                    |
| `node --check` (4 skenario k6)                                  | Bersih                                                                                                                                                        |
| `route:list --json`                                             | 156 rute / 90 GET; 4 target manifest resolve (`dashboard.events.show`, `dashboard.events.forms.submissions`, `dashboard.scan.index`, `dashboard.users.index`) |
| `generate-targets.php` (dijalankan nyata)                       | 50 target GET, 28 write dipisah, 79 dilewati (berparameter/vendor)                                                                                            |
| Logika seeder (reflection, scale 20.000, tanpa DB)              | UUID deterministik, `registration_number` unik, `(period,nim)` unik, urutan waktu konsisten, cohort interview/evaluasi/final/feedback valid                   |
| `npx prettier --check` (`AGENTS.md`, `CONTEXT.md`, `README.md`) | Bersih                                                                                                                                                        |

Catatan: host pengembangan tidak punya `php`/`docker`/`k6`, sehingga lint dijalankan lewat image `dunglas/frankenphp:php8.4-alpine` (podman) dan **tidak ada** query nyata yang dieksekusi.

## 7. Belum dijalankan / risiko

- Seed nyata ke MySQL, integritas FK/unique, dan throughput insert belum terukur.
- Skenario k6 belum pernah dijalankan terhadap aplikasi hidup; dua asumsi perlu dicek saat run pertama: path login `/auth/login` dan encoding cookie `XSRF-TOKEN`.
- `EXPLAIN ANALYZE` pada MariaDB 10.11 (prod) belum diuji; jalur utama baru MySQL.
- `--force` bersifat destruktif terhadap database yang aktif — pakai database khusus stress.
- Angka baris untuk `recruitment_interviews` tepat 1 juta hanya bila `scale` habis dibagi 10.

---

## 8. Issue & commit

| Item                                                        | Issue                               | Commit          |
| ----------------------------------------------------------- | ----------------------------------- | --------------- |
| Aturan proyek `AGENTS.md` (14 aturan baru, dedupe, hapus 2) | tanpa nomor tiket (konvensi folder) | belum di-commit |
| `CONTEXT.md` baru                                           | tanpa nomor tiket                   | belum di-commit |
| Stress harness + dataset + benchmark query                  | tanpa nomor tiket                   | belum di-commit |

Catatan: `.gitignore` (21 Okt 2026) sengaja mengabaikan `.opencode`, `scripts/`, `AGENTS.md`, `CONTEXT.md`, `database/seeders/Stress/*`, dan `app/Console/Commands/Stress` atas keputusan pemilik repo. Artefak tersebut tetap ada di mesin lokal tetapi tidak ikut commit; untuk memasukkannya sementara, pakai `git add -f <path>`.

---

## 9. Checklist sebelum merge

- [ ] `php artisan migrate` di database khusus stress, lalu `bash scripts/stress/run.sh --seed --scale 50000 --yes` sebagai smoke.
- [ ] `bash scripts/stress/run.sh --generate` → cek `targets.json` memuat target `/admin/*` yang diharapkan.
- [ ] Jalankan `--k6` dengan `STRESS_EMAIL`/`STRESS_PASSWORD` dari manifest; konfirmasi login sukses dan `http_429_rate` muncul sebagai sinyal limiter.
- [ ] `php artisan stress:queries --limit=50` → catat query dengan `Access=ALL` atau median > 100 ms sebagai temuan optimasi.
- [ ] Pastikan `storage/app/stress/**` dan `scripts/stress/targets.json` tidak ikut ter-commit.
- [ ] Commit per fitur (aturan baru): aturan+doc, lalu harness+seeder, terpisah bila memudahkan review.
