# Sapto Changes — 9 November 2026 (BE-P2-007)

| Field     | Nilai                                                                                                              |
| --------- | ------------------------------------------------------------------------------------------------------------------ |
| Issue     | BE-P2-007                                                                                                          |
| Commit    | `58dedb8` — `perf: aggregate recruitment report interview stats in one query`                                      |
| File kode | `app/Services/Recruitment/RecruitmentReportService.php`                                                            |
| Test      | `tests/Feature/Recruitment/RecruitmentReportServiceTest.php`, `tests/e2e/recruitment-report-query.desktop.spec.ts` |
| Laporan   | `docs/big-changes/patching-ticket/BE-P2-007.md`                                                                    |
| Plan      | `docs/big-changes/patching-ticket/BE-P2-007-plan.md`                                                               |

## Ringkasan

`RecruitmentReportService::build()` mematerialisasi seluruh application ID satu
periode lewat `pluck('id')`, lalu `buildInterviewStats()` menjalankan lima count
`whereIn` dengan list yang sama. Pada dataset stress 1.000.000 applicant (periode
terbesar 20.000 applicant) satu `build()` terukur 39,0 s, +18,9 MB, 19 query, dan
20.001 binding per query interview.

Perubahan: `build()` tidak lagi mengambil ID; `buildInterviewStats(string $periodId)`
memakai satu query agregat
`whereIn(<subquery id periode>) + GROUP BY status`. `total` menjumlah seluruh baris
hasil `GROUP BY` sehingga semantik `count(*)` lama (termasuk status tak dikenal)
tetap sama. Urutan key dan nilai tidak berubah.

## Hasil terukur

| Metrik (20.000 applicant) | Sebelum                  | Sesudah          |
| ------------------------- | ------------------------ | ---------------- |
| `build()` penuh           | 39.012 ms                | 17.046–27.875 ms |
| Peak memory delta         | 18.874.368 B             | 0 B              |
| Query interview           | 5                        | 1                |
| Binding maksimum          | 20.001                   | 2                |
| `interview_stats`         | `4581/0/15111/308/20000` | identik          |

Pengukuran terkontrol fungsi interview-stats dengan tiga run bergantian pada host
yang sama: 26,8–32,3 s → 7,4–12,8 s; memory delta 6,29 MB → 0.

## Test

- `RecruitmentReportServiceTest` (baru): 6 test / 24 assertions. Angka per status,
  isolasi periode, primary+secondary, periode kosong, `total` termasuk status tak
  dikenal (`no_show`), bentuk query (1 statement, `GROUP BY`, tepat 1 binding,
  tetap konstan pada 200 applicant, guard anti-`pluck('id')`), kontrak report.
  Test query-shape merah pada implementasi lama (5 statement).
- Regresi 4 file (ReportService, PeriodQueryTabs, QrStatusTab, InterviewExport)
  — 39 passed / 316 assertions.
- Seluruh `tests/Feature/Recruitment` + `DashboardStatsCacheTest` — 374 passed,
  1 failed pre-existing `BE-P2-018`.
- Pint PASS untuk dua file.
- E2E browser (container terisolasi, SQLite, port 18080): 5 passed / exit 0 pada
  dua run hijau (8020 pra-review, 18080 pasca-review). Fixture 64 interview;
  anomali period id non-UUID → 404,
  `period_id` non-UUID export → 422 `errors.period_id`, UUID tidak ada → 200 CSV
  header saja, tanpa sesi → 302 login, tanpa 5xx.

## Keputusan

`whereIn(subquery)` + `GROUP BY` dipilih karena satu binding, tanpa list ID di PHP,
dan hasil identik. `JOIN` dan `EXISTS` diukur setara/lebih lambat. Tidak ada
perubahan route, policy, controller, props, atau Vue.

Residual: join masih 7–13 s pada 20.000 applicant di host terkontensi karena
`status` tidak tercakup index interview → tiket `BE-P2-021`. `buildFeedbackStats()`
masih 6 query → `BE-P2-022`.

Tidak ada perubahan `AGENTS.md`/`SKILL.md`.
