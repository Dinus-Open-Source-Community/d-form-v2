# Sapto Changes — 9 November 2026 (BE-P2-005)

## Ringkasan

`RecruitmentPeriodController::show()` memanggil `qrStatusForPeriod()` tanpa
memandang tab. Metode itu memuat seluruh applicant periode + seluruh
`email_logs` tipe `recruitment_interview_scheduled` (20.000 applicant/periode
pada dataset stress) lalu hanya memakai tiga angka untuk `qr_status_summary`.
Setelah patch, ringkasan QR hanya dihitung saat `tab=peserta` atau saat Inertia
partial reload secara eksplisit meminta `qr_status_summary` (refresh/polling
header). Tab `interview`/`laporan`/`interviewer`/`settings` tidak lagi menjalankan
query berat; nilai tab `peserta`, endpoint JSON `qr-status`, route, policy, dan
Vue tidak berubah.

| Issue | Commit |
| --- | --- |
| BE-P2-005 | belum dibuat — file controller masih bercampur perubahan lane BE-P2-003 yang belum commit |

Laporan lengkap: `docs/big-changes/patching-ticket/BE-P2-005.md`.
Plan: `docs/big-changes/patching-ticket/BE-P2-005-plan.md`.

## Akar masalah (bukti)

- `app/Http/Controllers/Dashboard/Recruitment/RecruitmentPeriodController.php`
  (`show()`): `$qrStatus = $this->qrStatusForPeriod($period);` tanpa guard.
- `qrStatusForPeriod()`: satu query memuat `id, full_name, registration_number`
  seluruh applicant periode, satu query `email_logs` dengan `whereIn` seluruh ID,
  lalu membangun `recipients` yang tidak dipakai `show()`.
- Feature test merah: `qr_status_summary` ditemukan pada tab `interview` dan
  `laporan/settings` sebelum patch.
- Dataset stress 20.000 applicant/periode: query berat 13,3–15,4 s per panggilan
  saat host terkontensi; peak memori 64–68 MB jika dipaksa berjalan.

## Perubahan

| File | Perubahan |
| --- | --- |
| `app/Http/Controllers/Dashboard/Recruitment/RecruitmentPeriodController.php` | Helper `shouldComputeQrStatusSummary(Request $request, string $tab): bool`; guard membungkus `qrStatusForPeriod()`; prop `qr_status_summary` hanya ditambahkan bila dihitung. Menerima child prop bertitik (`qr_status_summary.sent`). |
| `tests/Feature/Recruitment/RecruitmentPeriodQrStatusTabTest.php` (baru) | 8 test / 105 assertion: parity tab peserta, tab interview/laporan/settings/interviewer tidak menghitung, partial reload + child prop tetap menghitung, partial navigasi `TAB_ONLY.interview` tidak menghitung, fallback tab invalid, endpoint JSON tetap lengkap. |
| `tests/e2e/recruitment-qr-status-tabs.desktop.spec.ts` (baru) | E2E browser + anomali (`tab` fallback, 422) — belum dieksekusi karena lingkungan tersumbat. |

Tidak ada perubahan route, policy, permission, schema, migration, endpoint JSON
`qr-status`, atau file Vue.

## Keputusan desain

| Keputusan | Alasan / alternatif yang ditolak |
| --- | --- |
| Tab-gated ketat | Sesuai acceptance tiket + keputusan user. Alternatif "summary murah di semua tab" tetap menambah query agregat di tab lain dan tidak memenuhi test "tab lain tidak memanggil query". |
| Deteksi lewat `X-Inertia-Partial-Data` | Kontrak resmi Inertia untuk partial reload; dipakai refresh/polling header agar status QR tidak diam-diam basi. |
| Child prop bertitik diterima | Inertia mengizinkan `only` bertitik; tanpa prefix check produk dapat membaca 0. |
| Prop dihilangkan, bukan `null` | Kontrak Vue opsional (`?.`/`?? 0`); test `missing()` bermakna dan tidak mengirim data yang tidak perlu. |
| `qrStatusForPeriod()` tidak dipecah di tiket ini | Pemuatan `recipients` yang tidak dipakai adalah masalah terpisah; sudah dicakup `BE-P2-014`. |
| Endpoint JSON `qr-status` tidak disentuh | Endpoint itu butuh recipients lengkap; kontraknya dipertahankan dan diuji ulang. |

## Hasil pengukuran

Lingkungan: Octane `--workers=1`, MySQL 9, dataset stress 1.000.000 applicant
(periode `840882b4-83ef-50fc-aeba-bf3f43c2d64e`, 20.000 applicant). Host dipakai
lane lain (load 5–9), sehingga waktu dinding tidak diklaim sebagai speedup.

Cold load:

| Tab | Query | Query berat `email_logs ... in (` | Peak memori |
| --- | ---: | ---: | ---: |
| peserta | 23 | 3 | 64 MB |
| interview | 17 | **0** | **14 MB** |
| laporan | 24 | **0** | **32 MB** |
| settings | 5 | **0** | **14 MB** |

Kontrol A/B (request sama + `X-Inertia-Partial-Data: qr_status_summary` = jalur
lama): interview 19 query/1 berat/64 MB; laporan 26/1/68 MB; settings 7/1/64 MB;
peserta 23/3/64 MB.

Kesimpulan deterministik: tab non-peserta turun dari **1 → 0** query berat dan
peak memori **64–68 MB → 14–32 MB**. Waktu dinding bervariasi ±3,5× karena
kontensi; bukan klaim speedup.

## Verifikasi

- `php artisan test tests/Feature/Recruitment/RecruitmentPeriodQrStatusTabTest.php`
  → **8 passed (105 assertions)**, exit 0.
- Red test sebelum implementasi → 2 failed (prop ditemukan di tab non-peserta).
- `php artisan test RecruitmentPeriodQueryTabsTest RecruitmentPeriodApplicantListingTest RecruitmentQrBulkServiceTest`
  → **36 passed (263 assertions)**.
- `php artisan test tests/Feature/Recruitment` → **357 passed / 1 failed**;
  kegagalan `RecruitmentEmailResendTest:288` sudah menjadi tiket `BE-P2-018`.
- `pint --test` (controller + test baru) → PASS 2 files, exit 0.
- `npm run test:e2e:types` → exit 0; `npx eslint tests/e2e/recruitment-qr-status-tabs.desktop.spec.ts`
  → exit 0.
- E2E browser → **Needs verification (environment blocked)**: `/up` timeout
  5–12 s berulang, `/auth/login` 40–180 s, `/dashboard` 333.782 ms; satu worker
  Octane dipakai lane lain (`bench_insert_applications.php`,
  `bench_insert_email_logs_toggle.php`, `e2e:cleanup`, phpunit paralel).

## Review

- **@oracle:** APPROVE WITH MINOR FIXES. Dua High (commit isolation; label E2E
  salah `0 antrean`) dan dua Medium (child prop, coverage `interviewer`/partial
  navigasi) sudah diperbaiki. Trade-off cold-load dicatat; fixture slug tetap
  aman karena `workers: 1`; predikat query log diberi komentar; optimasi partial
  peserta yang tidak meminta prop ditunda ke `BE-P2-014`.
- **@paladin:** APPROVE. Tidak ada temuan security baru; header partial yang
  dikendalikan klien tidak menambah beban dibanding perilaku lama. Temuan
  pre-existing `RecruitmentPeriodPolicy` null-creator dicatat sebagai
  `SEC-P3-013`.

## Temuan di luar scope

| Tiket | Ringkas |
| --- | --- |
| `BE-P2-018` | `has_replayable_notification` hanya membaca log email terbaru; `RecruitmentEmailResendTest:288` gagal (sudah ada di `patching.md`) |
| `SEC-P3-013` | Periode `created_by = null` membuka `view()` dan recipients PII `qr-status` untuk user recruitment (tiket baru §7.5) |

Migration/index drift yang dilaporkan fixer **tidak terbukti**: `migrate:status`
menunjukkan `2026_11_09_000002_add_qr_eligibility_indexes` sudah Ran dan index
tersedia; file migration hanya masih untracked milik lane BE-P2-003.

## Risiko & rollback

- Commit belum dibuat: `RecruitmentPeriodController.php` masih memuat perubahan
  lane BE-P2-003 (`RecruitmentQrEligibilityQuery`, penghapusan
  `qrEligibleCount()`/`qrSentCount()`) dan service itu untracked. Commit harus
  menunggu BE-P2-003 mendarat, atau memakai staging hunk selektif (hanya helper
  guard, blok gating, dan conditional prop).
- Perilaku yang diterima: cold-load tab non-peserta tidak menampilkan chip
  progres/refresh sampai data peserta dimuat; tombol Kirim QR tetap aktif dari
  `qr_eligible_count`/`qr_sent_count`.
- Rollback: hapus guard dan kembalikan satu baris `$qrStatusForPeriod()` + prop
  unconditional; tidak ada schema/migration.
- Tab peserta belum dioptimasi (masih memuat recipients); lanjutan di
  `BE-P2-014`.
