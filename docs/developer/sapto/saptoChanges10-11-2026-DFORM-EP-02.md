# Sapto Changes — 10 November 2026 (EP-02)

## Ringkasan

EP-02 melanjutkan remediation security/data-protection pada worktree yang berisi perubahan dari beberapa lane. Full Laravel/frontend checks lulus; subset E2E auth/report/export dan QR eligibility/harness juga lulus pada SQLite terisolasi. Multi-aktor E2E memakai cookie jar kosong dan memverifikasi identitas sesi agar state super-admin default tidak membuat assertion 403 palsu. EP-02 **belum selesai**: approval P0/P1, secret scan, verifikasi production, batas konsistensi SMTP→database, dan pengukuran skala bulk QR masih terbuka.

| Issue                                                                                                                                                                                                                           | Commit                                 |
| ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | -------------------------------------- |
| EP-02 (`AUDIT-P0-001`, `AUDIT-P1-001`, `BE-P1-007`, `BE-P1-010`, `BE-P2-015`, `BE-P2-020`, `BE-P3-010`, `BE-P3-016`, `BE-P3-017`, `AUDIT-P2-012`, `AUDIT-P2-014`, `AUDIT-P2-015`, `AUDIT-P2-016`, `AUDIT-P2-019`, `SEC-P3-013`) | Belum ada; perubahan masih di worktree |

Laporan lengkap: [`docs/big-changes/patching-ticket/EP-02/patching-ticket.md`](../../big-changes/patching-ticket/EP-02/patching-ticket.md).

## Perubahan yang sudah ada di worktree

- Otorisasi export interview berdasarkan kepemilikan periode dan permission, serta rate limit untuk pencarian admin, send-QR, dan endpoint export sensitif.
- Policy broadcast dan periode recruitment dirapikan; proteksi QR-status dan reservasi QR mengurangi risiko akses lintas periode dan duplicate send.
- CSV output dipusatkan pada writer bersama. Sanitasi formula injection mengenali whitespace termasuk NBSP UTF-8; audit export hanya menyimpan metadata aman, bukan isi baris PII.
- Redaksi exception dipakai pada jalur reporting, `failed_jobs`, OTP, broadcast, dan mail error agar pesan bebas tidak menyimpan PII. Job email yang diperiksa memakai `ShouldBeEncrypted`.
- Resend tracking mempertahankan token lama ketika dispatch atau delivery gagal dan mengaktifkan hash token baru hanya setelah pengiriman sukses.
- Regression tests ditambahkan/diperluas untuk policy, export, rate limit, redaksi, failed-job reporting, resend token, CSV writer/sanitizer, QR eligibility, dispatch recovery, dan activity-log projection. Pada E2E, status QR `queued` diperlakukan sebagai in-flight sesuai backend; limiter produksi tidak dilonggarkan, cache yang dibersihkan hanya cache lokal E2E, dan test `H-ownership-window` memakai run ID dinamis.
- `auth.spec.ts` tidak lagi memakai password stress user literal. Test login sukses memakai admin seeded dari environment yang diwajibkan global setup.
- Folder `docs/adr/` dibuat untuk ADR pertama keputusan durable QR outbox; folder `patching-ticket/EP-02/` dibuat untuk laporan per-tiket sesuai aturan dokumentasi proyek.

Tidak dilakukan perubahan pada file backup secret atau `database/seeders/UserSeeder.php`. Tidak ada credential yang dirotasi, migration destruktif yang dijalankan, atau perubahan di production.

## Verifikasi

| Pemeriksaan                                                          | Hasil                                                                             |
| -------------------------------------------------------------------- | --------------------------------------------------------------------------------- |
| `podman exec -i -w /app d_form_app php artisan test`                 | **1.353 passed, 9 skipped, 2 risky**                                              |
| PHPUnit issue detail                                                 | **2 risky non-asserting EP-04 probes; 1 metadata doc-comment deprecation**        |
| `podman exec -i -w /app d_form_app ./vendor/bin/pint --test`         | PASS; 749 file                                                                    |
| `npm run lint`, `npm run typecheck`, `npm run test:unit` (container) | PASS; 306 unit tests                                                              |
| `npm run test:e2e:types` (host)                                      | PASS                                                                              |
| Playwright auth/report/export regression subset (current)            | **13 passed**, setup **1 passed**                                                 |
| Playwright QR eligibility + harness subset (current)                 | **28 passed**, setup **1 passed**                                                 |
| Playwright CI runtime subset (checkpoint sebelumnya)                 | **34 passed**: setup 1, desktop 28, form submission 2, mobile 3                   |
| Playwright CI authz subset (checkpoint sebelumnya)                   | **5 passed**, setup included                                                      |
| `npm run build` (container)                                          | PASS; entry gzip **15,77 kB**, chunk terbesar gzip **145,58 kB**                  |
| Composer audit online (current recheck)                              | Packagist DNS timeout; tidak dapat memverifikasi advisory terbaru                 |
| Composer audit cache (`--ignore-unreachable`)                        | Tidak ada advisory terlapor dari cache; 3 package abandoned                       |
| Composer audit pada checkpoint sebelumnya                            | Command CI dan `--no-dev` lulus tanpa advisory; perlu recheck saat jaringan pulih |
| `gitleaks detect --no-git`                                           | Belum berjalan; executable tidak tersedia di host maupun container                |
| `git diff --check`                                                   | PASS setelah perubahan source saat ini; ulangi setelah dokumentasi final          |

**Reproduced pada checkpoint sebelumnya:** homepage lokal berhasil dibuka; login dengan kredensial sintetis yang salah memberi pesan umum dan respons `429`; guest yang membuka route QR-status diarahkan ke login. Browser check tersebut tidak diulang pada validasi kali ini.

## Gate dan residual

| Item                            | Evidence / status                                                                                                                                                                                                                                                                                                                                                                                           |
| ------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Playwright E2E                  | **Confirmed untuk gate CI lokal** — auth 9, runtime 34, dan authz 5 test lulus pada SQLite test terisolasi; QR eligibility + harness 29 test lulus pada checkpoint sebelumnya. Full suite tidak selesai pada percobaan sebelumnya (runner timeout dan PHP 256M fatal; tidak ada artefak yang mengisolasi test pemicu). CI sengaja memakai subset karena suite penuh bergantung pada stress data/lingkungan. |
| Secret scan                     | **Needs verification** — `gitleaks` tidak tersedia; secret scan belum berjalan.                                                                                                                                                                                                                                                                                                                             |
| Audit dependency                | **Confirmed:** command CI `composer audit --locked --no-interaction --abandoned=report` lulus tanpa advisory; tiga package abandoned tetap dilaporkan dan mengikuti tiket dependency terpisah. Audit `--no-dev` juga lulus.                                                                                                                                                                                 |
| Driver failed-job production    | **Needs verification** — provider custom mengharuskan `database-uuids`; konfigurasi instance production belum diverifikasi.                                                                                                                                                                                                                                                                                 |
| Scheduler QR production         | **Needs verification** — scheduler terdaftar pada aplikasi; jumlah instance/leader tunggal di production belum diketahui.                                                                                                                                                                                                                                                                                   |
| Resend SMTP→DB crash window     | **Residual, belum tertutup** — SMTP dapat menerima email sebelum hash token baru tersimpan. Bila worker mati di antara kedua operasi, email baru dapat berisi token belum aktif; token lama tetap aktif.                                                                                                                                                                                                    |
| QR bulk scale                   | **Needs verification** — service memuat seluruh kandidat dengan `get()` lalu melakukan reservasi per kandidat; memori O(N) dan query/transaction count O(N). Belum diukur pada skala production.                                                                                                                                                                                                            |
| QR outbox interrupted `sending` | **Trade-off fail-closed** — status ambigu tidak otomatis dikirim ulang untuk mencegah duplikasi; perlu rekonsiliasi dan resend manual.                                                                                                                                                                                                                                                                      |
| `AUDIT-P0-001` / `AUDIT-P1-001` | **Open; approval manusia diperlukan** — backup tracked berisi secret dan seeder default credential tetap tracked; tidak ada rotasi/penghapusan/perubahan seeder.                                                                                                                                                                                                                                            |

### Trade-off

QR bulk memakai intent outbox persisten dan batas dispatch 100 item per poll. Worker mengklaim status `sending` sebelum kontak SMTP; kegagalan yang diketahui sebelum SMTP menerima pesan dapat kembali ke queue, sedangkan crash dengan hasil delivery ambigu tidak otomatis di-retry. Ini mengurangi duplicate-send tetapi bukan exactly-once: state yang tertahan harus direkonsiliasi lalu di-resend secara sadar. Untuk resend tracking, SMTP dan update hash token tetap tidak dapat dibuat atomik tanpa dukungan idempotency/provider acknowledgement; residual itu tetap terbuka. Alternatif reclaim otomatis untuk `sending` ditolak karena dapat mengirim duplikat.

## Tindak lanjut

1. Reproduksi full E2E hanya bila fixture stress/lingkungan yang diperlukan tersedia; pertahankan DB terisolasi dan identifikasi test penyebab memory/timeout sebelum menyimpulkan seluruh suite hijau. Gate CI yang ditetapkan saat ini sudah lulus secara lokal.
2. Jalankan `gitleaks detect --no-git` saat executable tersedia.
3. Minta operator memverifikasi driver failed-job dan konfigurasi scheduler production tanpa menyalin secret ke dokumen/log.
4. Minta keputusan eksplisit untuk `AUDIT-P0-001` dan `AUDIT-P1-001`; jangan menghapus backup, merotasi secret, atau mengubah seeder melalui jalur lain.
5. Ukur bulk QR pada dataset besar dan putuskan apakah perlu pagination/keyset queue campaign; jangan melakukan refactor tanpa benchmark dan regression tests.
6. Tentukan apakah residual resend SMTP→DB diterima atau perlu provider idempotency/acknowledgement; jangan klaim exactly-once.
7. Status Jira tidak diubah. Agent hanya boleh mengajukan `In Progress → In Review`; penutupan `Done` oleh manusia.
8. Commit/push ditahan sampai blocker security Critical/High mendapat resolusi/waiver manusia dan gate yang tersedia diulang.

## Verifikasi lanjutan — 11-11-2026

Empat lane verifikasi + review `paladin`/`oracle` menutup 19 defect nyata pada
pekerjaan di atas. Ringkas:

- **Critical:** sanitizer CSV menjadi O(n²) pada input attacker-controlled
  (120 KB = 1,64 s) → satu regex ber-anchor + window 4 KiB (**0,017 ms**),
  UTF-8 invalid fail-closed.
- **High:** `SafeExceptionReporter` menghapus seluruh stack trace dari log →
  kini `file`, `line`, ≤10 frame tanpa argumen. `sending` ambigu mem poisoning
  eligibility permanen → command `recruitment:reconcile-qr-outbox`. `queued`
  dead-end → probe stale + re-push aman. Bulk QR hanya mengirim 1–9 email tanpa
  scheduler (1 queued / 29 pending) → semua kandidat di-enqueue (30/30).
- **Medium:** audit export interview tidak pernah tersimpan di MySQL
  (`entity_id char(36)` vs scope 44 karakter, error ditelan); audit export scan
  tidak ada; audit export applicant hilang saat stream gagal; sanitizer hanya
  menutup ASCII+NBSP; `failed_jobs` generik menyimpan pesan ganda + trace
  berisi payload; `LogicException` di jalur kegagalan queue; `result` hilang
  dari allowlist activity log; pencabutan `periods.edit` tidak efektif untuk
  owner; regresi harness E2E (`E2E_NON_ADMIN_*` wajib global); timezone skew
  7 jam pada slot pointer QR; plan outbox (filesort, full scan anchor, race
  slot antar-period); recovery tanpa scheduler; test race/memory/EXPLAIN yang
  tidak menguji apa pun.

Perubahan kebijakan & infra:

- `docker/entrypoint.prod.sh`, `docker-compose.yml`, `.gitignore` **dikembalikan
  ke HEAD** atas keputusan owner; `AUDIT-P2-004` tetap `Blocked`, `BE-P3-010`
  tetap `Open`.
- `AUDIT-P0-001` dan `AUDIT-P1-001` tetap `Open` (rotasi credential dan
  keputusan history rewrite oleh manusia); seeder dan file backup tidak
  disentuh.

Verifikasi final:

| Perintah | Hasil |
| --- | --- |
| `php artisan test` | **1.455 passed, 12 skipped** (11.407 assertions) |
| MySQL race suite (`phpunit.race.xml`) | **57 passed, 1 skipped** (422 assertions) |
| Pint | PASS, **759 file** |
| Prettier / ESLint / `vue-tsc` / E2E types / Vitest | 0 / 0 / 0 / 0 / **306 passed** |
| `npm run build` + budget | PASS (entry ≤350 kB gzip) |
| Playwright desktop | **89 passed** (`--retries=0`) |
| Playwright form-submission + mobile | **2 + 3 passed** |
| `composer audit` | Needs verification — DNS timeout; manifest tidak berubah |

Status index `patching.md`: 12 tiket EP-02 → `In Review`; `AUDIT-P0-001`,
`AUDIT-P1-001`, `BE-P3-010` tetap `Open` dengan catatan keputusan owner.
Laporan lengkap: `docs/big-changes/patching-ticket/EP-02/patching-ticket.md`.
