# Sapto Changes — 04 November 2026 (BE-P1-004)

## Ringkasan

Broadcast yang dijadwalkan bisa diproses dua kali saat dua scheduler/worker
berjalan bersamaan: claim `scheduled → processing` gagal pada pemanggil kedua,
tetapi alur luar tetap meng-queue job recipient. Perbaikan membuat claim
atomik dengan conditional `UPDATE` + `affected rows`; job hanya di-dispatch
oleh pemanggil yang benar-benar memenangkan claim. Pola conditional update
yang sama diterapkan pada reset recipient di `retryFailed()`.

| Issue     | Commit    |
| --------- | --------- |
| BE-P1-004 | `3cacfc8` |

## Perubahan

- `app/Services/Broadcasting/BroadcastDispatchService.php`: `dispatch()`
  memakai satu `UPDATE ... WHERE status = 'scheduled'` dan mengembalikan
  `bool`; `dispatchDue()` hanya mencatat claim yang menang; `retryFailed()`
  hanya me-reset dan meng-queue recipient yang berhasil diubah; import
  `EmailBroadcastRecipient` yang hilang diperbaiki (retry sebelumnya selalu
  `Class not found`).
- `tests/Feature/Broadcasting/BroadcastDispatchClaimTest.php` (baru): 8
  regression test termasuk simulasi claim kalah deterministik dan retry race.
- `tests/e2e/broadcast-dispatch-claim.desktop.spec.ts` (baru): happy path
  schedule → dispatch → sent dan anomaly validasi (create, schedule tanpa isi,
  email invalid, duplikat). Skip default saat `FEATURE_BROADCAST=false`.
- `docs/big-changes/patching-ticket/BE-P1-004.md`: report dan evidence lengkap.

## Verifikasi

- Regression test baru: merah 6 failed → hijau 8 passed (23 assertions).
- `tests/Feature/Broadcasting`: 14 passed, 2 skipped (42 assertions).
- Full backend suite: 874 passed, 7 skipped (5.527 assertions).
- E2E browser desktop: 2 passed; DB `total_sent/total_failed/recipients =
2/0/2` (tidak ada double-send).
- Pint, `npm run test:e2e:types`, dan Prettier: lulus.

Detail command, konfigurasi E2E sementara, dan temuan di luar scope ada di
report [`BE-P1-004`](../../big-changes/patching-ticket/BE-P1-004.md).
