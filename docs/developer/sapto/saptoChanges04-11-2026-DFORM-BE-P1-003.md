# Sapto Changes — 4 November 2026 (DFORM-BE-P1-003)

## Ringkasan

Memperbaiki dispatch job recruitment agar side effect eksternal hanya masuk
queue setelah transaction database berhasil commit.

| Issue                                         | Commit              |
| --------------------------------------------- | ------------------- |
| BE-P1-003 — after-commit recruitment dispatch | Pending user commit |

## Perubahan

- Menambahkan `afterCommit()` pada tujuh dispatch di
  `ScreeningService`, `FinalSelectionService`, dan `CorrectionRequestService`.
- Tidak mengubah `after_commit` queue global, payload job, route, schema, atau
  migration.
- Menambahkan regression feature test dengan Redis queue terisolasi untuk
  membuktikan rollback membuang job dan commit men-enqueue job.
- Menambahkan evidence report di
  `docs/big-changes/patching-ticket/BE-P1-003.md`.

## Keputusan dan alternatif

`afterCommit()` per dispatch dipilih karena paling sempit dan sesuai Laravel.
Perubahan global `after_commit=true` ditolak karena dapat mengubah job dan
queued listener di luar scope ticket. Memindahkan dispatch keluar transaction
ditolak karena lebih besar dan tidak diperlukan.

## Verifikasi

- Regression BE-P1-003: 4 passed, 19 assertions.
- Targeted recruitment tests: 48 passed, 166 assertions.
- Full backend suite: 874 passed, 7 skipped, 5.835 assertions.
- Unit suite: 63 passed, 337 assertions.
- Pint, `git diff --check`, `npm run typecheck`, `npm run lint`, dan
  `npm run test:e2e:types`: PASS.
- Browser anomaly check: PASS.
- Full Playwright admin flow: Needs verification karena credential E2E tidak
  tersedia; detail ada di report ticket.

## Checklist

- [x] Root cause confirmed dari source dan reproduced lewat regression test.
- [x] Side effect tidak tersisa setelah rollback.
- [x] Side effect masuk queue setelah commit.
- [x] Tidak mengubah queue global.
- [x] Dokumentasi ticket dibuat.
- [ ] Commit final menunggu review dan instruksi commit.
