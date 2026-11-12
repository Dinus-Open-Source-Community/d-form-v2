# Sapto Changes — 9 November 2026 (BE-P2-008)

## Ringkasan

`RecruitmentApplicationService::queueCounts()` berubah dari **7 query `COUNT`
terpisah** menjadi **2 query** (satu agregat kondisional pada
`recruitment_applications` + satu count `recruitment_correction_requests`).
Aturan antrean dipusatkan di konstanta `QUEUE_RULES` yang dipakai bersama oleh
hitung badge dan filter daftar, sehingga keduanya tidak dapat menyimpang. Response
shape, urutan key, dan nilai tidak berubah.

| Issue | Commit |
| ----- | ------ |
| BE-P2-008 | `f91a871` — `perf: compute recruitment queue counts from one conditional aggregate` |

Laporan lengkap: `docs/big-changes/patching-ticket/BE-P2-008.md`.
Plan: `docs/big-changes/patching-ticket/BE-P2-008-plan.md`.

## Akar masalah (bukti)

- `app/Services/Recruitment/RecruitmentApplicationService.php::queueCounts()`
  menjalankan enam `count()` pada `recruitment_applications` (all, screening,
  revision, interview, final, done) + satu count koreksi pending.
- Predikat antrean diduplikasi di `applyQueueFilter()` (baris 125-138), sehingga
  badge dan filter daftar berisiko berbeda arti.
- Test merah sebelum implementasi: `actual size 7 vs expected size 2`.
- EXPLAIN MySQL 9 (1.000.000 baris, periode 20.000): satu index range scan tanpa
  temp table/filesort; query count turun 7→2, tetapi wall time belum turun
  signifikan karena `result`/`revision_required` tidak tercakup index.

## Perubahan

| File | Perubahan |
| ---- | --------- |
| `app/Services/Recruitment/RecruitmentApplicationService.php` | Konstanta `QUEUE_RULES` (satu definisi aturan); `queueCounts()` memakai satu `selectRaw` `COUNT(*)` + `SUM(CASE WHEN ... THEN 1 ELSE 0 END)` dengan parameter binding dan cast `int`; `corrections_pending` tetap satu query; `applyQueueFilter()` memakai `QUEUE_RULES` yang sama; helper privat `queueCasePredicate()` menerjemahkan aturan ke predikat SQL + binding. |
| `tests/Feature/Recruitment/RecruitmentApplicationQueueCountsTest.php` (baru) | 5 test: nilai 7 key (termasuk overlap revision/screening, rejected, periode lain, koreksi periode lain), path global `null`/`''`, periode kosong = 0 integer, tepat 2 query via `DB::listen`, dan badge == total filter untuk setiap key. |
| `tests/e2e/recruitment-queue-counts.desktop.spec.ts` (baru) | E2E browser: badge persis (`Semua antrean (7)`, dst.), badge == jumlah baris filter, periode scoping, 9 probe anomali (302/422/404 tanpa 5xx), dan filter diterapkan/dibersihkan tanpa badge ganda. |

Tidak ada perubahan route, response contract, schema, enum, otorisasi, tampilan,
atau `AGENTS.md`/`SKILL.md`.

## Keputusan desain

| Keputusan | Alasan / alternatif yang ditolak |
| --- | --- |
| Satu agregat kondisional | Membaca tabel sekali dan menghasilkan tepat satu baris. `GROUP BY stage` + derive di PHP menambah state mapping dan tetap butuh kolom agregat `revision`. |
| Dua query, bukan satu | `corrections_pending` berada di tabel lain; subquery SQL mentah mencampur dua model dan lebih sulit diuji. Acceptance mengizinkan satu/dua query. |
| `QUEUE_RULES` bersama | Dedup satu implementasi (bukan rename) dan mencegah badge/filter drift. Terjemahan aturan tetap ada di dua jalur (SQL dan Eloquent) dan keduanya wajib diperluas bersamaan bila bentuk aturan baru ditambah. |
| Tanpa index baru | Perubahan schema dipisah ke tiket follow-up `BE-P2-023`; tiket ini fokus pada agregasi query. |
| `periodId = '0'` | HEAD memperlakukan `'0'` sebagai falsy (`when($periodId)`), implementasi baru memperlakukannya sebagai periode (konsisten dengan enam key lain). Nilai ini bukan UUID valid dan tidak mungkin berasal dari route binding; perubahan disengaja dan didokumentasikan. |

## Dampak & risiko

- Round-trip database per `queueCounts()` turun 7 → 2.
- Latensi pada periode besar belum turun sampai covering index tersedia
  (`BE-P2-023`); risiko ini dicatat, bukan diklaim sebagai perbaikan latensi.
- Risiko semantik dimitigasi oleh `QUEUE_RULES` tunggal, test nilai 7 key,
  invariant badge == filter, dan E2E badge == baris.
- Rollback: revert satu file service; tidak ada migration atau perubahan data.

## Verifikasi

Lihat `docs/big-changes/patching-ticket/BE-P2-008.md` §Verifikasi untuk perintah,
exit status, hasil feature test, Pint, EXPLAIN, dan E2E browser.
