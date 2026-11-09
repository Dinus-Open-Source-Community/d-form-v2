# Sapto Changes — 10 November 2026 (EP-03)

## Ringkasan

EP-03 menutup tiket performance recruitment/dashboard/reporting pada dataset
**1.000.000** baris. Semua target exit tercapai pada periode 20.000 applicant:

- daftar applicant p95 < 1 s (filter divisi 22 ms, search selective 3–5 ms,
  broad prefix 84 ms, search+filter 119–205 ms);
- halaman periode cold **1,09 s** (agregat) / 2,25 s fully cold, warm 74 ms;
- `queueCounts` 103–110 ms (dari 5,8 s);
- report build 20,8 s → ~2,1 s; interview aggregate 10,2 s → 333 ms;
- export funnel 20,9 s → 20 ms; export applicant 120 s → 9,7 s, peak 0,9 MB;
- `/dashboard` 112–160 ms (dari >300 s fallback);
- fixture form resolution 9,25 s → 22 ms.

| Issue | Commit |
| ----- | ------ |
| EP-03 (`BE-P2-012`, `BE-P2-013`, `BE-P2-014`, `BE-P2-016`, `BE-P2-021`, `BE-P2-022`, `BE-P2-023`, `BE-P2-026`, `BE-P2-027`, `BE-P2-028`, `BE-P2-029`, `BE-P3-020`, `HYG-002`, `HYG-004`, `HYG-005`) | `2fa0b9c` |

Laporan lengkap: `docs/big-changes/patching-ticket/EP-03/patching-ticket.md`.

## Baseline → sesudah (dev MySQL, periode 20.000)

| Operasi | Sebelum | Sesudah |
| --- | ---: | ---: |
| `paginate` tanpa filter | 91 ms | 54 ms |
| `paginate` filter divisi | 5.050 ms | **22 ms** |
| `paginate` search `A11` (20k match) | 4.124–7.900 ms | **84 ms** |
| `paginate` search + semester | — | **205 ms** |
| `queueCounts` | 5.837 ms | **103–110 ms** |
| `dashboard summary` stats blok | 9 query | **1 query (17,6 ms)** |
| period payload | 23 query | **3 query** |
| `buildFunnel` | 1.801 ms | **20 ms** |
| `build` penuh | 20.849 ms | **~2.100 ms** |
| export funnel | 20,9 s | **20 ms** |
| export applicants | 120 s | **9,7 s** (keyset, 0,9 MB) |
| interview aggregate | 10.240 ms | **333 ms** (`Using index`) |
| feedback stats | 6 query / 6.254 ms | **1 query** |
| `/dashboard` | >300 s fallback | **112–160 ms** |
| fixture form/field resolution | 9,25 s / 4,98 s | **22 ms / 1 ms** |

## Perubahan

- **Index**: `rec_apps_queue_counts_idx`, `rec_apps_period_secondary_division_idx`,
  `rec_apps_period_full_name_idx`, `rec_screenings_decision_app_idx`,
  `rec_correction_app_status_idx` (000001); `rec_interviews_app_status_idx`
  (000002); covering search `(period, kolom, submitted_at)` (000005).
- **Pagination/search**: total divisi = UNION dua covering count; pencarian
  substring tiga kolom dengan id-set pencarian diinterseksi dengan id-set filter
  (STRAIGHT_JOIN MySQL / joinSub SQLite); wildcard `%`/`_`/`!` di-escape.
- **Queue**: semua rule + filter mengecualikan `cancelled_at`; `all` tetap total;
  `corrections_pending` JOIN + cancelled.
- **Period page**: agregat per periode di-cache 60 s (group-link 300 s), hitungan
  QR summary memakai window function, hint index dijaga `Schema::hasIndex`.
- **Dashboard**: 8 stat + accepted dalam satu agregat; payload periode memakai
  `withCount`; fallback trend memakai index `form_answers.created_at`, cache 6 jam,
  rebuild `analytics:rebuild-daily-registrations`, `dashboard:warm` mengisi agregat.
- **Report/export**: feedback satu agregat; export funnel `buildFunnel`; export
  applicant keyset 500 baris + join divisi dengan `deleted_at IS NULL`; otorisasi
  period (`view`) + `recruitment.applications.export` untuk PII.
- **Migration hygiene**: index dev `rec_apps_period_regno_idx` sudah ada di
  migration lama; drop 4 index redundan (441,7 MB) disiapkan tetapi
  **approval-gated** (`APPLY_REDUNDANT_INDEX_DROP`), hard-block production,
  `down()` mengembalikan semuanya. `email_logs_notification_type_index`
  dipertahankan sebagai plan driver QR summary.

## Review dan perbaikan

`oracle` + `paladin` menemukan dan sudah diperbaiki: C1 (drop index merusak QR
summary), C2 (search+filter 24 s), H1 (index baru redundan), H2 (`cursor()`
buffered), H3 (division soft-deleted), H5/M3 (`corrections_pending`), M-1
(export PII tanpa `view` period), L-3 (gate migration di prod).

Residual/follow-up (bukan blocker): dashboard recruitment ~4,8 s (cache report),
`withCount` ~0,95 s (denormalisasi/cache), `/qr-status` JSON 9,1 s, audit export
setelah stream, invalidation cache tulis (saat ini TTL), dan drop 4 index
menunggu approval.

## Verifikasi

| Perintah | Hasil |
| --- | --- |
| `php artisan test --filter='RecruitmentApplication|...|Migration'` | **194 passed, 1 skipped** (1364 assertions) |
| Full Feature | 3 gagal milik lane EP-02 yang berjalan paralel (QR throttle/expectation/403), bukan EP-03 |
| `pint --test` (14 file) | PASS |
| Prettier / ESLint / vue-tsc / E2E types | 0 / 0 / 0 / 0 |
| Vitest | 306 passed |
| `npm run build` + budget | 0; entry ≤350 kB, chunk ≤200 kB |
| E2E period/report/export/dashboard | **25 passed** (retries 0) |
| `analytics:rebuild-daily-registrations` | 1.000.027 registrations, 1,96–2,48 s |
| `check:row-ownership` | `ok:true`, 0 violations |
