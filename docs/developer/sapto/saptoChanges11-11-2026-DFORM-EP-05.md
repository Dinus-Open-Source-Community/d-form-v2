# Sapto Changes — 11 November 2026 (EP-05)

## Ringkasan

EP-05 menutup kontrak client/server untuk field dan submission: matriks tipe
field tunggal, validasi server yang konsisten (nama field, checkbox/multi-select,
scalar guard), password tidak lagi di-flash, cascade arsip event berbasis marker,
duplikasi dan mapper jawaban disatukan, serta pemisahan hook form-fill dan
autosave yang tidak lagi kehilangan perubahan atau menulis dua kali.

| Issue | Commit |
| ----- | ------ |
| EP-05 (`AUDIT-P1-014` + F1–F2, `AUDIT-P2-017/018`, `AUDIT-P3-012/013/014`, `BE-P2-011/017`, `BE-P3-006/013/014/015`, `FLOW-P1-004/005/008/009`, `FLOW-P2-001/004/007`, `FE-AUDIT-003-F1`) | `PENDING` |

Laporan lengkap: `docs/big-changes/patching-ticket/EP-05/patching-ticket.md`.

## Perubahan

**Kontrak field & validasi**

- `lib/formFieldContract.ts` + `tests/fixtures/form-field-contract.json` menjadi
  satu sumber daftar display-only/api-type, dikonsumsi satu test PHP
  (`FormFieldContractTest`) dan satu Vitest — memenuhi exit criterion "satu
  contract matrix dipakai client/server".
- Blok display-only (`heading`, `paragraph`, `divider`, `banner`, `image`) tidak
  divalidasi dan tidak pernah masuk `answers`.
- Nama field reserved (`team_member_emails`, `_token`, `_method`), prefix
  `bundle__`, serta karakter `.`/`*` ditolak 422 dengan pesan yang menyebut nama.
- Whitelist checkbox **dan** multi-select dari `rules.in` → `optionChoices` →
  `options`, memakai `Rule::in(array)` sehingga label berisi `,`/`|` tidak
  memecah atau menyuntik rule.
- Scalar guard menjadi default aman dan type-aware (`numeric`/`date`/`string`);
  `banner` dihapus dari `API_TYPES` (representasi sah `fileUpload` +
  `metadata.builderType`) → 422, bukan 500.

**Submission**

- `app/Support/SecretAnswerInput` menjadi satu-satunya filter input rahasia
  (`current_password`/`password`/`password_confirmation`, field
  `metadata.type=password` + `_confirmation`, slot bundle) dan dipakai di dua
  redirect submit serta `TeamInvitationController`.
- `successRedirect()` memakai `can('view', $event)` sehingga organizer non-pemilik
  tidak lagi mendarat di 403.
- Cek duplikat guest: pre-check penuh sebelum mapping jawaban (tidak ada file
  private tertulis saat ditolak) + predicate-only race guard di transaksi;
  1 lookup user + 2 predikat.
- Satu `FormAnswerInputMapper::mergeAnswers(..., preserveStoredFiles)` untuk
  submit dan konfirmasi undangan.
- Cascade arsip event memakai marker `archived_by_event_id`: hanya baris aktif
  yang diarsipkan, restore hanya menghidupkan baris bertanda event itu, dan data
  migration backfill mengembalikan perilaku restore untuk arsip pra-migrasi.

**Autosave & builder**

- Semua exit wizard menunggu `flush()` single-flight, memblokir dengan toast saat
  gagal, dan menampilkan busy state; `flush()` tidak menulis saat tidak ada
  pekerjaan tertunda (menghapus save ganda per navigasi).
- Perubahan < debounce tidak hilang saat refresh: `hasUnsavedChanges` +
  `pagehide` keepalive flush (XSRF dari cookie), form bersih tidak mengirim apa pun.
- Sanitizer gambar punya satu pemilik: `bannerUrl` dan seluruh metadata yang
  dipertahankan dibersihkan sehingga `data:`/`blob:` tidak pernah mencapai DB.
- `useBuilderImageUpload` 199 → 73 baris (pure → `lib/`, browser →
  `adapters/browser/`, HTTP → `adapters/http/`).
- Satu core `lib/formBuilderFieldList.ts` dipakai demo dan production; parser
  drag payload memakai allowlist (bukan cast).
- `useFormFillPage` 442 → 122 baris sebagai facade atas lima hook; komponen
  form-fill presentational (339 → 254, 325 → 306); `lib/formSubmissionAnswerKeys`
  dan `lib/registrationStatusUi` menjadi implementasi tunggal.

## Review dan perbaikan

`oracle` (arsitektur) dan `paladin` (keamanan) meninjau seluruh diff. Temuan yang
ditutup sebelum merge:

- **H1** sanitasi dijalankan dua kali lalu dibuang dan `bannerUrl` tidak pernah
  dibersihkan (blob bisa masuk DB).
- **H2** `flush()` selalu menulis sehingga setiap keluar SPA mengirim dua save.
- **M-1** `TeamInvitationController` masih mem-flash password (fix
  `AUDIT-P2-017` belum lengkap).
- **L-1/L-2** multi-select tanpa whitelist dan rule `in:` dari string (label
  berkoma/pipe melebarkan whitelist).
- **L-3** arsip pra-migrasi tidak pernah dihidupkan lagi (backfill).
- **M1** tiga salinan daftar display-only + docblock stale → matriks kontrak.
- **M2/M5/M7/L1–L4** props by value, busy state 30 s, barrel, adapter object URL,
  argumen TS salah, test unload yang tidak membuktikan guard, `memberSlots` ganda.
- **M3/M4/M6** invarian race dua fase didokumentasikan, coercion numerik diganti
  guard type-aware, semantik marker + `forceDelete` masuk CONTEXT/runbook.

## Residual

- Aturan `input`/`number`/`rating` belum memakai `numeric`/`integer`
  (pre-existing, integritas data) — kandidat tiket baru.
- Body keepalive > ~64 KiB dapat ditolak browser dan hilang senyap saat unload
  (batas platform); tidak ada peringatan `beforeunload` (keputusan produk).
- Rejection duplikat yang kalah balapan di dalam transaksi dapat meninggalkan
  satu upload yang baru disimpan (dimiliki storage sweep).
- `Show.vue` menghitung `answerKeys` dengan aturan berbeda dari helper bersama
  (urutan kolom export) — perlu tiket terpisah.
- `isPersistableImageUrl` masih menerima `javascript:` (tidak exploitable karena
  selalu di-bind ke `<img src>`; kandidat defense-in-depth).
- Field lama di DB dengan nama `.`/`*` masih bisa disubmit tetapi tidak bisa
  disimpan ulang lewat builder.
- Demo builder tidak punya route; parity demo/production dibuktikan via Vitest.

## Verifikasi

| Perintah | Hasil |
| --- | --- |
| `php artisan test` | **1.497 passed, 12 skipped** (11.619 assertions) |
| Pint | PASS, **772 file** |
| Vitest | **513 passed** (52 file) |
| `npm run typecheck` / `typecheck:unit` | 0 / 0 |
| ESLint / Prettier / E2E types | 0 / 0 / 0 |
| `npm run build` + bundle budget | PASS; entry ≤350 kB gzip, 240 chunk, tidak ada chunk > 200 kB |
| E2E (builder-autosave, fe-audit-003, form-submission-boundary, autosave-retry, autosave-unload, wizard-navigation, qr-scan) | **33 passed** (`--retries=0`) |
| Migrasi `000001`/`000002` | diterapkan di dev (batch 15/16) dan E2E; `MigrationRollbackTest` hijau |

Status index `patching.md`: 20 tiket EP-05 → `In Review`; `FLOW-P1-008` dan
`FLOW-P2-001` → `Stale` (klaim audit tidak lagi valid, kode sudah sesuai).
