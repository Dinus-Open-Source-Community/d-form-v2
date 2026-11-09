# Sapto Changes — 9 November 2026 (BE-P2-003)

## Ringkasan

`RecruitmentPeriodController::qrEligibleCount()` dan `qrSentCount()` serta
predikat penerima di `RecruitmentQrBulkService` disalin di empat tempat dan
masing-masing memakai subquery `email_logs` yang hanya punya index tunggal.
Audit mengukur **1,675 s** per COUNT pada snapshot MySQL.

Setelah patch:

- dua index komposit ditambahkan: `email_logs_rec_app_type_status_idx`
  `(recruitment_application_id, notification_type, status)` dan
  `rec_apps_period_stage_cancelled_idx`
  `(recruitment_period_id, stage, cancelled_at)`; `EXPLAIN` membuktikan keduanya
  dipakai sebagai covering lookup;
- predikat eligibility kini satu sumber kebenaran
  (`RecruitmentQrEligibilityQuery`) yang dipakai controller dan bulk service,
  sehingga `qr_eligible_count`/`qr_sent_count` dijamin sama dengan
  `dispatched`/`skipped`;
- halaman menghitung kedua angka dalam satu pass setengah biaya
  (`counts()` = satu probe EXISTS + satu base count).

Target AC `count < 200 ms` **tidak terpenuhi pada buffer pool dev 128 MB**;
lihat §Waiver. Dengan pool ≥ 1 GB query yang sama terukur 27–47 ms.

| Issue | Commit |
| ----- | ------ |
| BE-P2-003 | `15e9f97` — `perf: index and deduplicate QR eligibility query` |

Laporan lengkap: `docs/big-changes/patching-ticket/BE-P2-003.md`.

## Akar masalah (bukti)

- `app/Http/Controllers/Dashboard/Recruitment/RecruitmentPeriodController.php`
  (sebelum) — `qrEligibleCount()`/`qrSentCount()` menyalin base filter +
  `NOT EXISTS`/`EXISTS` `email_logs` (`notification_type`, `status`).
- `app/Services/Recruitment/RecruitmentQrBulkService.php` (sebelum) — query
  penerima dan hitungan `skipped` menyalin predikat yang sama.
- `SHOW INDEX FROM email_logs` sebelum patch: `email_logs_rec_app_idx`
  (tunggal), `email_logs_notification_type_index` (tunggal), tanpa composite
  application + type + status.
- `EXPLAIN ANALYZE` sebelum patch (dataset 1 juta baris, 128 MB buffer pool):
  **1,98–2,20 s**; outer query tidak covering, email_logs memakai index tunggal.

## Perubahan

| File | Perubahan |
| ---- | --------- |
| `database/migrations/2026_11_09_000002_add_qr_eligibility_indexes.php` (baru) | Dua index komposit, idempoten lewat `Schema::hasIndex()`; `down()` index-only. |
| `app/Services/Recruitment/RecruitmentQrEligibilityQuery.php` (baru) | Satu sumber predikat: `base()`, `eligible()`, `alreadySent()`, `counts()`, `countEligible()`, `countAlreadySent()`. |
| `app/Http/Controllers/Dashboard/Recruitment/RecruitmentPeriodController.php` | `qrEligibleCount()`/`qrSentCount()` dihapus; `show()` memakai `counts()` sekali untuk kedua prop. |
| `app/Services/Recruitment/RecruitmentQrBulkService.php` | Penerima memakai `eligible()`/`base()`, `skipped` memakai `countAlreadySent()`; urutan, delay, baris `email_logs`, job, activity log tidak berubah. |
| `tests/Feature/Recruitment/RecruitmentQrEligibilityQueryTest.php` (baru) | Index + urutan kolom, count == service (`dispatched`/`skipped`), matriks queued/failed/other-type/attendance/cancelled/screening/other-period/"sent lalu queued", prop Inertia literal, penjaga `EXPLAIN` MySQL read-only. |
| `tests/e2e/recruitment-qr-eligibility.desktop.spec.ts` (baru) | Browser: render count, kirim QR, kirim ulang `include_sent`, anomali input, user non-recruitment, waktu navigasi. |

Tidak ada perubahan route, response contract, enum, atau otorisasi.

## Keputusan desain

| Keputusan | Alasan / alternatif yang ditolak |
| --- | --- |
| Index `(recruitment_application_id, notification_type, status)` | Subquery anti/semi-join memakai ketiganya sebagai equality; `application_id` pertama karena itu kolom korelasi. Menjadi covering (`Using index`). |
| Index kedua `(recruitment_period_id, stage, cancelled_at)` | Tanpa `cancelled_at` di index, MySQL harus membaca ±7,8 ribu baris clustered untuk filter `cancelled_at IS NULL` — diukur 7,30 s vs 1,53 s. Bukan over-indexing. |
| Dedup predicate ke satu kelas | Empat salinan rawan drift antara angka UI dan penerima aktual; tiket mensyaratkan count == bulk service. |
| `counts()` = `sent` + (`base` − `sent`) | Set komplementer; menghindari probe NOT EXISTS kedua. Diukur ~1,3 s vs ~2,6 s untuk dua count terpisah. |
| Cache/denormalisasi count ditolak | Menambah invalidasi dan sumber drift baru; akar masalah adalah index + query, bukan frekuensi hitung (gating per tab ditangani tiket `BE-P2-005`). |

## Hasil pengukuran (dataset stress ±1 juta baris, MySQL 9)

| Skenario | Sebelum | Sesudah |
| --- | --- | --- |
| `count_eligible` (128 MB pool) | 1,98–2,20 s | 0,85–0,95 s |
| `count_eligible` (pool 1 GB, hangat) | — | 27–47 ms |
| Tanpa index email_logs baru | 2,93 s | — |
| Tanpa index applicant baru | 7,30 s | — |

`EXPLAIN` sesudah patch: `recruitment_applications` `key=rec_apps_period_stage_cancelled_idx`,
`ref=const,const,const`, `Extra=Using index`; `email_logs`
`key=email_logs_rec_app_type_status_idx`, `ref=<outer.id>,const,const`,
`Extra=Using index; FirstMatch(...)`.

Index: `email_logs_rec_app_type_status_idx` ±106 MB,
`rec_apps_period_stage_cancelled_idx` ±103 MB (1,16 juta baris; UUID char(36)
mendominasi ukuran). Build ±9,4 s pada 1 juta baris.

Dampak write: benchmark 20.000 baris/ronde dengan/tanpa index pada tabel scratch
memberi delta yang tidak konsisten antar-run (+32,6%, −25,5%, −0,7%, −23,1%)
karena instance dev dipakai bersama lane lain (load average 9–14). Status:
`Needs verification`; perkiraan teoritis +90–108 byte/baris. Ukur ulang di
instance terisolasi sebelum `Done`.

## Waiver

> **BE-P2-003 — waiver AC "count < 200 ms pada dataset uji".** Status: **tidak
> terpenuhi pada buffer pool 128 MB; terpenuhi pada pool yang memadai.**
> Residual adalah kapasitas buffer pool, bukan bentuk query.
>
> Dataset: `recruitment_applications` 955.659 baris; `email_logs` 1.159.370;
> `recruitment_attendances` 732.832; database ≈ 15,9 GB. MySQL 9.0,
> `innodb_buffer_pool_size = 128 MB`, container dev, lane audit lain ikut
> mengakses instance yang sama. Periode terukur: 4.000 baris tahap interview.
>
> Plan (EXPLAIN dari SQL asli `countEligible()`): kedua langkah memakai index
> baru sebagai covering lookup. Timings (median 5 run): `count_eligible`
> 1,30 s (cold 2,96 s); `count_already_sent` 1,35 s. `Innodb_buffer_pool_reads`
> naik ≈15,7 ribu page (~245 MB) per count → I/O fisik. Kedua index baru total
> 209 MB terhadap pool 128 MB dan database 15,9 GB, sehingga tidak bisa resident.
> Dengan pool ≥ 1 GB query yang sama 27–47 ms.
>
> Nilai index (sebelum/sesudah pada instance yang sama): tanpa
> `email_logs_rec_app_type_status_idx` anti-join jatuh ke index tunggal dan
> kehilangan `Using index` → 2,93 s; tanpa
> `rec_apps_period_stage_cancelled_idx` optimizer memilih index non-covering
> `(period, stage, submitted_at)` → 7,30 s, vs 1,53 s dengan keduanya.
>
> Kriteria di-waive pada 128 MB sebagai batas kapasitas environment; wajib diukur
> ulang setelah buffer pool produksi ditentukan (rekomendasi ≥ 1 GB). Bila satu
> periode melebihi ~50 ribu pelamar, urutan tindak lanjut: (1) `counts()` satu
> pass (sudah diterapkan), (2) drop index redundan (`HYG-004`), (3) precomputed
> count.
>
> **Dampak write:** `Needs verification` (lihat §Hasil pengukuran).

## Verifikasi

Lihat `docs/big-changes/patching-ticket/BE-P2-003.md` §Verifikasi untuk perintah,
exit status, jumlah test, `EXPLAIN`, dan hasil E2E Playwright (happy path +
anomali input). Ringkas: feature test eligibility + parity hijau (6 passed, 32
assertions), migration round-trip SQLite hijau, Pint hijau, EXPLAIN guard MySQL
berjalan (bukan skip) di container dev, dan E2E browser 6 passed / 1 flaky
(lulus pada retry) / 0 failed termasuk kirim QR, kirim ulang `include_sent`,
`tab=bogus`, param GET liar, JSON 422, UUID 404, dan member non-recruitment 302.

## Dampak & risiko

- `email_logs` write path mendapat satu index tambahan (±106 MB); `email_logs`
  ditulis beberapa kali per applicant (queued → sent/failed), bukan jalur tulis
  panas seperti tabel domain lain.
- `recruitment_applications` write path mendapat satu index (±103 MB); tabel ini
  ditulis sekali per pelamar + update stage.
- `rec_apps_period_stage_idx` kini strict prefix dari index baru; `email_logs_rec_app_idx`
  strict prefix dari composite baru. Keduanya kandidat drop → `HYG-004`.
- Index baru membuat query daftar penerima bulk (`orderBy(submitted_at)`)
  memakai filesort terbatas pada kandidat (±4 ribu baris/periode). Tidak
  blocking; opsi menambah `submitted_at` sebagai kolom ke-4 dicatat di laporan.
- Rollback: `migrate:rollback --step=1` menghapus dua index; tidak ada perubahan
  data dan tidak ada query yang bergantung pada index untuk benar.
- Temuan di luar scope dicatat sebagai tiket baru: `BE-P2-017` (kirim ganda saat
  baris masih `queued`), `BE-P2-018` (rate limit/otorisasi `send-qr`),
  `INFRA-P3-022` (`migrate --force --isolated`), `HYG-004` (index redundan).
