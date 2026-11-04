# Sapto Changes — 04 November 2026 (BE-P1-006)

## Ringkasan

Portal tracking OpRec punya tiga mutation setelah sesi valid (`PUT /track`,
`POST /track/correction`, `POST /track/feedback`) yang tidak dibatasi lajunya.
Endpoint pertama menulis beberapa tabel + berkas, endpoint kedua membuat baris
koreksi + activity log + job email ke staff, endpoint ketiga menulis feedback.
Gate domain memang membatasi hasil akhir, tetapi request tetap diproses sampai
controller. Perbaikan menambahkan tiga named limiter per endpoint dengan kunci
sesi tracking (id aplikasi) dan fallback IP, sehingga kuota antar pendaftar
terpisah dan user di belakang NAT tidak saling memblokir.

| Issue     | Commit    |
| --------- | --------- |
| BE-P1-006 | `1f945e2` |

## Perubahan

- `config/recruitment.php`: blok `tracking_mutation_throttle` (`update` 12,
  `correction` 5, `feedback` 5 per menit) yang dapat disetel lewat
  `RECRUITMENT_TRACK_UPDATE_RATE_LIMIT`, `RECRUITMENT_TRACK_CORRECTION_RATE_LIMIT`,
  dan `RECRUITMENT_TRACK_FEEDBACK_RATE_LIMIT`.
- `app/Providers/AppServiceProvider.php`: registrasi `oprec-track-update`,
  `oprec-track-correction`, `oprec-track-feedback`; helper
  `recruitmentTrackingLimit()` memakai id aplikasi sesi tracking, fallback IP
  bila sesi belum/tidak valid.
- `routes/web/oprec.php`: `throttle:oprec-track-*` pada tiga route mutation.
- `tests/Feature/Recruitment/RecruitmentTrackingMutationThrottleTest.php` (baru):
  6 regression test (429 setelah kuota, data tidak tersimpan saat 429, gate
  domain tetap, isolasi antar sesi, fallback IP, deklarasi middleware route).
- `tests/e2e/recruitment-tracking-throttle.desktop.spec.ts` (baru): flood lewat
  browser berakhir 429 + `Retry-After`, anomali validasi, toast 429 di UI, dan
  sesi pendaftar lain pada IP sama tetap bisa menyimpan.
- `docs/big-changes/patching-ticket/BE-P1-006.md`: report dan evidence lengkap.

## Verifikasi

- Regression test baru: merah 6 failed (15 assertions) → hijau 6 passed
  (89 assertions).
- `tests/Feature/Recruitment`: 328 passed (2.277 assertions).
- Full backend suite: 883 passed, 7 skipped (5.656 assertions).
- E2E browser desktop (Chromium): 1 passed (9.0s), diulang langsung 1 passed
  (9.9s); sisa data fixture 0 baris (termasuk periode soft-deleted).
- Pint (4 file PHP), `npm run test:e2e:types`, dan Prettier (spec E2E): lulus.

Detail keputusan kunci limiter, urutan middleware, angka batas, dan temuan di
luar scope ada di report
[`BE-P1-006`](../../big-changes/patching-ticket/BE-P1-006.md).
